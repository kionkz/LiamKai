<template>
  <div class="receiving-container">
    <div class="header-section">
      <router-link to="/purchasing" class="link-back">← Back to POs</router-link>
      <h1>Receiving Report - {{ purchaseOrder?.order_number || 'Loading...' }}</h1>
    </div>

    <div v-if="loading" class="state-card">Loading purchase order...</div>
    <div v-else-if="errorMessage && !purchaseOrder" class="state-card error">{{ errorMessage }}</div>

    <template v-else-if="purchaseOrder">
      <div class="po-info-card">
        <div class="info-row">
          <div class="info-group">
            <p class="label">PO Number</p>
            <p class="value">{{ purchaseOrder.order_number }}</p>
          </div>
          <div class="info-group">
            <p class="label">Supplier</p>
            <p class="value">{{ purchaseOrder.supplier?.name || 'Unknown Supplier' }}</p>
          </div>
          <div class="info-group">
            <p class="label">Original Date</p>
            <p class="value">{{ formatDate(purchaseOrder.order_date) }}</p>
          </div>
          <div class="info-group">
            <p class="label">Status</p>
            <p class="value status-text" :class="'status-' + purchaseOrder.status">{{ formatStatus(purchaseOrder.status) }}</p>
          </div>
        </div>
      </div>

      <!-- Closed-state banners -->
      <div v-if="purchaseOrder.status === 'received'" class="status-banner banner-received">
        ✓ This purchase order has been fully received and is now closed. No further edits are allowed.
      </div>
      <div v-else-if="purchaseOrder.status === 'cancelled'" class="status-banner banner-cancelled">
        ✗ This purchase order has been cancelled. No receiving actions are available.
      </div>

      <!-- Submit-level error message -->
      <div v-if="errorMessage" class="state-card error">{{ errorMessage }}</div>

      <!-- Editable form — only shown when PO can still be received -->
      <form v-if="purchaseOrder.status !== 'received' && purchaseOrder.status !== 'cancelled'" @submit.prevent="submitReceiving" class="receiving-form">
        <div class="form-section">
          <h2>Received Items</h2>

          <table class="items-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Ordered (kg)</th>
                <th>Accepted (kg)</th>
                <th>Damaged (kg)</th>
                <th>Short (kg)</th>
                <th>Balance (kg)</th>
              </tr>
            </thead>
            <tbody>
              <template
                v-for="(item, idx) in items"
                :key="item.purchase_order_item_id"
              >
                <!-- Main product row -->
                <tr :class="{ 'row-complete': rowBalance(idx) === 0 }"><td class="col-product">{{ item.product }}</td>
                  <td>{{ item.ordered.toFixed(2) }}</td>

                  <!-- Accepted -->
                  <td>
                    <input
                      v-model.number="item.received"
                      type="number"
                      min="0"
                      :max="maxForReceived(idx)"
                      step="0.01"
                      placeholder="0"
                      :disabled="item.remaining === 0"
                      @input="normalizeRow(idx)"
                    />
                  </td>

                  <!-- Damaged -->
                  <td>
                    <input
                      v-model.number="item.damaged"
                      type="number"
                      min="0"
                      :max="maxForDamaged(idx)"
                      step="0.01"
                      placeholder="0"
                      :disabled="item.remaining === 0"
                      @input="normalizeRow(idx)"
                    />
                  </td>

                  <!-- Short -->
                  <td>
                    <input
                      v-model.number="item.short"
                      type="number"
                      min="0"
                      :max="maxForShort(idx)"
                      step="0.01"
                      placeholder="0"
                      :disabled="item.remaining === 0"
                      @input="normalizeRow(idx)"
                    />
                  </td>

                  <!-- Balance -->
                  <td :class="rowBalance(idx) === 0 ? 'balance-zero' : 'balance-positive'">
                    {{ rowBalance(idx).toFixed(2) }}
                  </td>
                </tr>

                <!-- Instance batches row -->
                <tr class="instances-row">
                  <td colspan="6" class="instances-cell">
                    <div class="instances-wrap">
                      <div
                        v-for="(batch, bIdx) in item.instances"
                        :key="bIdx"
                        class="instance-entry"
                      >
                        <div class="instance-field">
                          <label class="instance-label">Quantity (kg)</label>
                          <input
                            v-model.number="batch.quantity"
                            type="number"
                            min="0.01"
                            :max="maxInstanceQty(idx, bIdx)"
                            step="0.01"
                            placeholder="0.00"
                            class="instance-input"
                            @input="clampInstance(idx, bIdx)"
                          />
                        </div>
                        <div class="instance-field">
                          <label class="instance-label">Expiration Date</label>
                          <input
                            v-model="batch.expiration_date"
                            type="date"
                            class="instance-input"
                            :min="todayDate"
                          />
                        </div>
                        <button
                          type="button"
                          class="remove-instance-btn"
                          title="Remove this batch"
                          @click="removeInstance(idx, bIdx)"
                        >✕</button>
                      </div>

                      <button
                        type="button"
                        class="add-instance-btn"
                        :disabled="instancesRemaining(idx) <= 0"
                        @click="addInstance(idx)"
                      >
                        + Add new instance
                        <span v-if="instancesRemaining(idx) > 0" class="remaining-hint">({{ instancesRemaining(idx).toFixed(2) }} kg unassigned)</span>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>

          <p class="hint">
            Enter Accepted, Damaged, and Short per product. Optionally split the accepted qty into expiration date batches below each row.
          </p>
        </div>

        <div class="form-section">
          <h2>Damage &amp; Shortage Notes</h2>
          <div class="form-group">
            <label>Damage Details</label>
            <textarea v-model="formData.damageNotes" rows="3" placeholder="Describe any damaged items..."></textarea>
          </div>

          <div class="form-group">
            <label>Shortage Details</label>
            <textarea v-model="formData.shortageNotes" rows="3" placeholder="Describe any missing items..."></textarea>
          </div>

          <div class="form-group">
            <label>Receiving Notes</label>
            <textarea v-model="formData.notes" rows="3" placeholder="General receiving notes..."></textarea>
          </div>
        </div>

        <div class="form-section">
          <h2>Quick Payment</h2>
          <div class="payment-grid">
            <div class="form-group">
              <label>Payment Type</label>
              <select v-model="formData.paymentMethod">
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            </div>
            <div class="form-group">
              <label>Payment Date</label>
              <input :value="todayDate" type="date" disabled />
            </div>
            <div class="form-group">
              <label>Reference No.</label>
              <input
                v-model="formData.paymentReference"
                type="text"
                :readonly="formData.paymentMethod === 'cash'"
                :placeholder="formData.paymentMethod === 'bank_transfer' ? 'Enter bank transaction reference' : ''"
                :required="formData.paymentMethod === 'bank_transfer'"
              />
            </div>
          </div>
          <p class="hint">
            Payment date follows the receiving date. Cash uses a generated reference number; bank transfer uses the bank transaction reference.
          </p>
        </div>

        <div class="form-section">
          <h2>Confirmation</h2>
          <div class="check-group">
            <input v-model="formData.inspected" type="checkbox" id="inspected" required />
            <label for="inspected">Items have been physically inspected and counted</label>
          </div>

          <div class="check-group">
            <input v-model="formData.storageConfirmed" type="checkbox" id="storage" required />
            <label for="storage">Items have been stored in designated locations</label>
          </div>

          <!-- Receiving Person — auto-filled from logged-in user, read-only -->
          <div class="form-group receiving-person-group">
            <label>Receiving Person</label>
            <input
              type="text"
              :value="formData.recipientName"
              disabled
              class="recipient-input"
              title="Automatically set to the logged-in user"
            />
          </div>
        </div>

        <div class="summary-section">
          <div class="summary-row">
            <span>Total Ordered:</span>
            <span>{{ totalOrdered.toFixed(2) }} kg</span>
          </div>
          <div class="summary-row">
            <span>Total Accepted:</span>
            <span>{{ totalReceived.toFixed(2) }} kg</span>
          </div>
          <div class="summary-row alert-row" v-if="totalDamaged > 0">
            <span>Total Damaged:</span>
            <span>{{ totalDamaged.toFixed(2) }} kg</span>
          </div>
          <div class="summary-row alert-row" v-if="totalShort > 0">
            <span>Total Short:</span>
            <span>{{ totalShort.toFixed(2) }} kg</span>
          </div>
          <div class="summary-row warning-row" v-if="totalBalance > 0">
            <span>Remaining Unaccounted:</span>
            <span>{{ totalBalance.toFixed(2) }} kg</span>
          </div>
        </div>

        <div class="form-actions">
          <router-link to="/purchasing" class="btn btn-secondary">Cancel</router-link>
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="submitting || !hasAnyQuantity"
            :title="!hasAnyQuantity ? 'Enter at least one quantity to confirm receipt' : ''"
          >
            {{ submitting ? 'Saving...' : 'Confirm Receipt' }}
          </button>
        </div>
      </form>

      <!-- Unassigned expiration date warning modal -->
      <div v-if="showUnassignedWarning" class="modal-overlay">
        <div class="warning-modal">
          <div class="warning-modal-icon">⚠️</div>
          <h3>Unassigned Expiration Dates</h3>
          <p>Some accepted quantities have <strong>not been assigned to expiration date batches</strong>:</p>
          <ul class="unassigned-list">
            <li v-for="item in unassignedItems" :key="item.product">
              <strong>{{ item.product }}</strong> — {{ item.unassigned.toFixed(2) }} kg unassigned
            </li>
          </ul>
          <p class="warning-note">Stock received without an expiration date will not be automatically expired. Proceed anyway?</p>
          <div class="warning-modal-actions">
            <button class="btn btn-secondary" @click="showUnassignedWarning = false">Go Back &amp; Fix</button>
            <button class="btn btn-primary" @click="confirmReceiveAnyway">Proceed Anyway</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const todayDate = new Date().toISOString().slice(0, 10);
