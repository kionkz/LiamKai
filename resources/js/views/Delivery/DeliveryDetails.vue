<template>
  <div class="detail-page">

    <!-- Header -->
    <div class="page-header">
      <div>
        <button class="back-btn" @click="$router.back()">&#8592; Back to Logistics</button>
        <h1 v-if="order">Order #{{ String(order.id).padStart(4, '0') }}</h1>
      </div>
      <span v-if="order" class="status-pill" :class="order.fulfillment_status">
        {{ statusLabel(order.fulfillment_status, order.fulfillment_type) }}
      </span>
    </div>

    <div v-if="loading" class="state-msg">Loading order...</div>
    <div v-else-if="loadError" class="state-msg error">{{ loadError }}</div>

    <template v-else-if="order">
      <div class="grid-two">

        <!-- Order Info -->
        <div class="card">
          <h3 class="section-title">Order Information</h3>
          <div class="info-rows">
            <div class="info-row">
              <span class="lbl">Customer</span>
              <span>{{ order.customer?.name ?? 'Walk-In Customer' }}</span>
            </div>
            <div class="info-row">
              <span class="lbl">Fulfillment Type</span>
              <span class="type-badge" :class="order.fulfillment_type">
                {{ order.fulfillment_type === 'pickup' ? 'Pickup' : 'Delivery' }}
              </span>
            </div>
            <div class="info-row">
              <span class="lbl">{{ order.fulfillment_type === 'pickup' ? 'Pickup Note' : 'Delivery Address' }}</span>
              <span>{{ order.delivery_address || '—' }}</span>
            </div>
            <div class="info-row">
              <span class="lbl">Scheduled</span>
              <span>{{ formatDateTime(order.scheduled_for) }}</span>
            </div>
            <div class="info-row">
              <span class="lbl">{{ order.fulfillment_type === 'pickup' ? 'Picked Up Date' : 'Delivered Date' }}</span>
              <span>{{ formatDateTime(order.actual_fulfillment_at) }}</span>
            </div>
            <div class="info-row">
              <span class="lbl">Total Amount</span>
              <span class="amount">{{ formatAmount(order.total_amount) }}</span>
            </div>
            <div class="info-row">
              <span class="lbl">Payment Status</span>
              <span class="payment-badge" :class="order.payment_status">
                {{ order.payment_status ?? '—' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Items -->
        <div class="card">
          <h3 class="section-title">Order Items</h3>
          <table class="items-table" v-if="(order.items || []).length">
            <thead>
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in order.items" :key="item.id">
                <td>{{ item.product?.name || 'Product' }}</td>
                <td>{{ item.quantity }} {{ item.unit || '' }}</td>
                <td>{{ formatAmount(item.unit_price) }}</td>
                <td>{{ formatAmount(item.subtotal || item.total) }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="empty-items">No items found.</p>
        </div>

      </div>

      <!-- Status Update -->
      <div class="card status-card">
        <h3 class="section-title">Update Fulfillment Status</h3>
        <div class="completion-date-row">
          <label>{{ completionDateLabel }}</label>
          <input v-model="actualFulfillmentAt" type="datetime-local" :disabled="order.fulfillment_status === 'completed'" />
        </div>
        <div class="status-buttons">
          <button
            v-for="opt in statusOptions"
            :key="opt.value"
            class="status-btn"
            :class="{ active: order.fulfillment_status === opt.value }"
            :disabled="updating || !canSelectStatus(opt.value)"
            @click="updateStatus(opt.value)"
          >
            <span class="btn-icon">{{ opt.icon }}</span>
            {{ statusLabel(opt.value, order.fulfillment_type) }}
          </button>
        </div>
        <p v-if="updateMessage" class="update-msg" :class="{ error: updateError }">
          {{ updateMessage }}
        </p>
      </div>

      <div class="card">
        <h3 class="section-title">Audit</h3>
        <div class="audit-box">
          <strong>{{ auditLabel }}</strong>
          <span>{{ auditTimeLabel }}</span>
        </div>
      </div>
    </template>

  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api';
import { formatPeso } from '../../utils/currency';

const route = useRoute();

const loading = ref(false);
const loadError = ref('');
const updating = ref(false);
const updateMessage = ref('');
const updateError = ref(false);
const order = ref(null);
const actualFulfillmentAt = ref('');

const statusOptions = [
  { value: 'pending', icon: '○' },
  { value: 'in_progress', icon: '→' },
  { value: 'completed', icon: '✓' },
];

const statusLabel = (s, fulfillmentType = 'delivery') => {
  if (s === 'in_progress') return fulfillmentType === 'pickup' ? 'Ready for Pickup' : 'En-route';
  if (s === 'completed') return fulfillmentType === 'pickup' ? 'Picked Up' : 'Delivered';
  return 'Pending';
};

const completionDateLabel = computed(() => {
  if (!order.value) return 'Completion Date';
  return order.value.fulfillment_type === 'pickup' ? 'Pickup Date & Time' : 'Delivery Date & Time';
});

const userName = (user) => user?.name || user?.username || 'Unknown user';

const auditLabel = computed(() => {
  if (!order.value?.fulfillment_action && !order.value?.fulfillment_updated_by) {
    return 'No logistics update yet';
  }

  return `${userName(order.value.fulfillment_updated_by)} ${order.value.fulfillment_action || 'updated logistics'}`;
});

const auditTimeLabel = computed(() => {
  const value = order.value?.actual_fulfillment_at || order.value?.updated_at;
  return value ? formatDateTime(value) : '—';
});

const canSelectStatus = (status) => {
  if (!order.value || order.value.fulfillment_status === status) return false;
  if (order.value.fulfillment_status === 'completed') return false;
  if (
    order.value.fulfillment_type !== 'pickup'
    && order.value.fulfillment_status === 'pending'
    && status === 'completed'
  ) return false;
  return true;
};

const formatDateTime = (value) => {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
};

const formatDateTimeLocal = (value) => {
  if (!value) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().slice(0, 16);
  }

  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
};

const formatAmount = formatPeso;

const loadOrder = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    const res = await api.get(`/orders/${route.params.id}`);
    if (res.data?.success) {
      order.value = res.data.data;
      actualFulfillmentAt.value = formatDateTimeLocal(res.data.data.actual_fulfillment_at);
      return;
    }
    loadError.value = res.data?.message || 'Failed to load order';
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Failed to load order';
  } finally {
    loading.value = false;
  }
};

