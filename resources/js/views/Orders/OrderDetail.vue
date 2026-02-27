<template>
  <div class="order-detail-container">
    <div class="header-section">
      <div class="header-left">
        <button @click="$router.push('/orders')" class="btn btn-back">← Back to Orders</button>
        <h1>Order #{{ order?.id?.toString().padStart(4, '0') || 'Loading...' }}</h1>
      </div>
      <div class="header-actions">
        <button @click="editOrder" class="btn btn-secondary">Edit Order</button>
        <button @click="printOrder" class="btn btn-secondary">Print</button>
        <button @click="deleteOrder" class="btn btn-danger">Delete</button>
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
            <span class="status" :class="order.status">{{ order.status }}</span>
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

      <!-- Payment Information -->
      <div class="payment-info">
        <h3>Payment Information</h3>
        <div class="payment-details">
          <div class="info-row">
            <span class="label">Payment Method:</span>
            <span>{{ order.payment_method || 'Cash' }}</span>
          </div>
          <div class="info-row">
            <span class="label">Payment Status:</span>
            <span class="status" :class="order.status">{{ order.status }}</span>
          </div>
          <div class="info-row" v-if="order.notes">
            <span class="label">Notes:</span>
            <span>{{ order.notes }}</span>
          </div>
        </div>
      </div>

      <!-- Delivery Information -->
      <div class="delivery-info" v-if="order.delivery_address">
        <h3>Delivery Information</h3>
        <div class="delivery-details">
          <div class="info-row">
            <span class="label">Delivery Address:</span>
            <span>{{ order.delivery_address }}</span>
          </div>
          <div class="info-row" v-if="order.delivery_date">
            <span class="label">Delivery Date:</span>
            <span>{{ new Date(order.delivery_date).toLocaleDateString() }}</span>
          </div>
          <div class="info-row" v-if="order.delivery_status">
            <span class="label">Delivery Status:</span>
            <span>{{ order.delivery_status }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click="showDeleteConfirm = false">
      <div class="modal-content small-modal" @click.stop>
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete Order #{{ order?.id?.toString().padStart(4, '0') }}?</p>
        <p class="warning">This action cannot be undone.</p>
        <div class="modal-actions">
          <button @click="showDeleteConfirm = false" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="btn btn-danger">
            {{ deleting ? 'Deleting...' : 'Delete Order' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api';

const route = useRoute();
const router = useRouter();

const order = ref(null);
const loading = ref(false);
const error = ref('');
const showDeleteConfirm = ref(false);
const deleting = ref(false);

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

const printOrder = () => {
  window.print();
};

const deleteOrder = () => {
  showDeleteConfirm.value = true;
};

const confirmDelete = async () => {
  deleting.value = true;
  try {
    const response = await api.delete(`/orders/${order.value.id}`);
    if (response.data.success) {
      router.push('/orders');
    } else {
      alert(response.data.message || 'Failed to delete order');
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete order');
  } finally {
    deleting.value = false;
  }
};

onMounted(() => {
  fetchOrder();
});
</script>