import { useAuthStore } from '../../stores/authStore';
import api from '../../api';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const purchaseOrder = ref(null);
const items = ref([]);
const loading = ref(false);
const submitting = ref(false);
const errorMessage = ref('');
const showUnassignedWarning = ref(false);
const unassignedItems = ref([]);

const formData = ref({
  damageNotes: '',
  shortageNotes: '',
  notes: '',
  inspected: false,
  storageConfirmed: false,
  recipientName: '',
  paymentMethod: 'cash',
  paymentReference: '',
});

// ─── Totals ──────────────────────────────────────────────────────────────────
const totalOrdered   = computed(() => items.value.reduce((s, i) => s + i.ordered, 0));
const instancesTotal = (idx) => {
  const item = items.value[idx];
  if (!item) return 0;
  return item.instances.reduce((s, b) => s + Number(b.quantity || 0), 0);
};
/** How much of the accepted qty hasn't been assigned to an instance yet. */
const instancesRemaining = (idx) => {
  const item = items.value[idx];
  if (!item) return 0;
  return Math.max(0, Number(item.received || 0) - instancesTotal(idx));
};
const totalReceived  = computed(() => items.value.reduce((s, i) => s + Number(i.received || 0), 0));
const totalDamaged   = computed(() => items.value.reduce((s, i) => s + Number(i.damaged || 0), 0));
const totalShort     = computed(() => items.value.reduce((s, i) => s + Number(i.short || 0), 0));
const totalBalance   = computed(() =>
  items.value.reduce((s, _, idx) => s + rowBalance(idx), 0)
);
const hasAnyQuantity = computed(() =>
  items.value.some((i) => Number(i.received) > 0 || Number(i.damaged) > 0 || Number(i.short) > 0)
);