const updateStatus = async (status) => {
  if (!order.value) return;
  updating.value = true;
  updateMessage.value = '';
  updateError.value = false;
  try {
    const payload = { status };
    if (status === 'completed') {
      payload.actual_fulfillment_at = actualFulfillmentAt.value || formatDateTimeLocal();
    }

    const res = await api.patch(`/orders/${order.value.id}/fulfillment-status`, payload);
    if (res.data?.success) {
      order.value = {
        ...order.value,
        fulfillment_status: res.data.data?.status || status,
        delivery_status: res.data.data?.delivery_status || order.value.delivery_status,
        delivery_date: res.data.data?.delivery_date || order.value.delivery_date,
        actual_fulfillment_at: res.data.data?.actual_fulfillment_at || order.value.actual_fulfillment_at,
        fulfillment_action: res.data.data?.fulfillment_action || order.value.fulfillment_action,
        fulfillment_updated_by: res.data.data?.fulfillment_updated_by || order.value.fulfillment_updated_by,
      };
      actualFulfillmentAt.value = formatDateTimeLocal(order.value.actual_fulfillment_at);
      updateMessage.value = 'Status updated successfully.';
      return;
    }
    updateError.value = true;
    updateMessage.value = res.data?.message || 'Update failed';
  } catch (e) {
    updateError.value = true;
    updateMessage.value = e.response?.data?.message || 'Update failed';
  } finally {
    updating.value = false;
  }
};

onMounted(loadOrder);
</script>

