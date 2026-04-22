<template>
  <div class="order-detail-container">
    <div class="header-section">
      <div class="header-left">
        <button @click="$router.push('/orders')" class="btn btn-back">← Back to Orders</button>
        <h1>Order #{{ order?.id?.toString().padStart(4, '0') || 'Loading...' }}</h1>
      </div>
      <div class="header-actions">
        <button @click="editOrder" class="btn btn-secondary">Edit Order</button>
        <button @click="exportOrderPdf" class="btn btn-secondary">Export PDF</button>
        <button @click="deleteOrder" class="btn btn-danger" :disabled="!canCancelOrder">Cancel Order</button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <p>Loading order details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="fetchOrder" class="btn btn-secondary">Retry</button>
    </div>

    <!-- Order Details -->
    <div v-else-if="order" class="order-content">
      <div class="order-header">
        <div class="order-info">
          <div class="info-row">
            <span class="label">Order Date:</span>
            <span>{{ new Date(order.created_at).toLocaleDateString() }}</span>
          </div>
          <div class="info-row">
            <span class="label">Customer:</span>
            <span>{{ order.customer?.name || 'N/A' }}</span>
          </div>
          <div class="info-row">
            <span class="label">Type:</span>
            <span class="badge" :class="order.type">{{ order.type === 'retail' ? 'Retail' : 'Wholesale' }}</span>
          </div>
          <div class="info-row">
            <span class="label">Status:</span>
            <span class="status" :class="order.order_status">{{ formatOrderStatus(order.order_status) }}</span>
          </div>
        </div>
        <div class="order-total">
          <div class="total-amount">
            <span class="label">Total Amount:</span>
            <span class="amount">₱{{ order.total_amount?.toLocaleString() || '0' }}</span>
          </div>
        </div>
      </div>

      <!-- Order Items -->
      <div class="order-items">
        <h3>Order Items</h3>
        <table class="items-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Quantity</th>
              <th>Unit Price</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.id">
              <td>{{ item.product?.name || 'N/A' }}</td>
              <td>{{ item.quantity }} {{ item.unit }}</td>
              <td>₱{{ item.unit_price?.toLocaleString() || '0' }}</td>
              <td>₱{{ item.total?.toLocaleString() || '0' }}</td>
            </tr>
            <tr v-if="!order.items || order.items.length === 0">
              <td colspan="4" class="no-data">No items found for this order.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="delivery-info" v-if="order.fulfillment_type">
        <h3>Fulfillment Information</h3>
        <div class="delivery-details">
          <div class="info-row">
            <span class="label">Fulfillment Type:</span>
            <span>{{ order.fulfillment_type === 'pickup' ? 'Pickup' : 'Delivery' }}</span>
          </div>
          <div class="info-row" v-if="order.scheduled_for">
            <span class="label">Scheduled Date &amp; Time:</span>
            <span>{{ new Date(order.scheduled_for).toLocaleString() }}</span>
          </div>
          <div class="info-row" v-if="order.fulfillment_status">
            <span class="label">Logistics Status:</span>
            <span>{{ order.fulfillment_status }}</span>
          </div>
          <div class="info-row">
            <span class="label">Delivery Address:</span>
            <span>{{ order.fulfillment_type === 'pickup' ? 'Customer pickup' : (order.delivery_address || '--') }}</span>
          </div>
          <div class="info-row" v-if="order.notes">
            <span class="label">Notes:</span>
            <span>{{ order.notes }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click="showDeleteConfirm = false">
      <div class="modal-content small-modal" @click.stop>
        <h3>Cancel Order</h3>
        <p>Are you sure you want to cancel Order #{{ order?.id?.toString().padStart(4, '0') }}?</p>
        <p class="warning">Cancelling restores inventory and removes the order from active lists.</p>
        <div class="modal-actions">
          <button @click="showDeleteConfirm = false" class="btn btn-secondary">Go Back</button>
          <button @click="confirmDelete" :disabled="deleting" class="btn btn-danger">
            {{ deleting ? 'Cancelling...' : 'Confirm Cancel' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api';
import { exportReceiptPdf } from '../../utils/receiptPdf';

const route = useRoute();
const router = useRouter();

const order = ref(null);
const loading = ref(false);
const error = ref('');
const showDeleteConfirm = ref(false);
const deleting = ref(false);

const canCancelOrder = computed(() => {
  if (!order.value) return false;

  const hasLogisticsProgress = order.value.fulfillment_status !== 'pending'
    || order.value.delivery_status !== 'pending';
  const hasPaymentActivity = ['paid', 'partially_paid', 'utang'].includes(order.value.payment_status)
    || Number(order.value.outstanding_balance || 0) < Number(order.value.total_amount || 0);

  return !hasLogisticsProgress && !hasPaymentActivity;
});

const fetchOrder = async () => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get(`/orders/${route.params.id}`);
    if (response.data.success) {
      order.value = response.data.data;
    } else {
      error.value = response.data.message || 'Failed to load order';
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load order';
  } finally {
    loading.value = false;
  }
};

const editOrder = () => {
  // Navigate to edit order page
  alert('Edit order functionality will be implemented');
};

const formatCurrency = (value) => `₱${Number(value || 0).toFixed(2)}`;

const formatOrderStatus = (value) => {
  if (value === 'complete') return 'Complete';
  if (value === 'cancelled') return 'Cancelled';
  return 'Pending';
};

const exportOrderPdf = () => {
  if (!order.value) return;

  exportReceiptPdf({
    title: `Order #${String(order.value.id).padStart(4, '0')}`,
    subtitle: 'Customer Order Receipt',
    filename: `order-${String(order.value.id).padStart(4, '0')}.pdf`,
    meta: [
      { label: 'Customer', value: order.value.customer?.name || 'N/A' },
      { label: 'Pricing', value: order.value.type === 'wholesale' ? 'Wholesale' : 'Retail' },
      { label: 'Fulfillment', value: order.value.fulfillment_type === 'pickup' ? 'Pickup' : 'Delivery' },
      { label: 'Scheduled', value: order.value.scheduled_for ? new Date(order.value.scheduled_for).toLocaleString() : '--' },
    ],
    items: (order.value.items || []).map((item) => ({
      name: item.product?.name || 'N/A',
      qty: `${item.quantity} ${item.unit || ''}`.trim(),
      unitPrice: formatCurrency(item.unit_price),
      amount: formatCurrency(item.total || item.subtotal),
    })),
    totals: [
      { label: 'Total', value: formatCurrency(order.value.total_amount) },
    ],
  });
};

const deleteOrder = () => {
  if (!canCancelOrder.value) return;
  showDeleteConfirm.value = true;
};

const confirmDelete = async () => {
  deleting.value = true;
  try {
    const response = await api.delete(`/orders/${order.value.id}`);
    if (response.data.success) {
      router.push('/orders');
    } else {
      alert(response.data.message || 'Failed to cancel order');
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to cancel order');
  } finally {
    deleting.value = false;
  }
};

onMounted(() => {
  fetchOrder();
});
</script>