// ─── Per-row helpers ──────────────────────────────────────────────────────────
const rowBalance = (idx) => {
  const item = items.value[idx];
  if (!item) return 0;
  return Math.max(0, item.remaining - Number(item.received || 0) - Number(item.damaged || 0) - Number(item.short || 0));
};

/** Max the user may enter for Accepted. */
const maxForReceived = (idx) => {
  const item = items.value[idx];
  if (!item) return 0;
  return Math.max(0, item.remaining - Number(item.damaged || 0) - Number(item.short || 0));
};

/** Max quantity for a specific instance — capped by accepted qty. */
const maxInstanceQty = (idx, bIdx) => {
  const item = items.value[idx];
  if (!item) return 0;
  const otherInstances = item.instances.reduce((s, b, i) => i === bIdx ? s : s + Number(b.quantity || 0), 0);
  return Math.max(0, Number(item.received || 0) - otherInstances);
};

const clampInstance = (idx, bIdx) => {
  const item = items.value[idx];
  if (!item) return;
  const max = maxInstanceQty(idx, bIdx);
  const b = item.instances[bIdx];
  b.quantity = Math.min(Math.max(0, Number(b.quantity || 0)), max);
};

/** Max for Damaged. */
const maxForDamaged = (idx) => {
  const item = items.value[idx];
  if (!item) return 0;
  return Math.max(0, item.remaining - Number(item.received || 0) - Number(item.short || 0));
};