<style scoped>
.detail-page {
  max-width: 960px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
  animation: fadeIn 0.25s ease;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
}

.back-btn {
  background: none;
  border: none;
  color: #7b8598;
  font-size: 13px;
  cursor: pointer;
  padding: 0;
  margin-bottom: 6px;
  display: block;
}
.back-btn:hover { color: #0a1d37; }

.page-header h1 { margin: 0; font-size: 26px; color: #0a1d37; font-weight: 800; }

.state-msg { padding: 40px; text-align: center; color: #607089; font-size: 14px; }
.state-msg.error { color: #c0392b; }

.grid-two {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e6eaf2;
  padding: 22px;
  box-shadow: 0 2px 8px rgba(10, 25, 52, 0.05);
}

.section-title {
  margin: 0 0 16px;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: #6b7a99;
  font-weight: 700;
}

.info-rows { display: flex; flex-direction: column; gap: 0; }

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #f0f3f8;
  font-size: 14px;
  gap: 12px;
}
.info-row:last-child { border-bottom: none; }
.lbl { color: #7b8598; font-size: 13px; flex-shrink: 0; }

.amount { font-weight: 700; color: #0a1d37; }

/* Status pills */
.status-pill {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
}
.status-pill.in_progress { background: #d3ecff; color: #2f7db7; }
.status-pill.completed   { background: #daf5e3; color: #2a8d57; }
.status-pill.pending     { background: #fef3e2; color: #9a6f20; }

/* Payment badge */
.payment-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
  background: #f0f0f0;
  color: #555;
}
.payment-badge.paid   { background: #daf5e3; color: #2a8d57; }
.payment-badge.unpaid { background: #fdecea; color: #c0392b; }

/* Type badge */
.type-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
}
.type-badge.delivery { background: #e3f2fd; color: #1565c0; }
.type-badge.pickup   { background: #f3e5f5; color: #6a1b9a; }

/* Items table */
.items-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.items-table th {
  text-align: left;
  padding: 8px 10px;
  font-size: 11px;
  text-transform: uppercase;
  color: #6b7a99;
  font-weight: 700;
  border-bottom: 2px solid #eef1f5;
}
.items-table td { padding: 10px; border-bottom: 1px solid #f0f3f8; color: #2b3650; }
.items-table tr:last-child td { border-bottom: none; }

.empty-items { color: #9aaab8; font-size: 13px; text-align: center; padding: 20px; }

/* Status card */
.status-card { }

.completion-date-row {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-bottom: 14px;
}

.completion-date-row label {
  color: #3f4d5f;
  font-size: 13px;
  font-weight: 700;
}

.completion-date-row input {
  height: 42px;
  border: 1px solid #dce2ec;
  border-radius: 8px;
  color: #25334a;
  padding: 0 12px;
}

.completion-date-row input:disabled {
  background: #f3f5f7;
  color: #7a8594;
}

.status-buttons { display: flex; gap: 12px; flex-wrap: wrap; }

.status-btn {
  flex: 1;
  min-width: 120px;
  padding: 14px 20px;
  border: 2px solid #e0e4ee;
  border-radius: 10px;
  background: #f8fafc;
  color: #2a3b57;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.status-btn:hover:not(:disabled) {
  border-color: #e28937;
  background: #fff7ed;
  color: #e28937;
}

.status-btn.active {
  border-color: #0a1d37;
  background: #0a1d37;
  color: #fff;
}

.status-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-icon { font-size: 16px; }

.update-msg { margin: 14px 0 0; font-size: 13px; color: #2a8d57; font-weight: 600; }
.update-msg.error { color: #c0392b; }

.audit-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid #e6eaf2;
  border-radius: 10px;
  background: #f8fafc;
}

.audit-box strong {
  color: #0a1d37;
  font-size: 14px;
}

.audit-box span {
  color: #607089;
  font-size: 13px;
}

@media (max-width: 720px) {
  .grid-two { grid-template-columns: 1fr; }
}

@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>
