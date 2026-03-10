<template>
  <div class="delivery-details">
    <div class="card">
      <div class="header">
        <h1>{{ deliveryNumber }}</h1>
        <button @click="$router.back()">← Back</button>
      </div>

      <div v-if="loading" class="state">Loading delivery...</div>
      <div v-else-if="loadError" class="state error">{{ loadError }}</div>
      <template v-else>

      <div class="details-grid">
        <div class="details-section">
          <h3>Delivery Information</h3>
          <div class="detail-row">
            <span class="label">Status:</span>
            <span class="status" :class="statusClass">{{ statusLabel }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Current Location:</span>
            <span>{{ currentLocation }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Assigned Driver:</span>
            <span>{{ assignedDriver }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Scheduled Time:</span>
            <span>{{ scheduledTime }}</span>
          </div>
        </div>

        <div class="details-section">
          <h3>Delivery Items</h3>
          <table class="items-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in deliveryItems" :key="item.id">
                <td>{{ item.name }}</td>
                <td>{{ item.qty }}</td>
                <td>₱{{ item.price }}</td>
              </tr>
              <tr v-if="deliveryItems.length === 0">
                <td colspan="3">No delivery items</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="actions-section">
        <h3>Update Status</h3>
        <div class="status-buttons">
          <button
            @click="updateStatus('picked_up')"
            class="btn btn-status"
            :class="{ active: selectedAction === 'picked_up' }"
            :disabled="updating"
          >📦 Picked Up</button>
          <button
            @click="updateStatus('in_transit')"
            class="btn btn-status"
            :class="{ active: selectedAction === 'in_transit' }"
            :disabled="updating"
          >🚚 In Transit</button>
          <button
            @click="updateStatus('delivered')"
            class="btn btn-status"
            :class="{ active: selectedAction === 'delivered' }"
            :disabled="updating"
          >✓ Delivered</button>
          <button
            @click="updateStatus('failed')"
            class="btn btn-status btn-failed"
            :class="{ active: selectedAction === 'failed' }"
            :disabled="updating"
          >✕ Failed</button>
        </div>
        <p v-if="updateMessage" class="update-message">{{ updateMessage }}</p>
      </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api';

const route = useRoute();

const loading = ref(false);
const loadError = ref('');
const updating = ref(false);
const updateMessage = ref('');
const delivery = ref(null);
const selectedAction = ref('');

const formatDateTime = (value) => {
  if (!value) return '--';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '--';
  return date.toLocaleString();
};

const deliveryNumber = computed(() => {
  if (!delivery.value) return 'Delivery';
  return `Delivery #DLV-${String(delivery.value.id).padStart(4, '0')}`;
});

const statusLabel = computed(() => {
  const status = delivery.value?.status;
  if (status === 'in_transit') return 'In Transit';
  if (status === 'delivered') return 'Delivered';
  if (status === 'failed') return 'Failed';
  return 'Pending';
});

const statusClass = computed(() => {
  const status = delivery.value?.status;
  if (status === 'in_transit') return 'pending';
  if (status === 'delivered') return 'delivered';
  if (status === 'failed') return 'failed';
  return 'pending';
});

const assignedDriver = computed(() => delivery.value?.employee?.name || 'Unassigned');
const currentLocation = computed(() => delivery.value?.delivery_address || delivery.value?.order?.delivery_address || '--');
const scheduledTime = computed(() => formatDateTime(delivery.value?.scheduled_delivery));

const deliveryItems = computed(() => {
  const items = delivery.value?.order?.order_items || delivery.value?.order?.orderItems || [];
  return items.map((item) => ({
    id: item.id,
    name: item.product?.name || 'Product',
    qty: item.quantity,
    price: Number(item.unit_price || 0).toFixed(2),
  }));
});

const loadDelivery = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    const response = await api.get(`/deliveries/${route.params.id}`);
    if (response.data?.success) {
      delivery.value = response.data.data;
      selectedAction.value = ['in_transit', 'delivered', 'failed'].includes(delivery.value.status)
        ? delivery.value.status
        : '';
      return;
    }
    loadError.value = response.data?.message || 'Failed to load delivery details';
  } catch (error) {
    loadError.value = error.response?.data?.message || 'Failed to load delivery details';
  } finally {
    loading.value = false;
  }
};

const updateStatus = async (action) => {
  if (!delivery.value) return;

  updating.value = true;
  updateMessage.value = '';
  selectedAction.value = action;

  const status = action === 'picked_up' ? 'in_transit' : action;
  const payload = { status };
  if (status === 'delivered') {
    payload.actual_delivery_date = new Date().toISOString();
  }

  try {
    const response = await api.put(`/deliveries/${delivery.value.id}`, payload);
    if (response.data?.success) {
      delivery.value = response.data.data;
      updateMessage.value = 'Status updated successfully';
      return;
    }
    updateMessage.value = response.data?.message || 'Failed to update status';
  } catch (error) {
    updateMessage.value = error.response?.data?.message || 'Failed to update status';
  } finally {
    updating.value = false;
  }
};

onMounted(loadDelivery);
</script>

<style scoped>
.delivery-details {
  max-width: 900px;
  margin: 0 auto;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 30px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.header h1 {
  margin: 0;
  color: #0a1d37;
}

.header button {
  padding: 8px 12px;
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
}

.details-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
  margin-bottom: 30px;
}

.details-section h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
  font-size: 14px;
  text-transform: uppercase;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #e0e0e0;
}

.detail-row .label {
  color: #666;
  font-weight: 500;
}

.status {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.status.pending {
  background-color: #e3f2fd;
  color: #1976d2;
}

.status.delivered {
  background-color: #e8f5e9;
  color: #2e7d32;
}

.status.failed {
  background-color: #fdecea;
  color: #b71c1c;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  background-color: #f9f9f9;
  border-radius: 6px;
  overflow: hidden;
}

.items-table th {
  padding: 10px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  border-bottom: 2px solid #e0e0e0;
}

.items-table td {
  padding: 10px;
  border-bottom: 1px solid #e0e0e0;
}

.actions-section {
  padding: 20px;
  background-color: #f9f9f9;
  border-radius: 6px;
  margin-bottom: 20px;
}

.actions-section h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
}

.status-buttons {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.btn {
  padding: 10px 15px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
}

.btn-status {
  background-color: #f0f0f0;
  color: #333;
  border: 1px solid #ddd;
  flex: 1;
}

.btn-status:hover {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
}

.btn-status.active {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
}

.btn-failed.active {
  background-color: #dc2626;
  border-color: #dc2626;
}

.btn-status:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover {
  background-color: #d46a1a;
}

.state {
  margin-bottom: 16px;
  color: #334155;
}

.state.error {
  color: #b91c1c;
}

.update-message {
  margin: 8px 0 0 0;
  color: #1e40af;
  font-size: 13px;
}

@media (max-width: 768px) {
  .details-grid {
    grid-template-columns: 1fr;
  }

  .status-buttons {
    flex-direction: column;
  }
}
</style>