/** Max for Short. */
const maxForShort = (idx) => {
  const item = items.value[idx];
  if (!item) return 0;
  return Math.max(0, item.remaining - Number(item.received || 0) - Number(item.damaged || 0));
};

const normalizeRow = (idx) => {
  const item = items.value[idx];
  if (!item) return;
  item.received = Math.max(0, Number(item.received || 0));
  item.damaged  = Math.max(0, Number(item.damaged || 0));
  item.short    = Math.max(0, Number(item.short || 0));
  const total = item.received + item.damaged + item.short;
  if (total > item.remaining) {
    let overflow = parseFloat((total - item.remaining).toFixed(6));
    const shortCut = Math.min(item.short, overflow);
    item.short = parseFloat((item.short - shortCut).toFixed(4));
    overflow = parseFloat((overflow - shortCut).toFixed(6));
    if (overflow > 0) {
      const damagedCut = Math.min(item.damaged, overflow);
      item.damaged = parseFloat((item.damaged - damagedCut).toFixed(4));
      overflow = parseFloat((overflow - damagedCut).toFixed(6));
    }
    if (overflow > 0) {
      item.received = parseFloat(Math.max(0, item.received - overflow).toFixed(4));
    }
  }
  // Also clamp instances so they don't exceed received
  item.instances.forEach((b, bIdx) => clampInstance(idx, bIdx));
};

// ─── Instance management ─────────────────────────────────────────────────────
const addInstance = (idx) => {
  const item = items.value[idx];
  if (!item || instancesRemaining(idx) <= 0) return;
  item.instances.push({ quantity: instancesRemaining(idx), expiration_date: '' });
};

const removeInstance = (idx, bIdx) => {
  items.value[idx].instances.splice(bIdx, 1);
};

// ─── Formatting ──────────────────────────────────────────────────────────────
const formatDate = (value) => {
  if (!value) return '—';
  return new Date(value).toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
  });
};

const formatStatus = (status) => String(status || '').replace(/_/g, ' ');

const generatePaymentReference = (orderNumber = '') => {
  const date = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const suffix = Math.random().toString(36).slice(2, 7).toUpperCase();
  const poPart = String(orderNumber || 'PO').replace(/[^A-Z0-9]/gi, '').slice(-6).toUpperCase();
  return `POPAY-${date}-${poPart}-${suffix}`;
};

// ─── Data fetching ────────────────────────────────────────────────────────────
const fetchPurchaseOrder = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const response = await api.get(`/purchase-orders/${route.params.id}`);
    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to load purchase order');
    }

    purchaseOrder.value = response.data.data;
    formData.value.paymentReference = generatePaymentReference(purchaseOrder.value.order_number);
    items.value = (purchaseOrder.value.purchase_order_items || []).map((item) => {
      const ordered        = Number(item.quantity || 0);
      const alreadyReceived = Number(item.received_quantity || 0);
      return {
        purchase_order_item_id: item.id,
        product: item.product?.name || `Product #${item.product_id}`,
        ordered,
        alreadyReceived,
        remaining: ordered,
        received: 0,
        damaged: 0,
        short: 0,
        instances: [],
      };
    });
  } catch (error) {
    errorMessage.value = error.response?.data?.message || error.message || 'Failed to load purchase order';
  } finally {
    loading.value = false;
  }
};

// ─── Submit ───────────────────────────────────────────────────────────────────
const buildUnassigned = () => {
  return items.value
    .map((item, idx) => ({ product: item.product, unassigned: instancesRemaining(idx) }))
    .filter(x => x.unassigned > 0.001);
};

const confirmReceiveAnyway = async () => {
  showUnassignedWarning.value = false;
  await doSubmitReceiving();
};

const submitReceiving = async () => {
  // Check for items with accepted qty but unassigned instances
  const missing = buildUnassigned().filter((_, idx) => Number(items.value[idx]?.received || 0) > 0);
  const realMissing = items.value
    .map((item, idx) => ({ product: item.product, unassigned: instancesRemaining(idx), received: Number(item.received || 0) }))
    .filter(x => x.received > 0 && x.unassigned > 0.001);

  if (realMissing.length > 0) {
    unassignedItems.value = realMissing;
    showUnassignedWarning.value = true;
    return;
  }

  await doSubmitReceiving();
};

const doSubmitReceiving = async () => {
  submitting.value = true;
  errorMessage.value = '';

  try {
    const payload = {
      recipient_name: formData.value.recipientName,
      damage_notes: formData.value.damageNotes,
      shortage_notes: formData.value.shortageNotes,
      notes: formData.value.notes,
      payment_method: formData.value.paymentMethod,
      payment_reference: formData.value.paymentReference,
      received_items: items.value
        .filter((item) => Number(item.received) > 0 || Number(item.damaged) > 0 || Number(item.short) > 0)
        .map((item) => ({
          purchase_order_item_id: item.purchase_order_item_id,
          received_quantity: Number(item.received || 0),
          damaged_quantity: Number(item.damaged || 0),
          short_quantity: Number(item.short || 0),
          instances: item.instances
            .filter((b) => Number(b.quantity || 0) > 0)
            .map((b) => ({
              quantity: Number(b.quantity),
              expiration_date: b.expiration_date || null,
            })),
        })),
    };

    if (!payload.received_items.length) {
      throw new Error('Enter at least one received, damaged, or short quantity.');
    }

    const response = await api.put(`/purchase-orders/${route.params.id}`, payload);
    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to save receiving report');
    }

    router.push('/purchasing');
  } catch (error) {
    errorMessage.value = error.response?.data?.message || error.message || 'Failed to save receiving report';
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  fetchPurchaseOrder();
  // Auto-fill receiving person from the authenticated session — not editable by the user.
  formData.value.recipientName =
    authStore.user?.name || authStore.user?.username || 'Unknown';
  if (!formData.value.paymentReference) {
    formData.value.paymentReference = generatePaymentReference();
  }
});

watch(() => formData.value.paymentMethod, (method) => {
  if (method === 'cash') {
    formData.value.paymentReference = generatePaymentReference(purchaseOrder.value?.order_number);
    return;
  }

  formData.value.paymentReference = '';
});
</script>

<style scoped>
/* ── Unassigned-batches warning modal ────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
}

.warning-modal {
  background: #fff;
  border-radius: 12px;
  padding: 32px 28px;
  max-width: 480px;
  width: 90%;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
  text-align: center;
}

.warning-modal-icon {
  font-size: 40px;
  margin-bottom: 12px;
}

.warning-modal h3 {
  margin: 0 0 12px;
  color: #0a1d37;
  font-size: 18px;
}

.warning-modal p {
  color: #444;
  margin: 0 0 10px;
  font-size: 14px;
}

.unassigned-list {
  text-align: left;
  background: #fff8f0;
  border-left: 3px solid #e57c2a;
  border-radius: 6px;
  padding: 10px 14px;
  margin: 10px 0 14px;
  list-style: none;
}

.unassigned-list li {
  font-size: 13px;
  color: #333;
  margin-bottom: 4px;
}

.warning-note {
  font-size: 12px;
  color: #888;
  margin-bottom: 18px !important;
}

.warning-modal-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.receiving-container {
  animation: fadeIn 0.3s ease-in;
}

.header-section {
  margin-bottom: 20px;
}

.link-back {
  display: inline-block;
  color: #e57c2a;
  text-decoration: none;
  font-weight: 500;
  margin-bottom: 10px;
  transition: color 0.3s;
}

.link-back:hover {
  color: #d46a1a;
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
}

.state-card {
  background: white;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 20px;
}

.state-card.error {
  color: #b42318;
  border-left: 4px solid #b42318;
}

/* ── Status Banners ─────────────────────────────────────── */
.status-banner {
  border-radius: 8px;
  padding: 14px 20px;
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 20px;
}

.banner-received {
  background: #ecfdf3;
  color: #027a48;
  border-left: 4px solid #027a48;
}

.banner-cancelled {
  background: #fff4ed;
  color: #b54708;
  border-left: 4px solid #b54708;
}

/* ── PO Info Card ───────────────────────────────────────── */
.po-info-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 25px;
}

.info-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}

.info-group {
  margin: 0;
}

.info-group .label {
  margin: 0;
  font-size: 12px;
  text-transform: uppercase;
  color: #999;
  font-weight: 600;
}

.info-group .value {
  margin: 5px 0 0 0;
  font-size: 16px;
  color: #0a1d37;
  font-weight: 600;
}

.status-text {
  text-transform: capitalize;
}

.status-pending          { color: #b54708; }
.status-received         { color: #027a48; }
.status-cancelled        { color: #b42318; }

/* ── Form ───────────────────────────────────────────────── */
.receiving-form {
  background: white;
  border-radius: 8px;
  padding: 25px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.form-section {
  margin-bottom: 30px;
}

.form-section h2 {
  margin-top: 0;
  margin-bottom: 15px;
  color: #0a1d37;
  font-size: 16px;
}

/* ── Instance Batches ───────────────────────────────────── */
.instances-row {
  background: #fafbfc;
}

.instances-cell {
  padding: 0 0 14px 0 !important;
  border-bottom: 2px solid #e0e0e0;
}

.instances-wrap {
  padding: 10px 16px 4px 48px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.instance-entry {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  background: #fff;
  border: 1px solid #e4e9f0;
  border-radius: 8px;
  padding: 10px 14px;
  position: relative;
}

.instance-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.instance-label {
  font-size: 11px;
  font-weight: 600;
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.instance-input {
  height: 36px;
  border: 1px solid #ddd;
  border-radius: 6px;
  padding: 0 10px;
  font-size: 14px;
  min-width: 130px;
}

.instance-input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 2px rgba(229, 124, 42, 0.12);
}

.remove-instance-btn {
  background: none;
  border: 1px solid #e0e0e0;
  border-radius: 50%;
  width: 26px;
  height: 26px;
  display: grid;
  place-items: center;
  cursor: pointer;
  color: #999;
  font-size: 12px;
  align-self: flex-end;
  margin-bottom: 2px;
  flex-shrink: 0;
  transition: background 0.15s, color 0.15s;
}

.remove-instance-btn:hover {
  background: #fde8e8;
  border-color: #e57373;
  color: #c62828;
}

.add-instance-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: 1px dashed #e57c2a;
  border-radius: 7px;
  padding: 7px 14px;
  color: #e57c2a;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
  width: fit-content;
}

.add-instance-btn:hover:not(:disabled) {
  background: #fff7f0;
}

.add-instance-btn:disabled {
  border-color: #ccc;
  color: #bbb;
  cursor: not-allowed;
}

.remaining-hint {
  font-size: 11px;
  font-weight: 400;
  color: #b57a3e;
}

.accepted-cell {
  text-align: center;
}

.accepted-total {
  font-weight: 700;
  color: #999;
  font-size: 14px;
}

.accepted-total.total-full {
  color: #0a1d37;
}

/* ── Items Table ────────────────────────────────────────── */
.items-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}

.items-table thead {
  background-color: #f9f9f9;
}

.items-table th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  color: #666;
  border-bottom: 2px solid #e0e0e0;
}

.items-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e0e0e0;
  vertical-align: middle;
}

.col-product {
  font-weight: 500;
  color: #0a1d37;
}

/* Row that has been fully accounted for */
.row-complete {
  background-color: #f0faf4;
}

.items-table input[type="number"] {
  width: 100%;
  padding: 7px 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.items-table input[type="number"]:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 2px rgba(229, 124, 42, 0.15);
}

/* Input disabled because total would be exceeded */
.items-table input[type="number"]:disabled {
  background-color: #f5f5f5;
  color: #aaa;
  cursor: not-allowed;
}

/* Input still enabled but the other fields have consumed the budget */
.input-at-limit {
  border-color: #f0b429 !important;
  background-color: #fffdf0 !important;
}

/* Balance column */
.balance-zero {
  font-weight: 700;
  color: #027a48;
}

.balance-positive {
  font-weight: 600;
  color: #1570ef;
}

.hint {
  font-size: 12px;
  color: #888;
  margin: 6px 0 0 0;
}

/* ── Notes / Form Groups ────────────────────────────────── */
.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

.form-group input[type="text"],
.form-group input[type="date"],
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  font-family: inherit;
  box-sizing: border-box;
}

.form-group textarea:focus,
.form-group input[type="date"]:focus,
.form-group select:focus,
.form-group input[type="text"]:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.form-group input:disabled,
.form-group input[readonly] {
  background-color: #f5f5f5;
  color: #555;
}

.payment-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

/* ── Receiving Person ───────────────────────────────────── */
.receiving-person-group {
  margin-top: 16px;
}

.recipient-input {
  background-color: #f5f5f5;
  color: #555;
  cursor: not-allowed;
  border: 1px solid #e0e0e0 !important;
  width: 100%;
  padding: 10px;
  border-radius: 4px;
  font-size: 14px;
  box-sizing: border-box;
}

/* ── Checkboxes ─────────────────────────────────────────── */
.check-group {
  margin-bottom: 12px;
  display: flex;
  align-items: center;
}

.check-group input[type="checkbox"] {
  margin-right: 10px;
  cursor: pointer;
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.check-group label {
  margin: 0;
  cursor: pointer;
  font-size: 14px;
  color: #333;
}

/* ── Summary ────────────────────────────────────────────── */
.summary-section {
  background-color: #f9f9f9;
  padding: 20px;
  border-radius: 6px;
  margin-bottom: 25px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
  font-weight: 600;
  color: #0a1d37;
  border-bottom: 1px solid #eee;
}

.summary-row:last-child {
  border-bottom: none;
}

.summary-row.alert-row {
  color: #d32f2f;
}

.summary-row.warning-row {
  color: #b54708;
}

/* ── Actions ────────────────────────────────────────────── */
.form-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #d46a1a;
}

.btn-primary:disabled {
  background-color: #f5c49a;
  cursor: not-allowed;
}

.btn-secondary {
  background-color: #f0f0f0;
  color: #333;
}

.btn-secondary:hover {
  background-color: #e0e0e0;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
