<template>
  <div class="orders-container">
    <div class="header-section">
      <div class="header-left">
        <button @click="$router.push('/')" class="btn btn-back">← Back to Dashboard</button>
        <h1>Orders Management</h1>
      </div>
      <router-link to="/orders/create" class="btn btn-primary">+ Create Order</router-link>
    </div>

    <div class="filters">
      <input v-model="searchQuery" type="text" placeholder="Search order #, customer name..." />
      <select v-model="filterStatus">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="paid">Paid</option>
        <option value="delivered">Delivered</option>
      </select>
      <select v-model="filterType">
        <option value="">All Types</option>
        <option value="retail">Retail</option>
        <option value="wholesale">Wholesale</option>
      </select>
      <button @click="fetchOrders" class="btn btn-secondary">Search</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <p>Loading orders...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="fetchOrders" class="btn btn-secondary">Retry</button>
    </div>

    <!-- Orders Table -->
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredOrders.length === 0">
            <td colspan="7" class="no-data">No orders found matching your criteria.</td>
          </tr>
          <tr v-for="order in filteredOrders" :key="order.id">
            <td>#{{ order.id.toString().padStart(4, '0') }}</td>
            <td>{{ order.customer?.name || 'N/A' }}</td>
            <td><span class="badge" :class="order.type">{{ order.type === 'retail' ? 'Retail' : 'Wholesale' }}</span></td>
            <td>₱{{ order.total_amount?.toLocaleString() || '0' }}</td>
            <td>
              <span class="status" :class="order.status">
                {{ order.status }}
              </span>
            </td>
            <td>{{ new Date(order.created_at).toLocaleDateString() }}</td>
            <td>
              <router-link :to="`/orders/${order.id}`" class="btn-small">View</router-link>
              <button @click="editOrder(order)" class="btn-small">Edit</button>
              <button @click="deleteOrder(order)" class="btn-small btn-danger">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click="showDeleteConfirm = false">
      <div class="modal-content small-modal" @click.stop>
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete Order #{{ orderToDelete?.id.toString().padStart(4, '0') }}?</p>
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
import { ref, onMounted, computed } from 'vue';
import api from '../../api';

const orders = ref([]);
const loading = ref(false);
const error = ref('');
const searchQuery = ref('');
const filterStatus = ref('');
const filterType = ref('');
const showDeleteConfirm = ref(false);
const deleting = ref(false);
const orderToDelete = ref(null);

const filteredOrders = computed(() => {
  let filtered = orders.value;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(order =>
      order.id.toString().includes(query) ||
      order.customer?.name?.toLowerCase().includes(query)
    );
  }

  if (filterStatus.value) {
    filtered = filtered.filter(order => order.status === filterStatus.value);
  }

  if (filterType.value) {
    filtered = filtered.filter(order => order.type === filterType.value);
  }

  return filtered;
});

const fetchOrders = async () => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/orders');
    if (response.data.success) {
      orders.value = response.data.data;
    } else {
      error.value = response.data.message || 'Failed to load orders';
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load orders';
  } finally {
    loading.value = false;
  }
};

const editOrder = (order) => {
  // Navigate to edit order page
  // For now, just show an alert
  alert('Edit order functionality will be implemented');
};

const deleteOrder = (order) => {
  orderToDelete.value = order;
  showDeleteConfirm.value = true;
};

const confirmDelete = async () => {
  deleting.value = true;
  try {
    const response = await api.delete(`/orders/${orderToDelete.value.id}`);
    if (response.data.success) {
      await fetchOrders();
      showDeleteConfirm.value = false;
      orderToDelete.value = null;
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
  fetchOrders();
});
</script>

<style scoped>
.orders-container {
  max-width: 1400px;
  margin: 0 auto;
  animation: fadeIn 0.3s ease-in;
  padding: 20px 0;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  padding: 24px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
  font-size: 28px;
  font-weight: 700;
}

.filters {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
  padding: 20px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.filters input,
.filters select {
  padding: 12px 16px;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s ease;
  background-color: #fafbfc;
  min-width: 200px;
}

.filters input:focus,
.filters select:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
  background-color: white;
}

.filters input {
  flex: 1;
  min-width: 280px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  overflow: hidden;
  margin-bottom: 20px;
}

.data-table thead {
  background-color: #fafbfc;
}

.data-table th {
  padding: 18px 20px;
  text-align: left;
  font-weight: 600;
  color: #0a1d37;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #e0e0e0;
}

.data-table td {
  padding: 18px 20px;
  border-bottom: 1px solid #f0f0f0;
  color: #333;
}

.data-table tbody tr:hover {
  background-color: #fafbfc;
}

.badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.badge.retail {
  background-color: #e3f2fd;
  color: #1976d2;
}

.badge.wholesale {
  background-color: #f3e5f5;
  color: #7b1fa2;
}

.status {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.status.pending {
  background-color: #fff3e0;
  color: #f57c00;
}

.status.paid {
  background-color: #e8f5e9;
  color: #388e3c;
}

.status.delivered {
  background-color: #e0f2f1;
  color: #00897b;
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s ease;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover {
  background-color: #d46a1a;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.3);
}

.btn-small {
  padding: 8px 16px;
  background-color: #f8f9fa;
  border: 2px solid #e9ecef;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  margin-right: 8px;
  transition: all 0.3s ease;
  color: #495057;
  text-transform: none;
  letter-spacing: 0;
}

.btn-small:hover {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.15);
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .orders-container {
    padding: 10px 0;
  }

  .header-section {
    flex-direction: column;
    gap: 20px;
    text-align: center;
    padding: 20px;
  }

  .header-section h1 {
    font-size: 24px;
  }

  .filters {
    flex-direction: column;
    gap: 12px;
    padding: 16px;
  }

  .filters input,
  .filters select {
    min-width: auto;
    width: 100%;
  }

  .filters input {
    flex: none;
  }

  .data-table th,
  .data-table td {
    padding: 12px 16px;
  }

  .btn {
    padding: 10px 20px;
    font-size: 13px;
  }
}
</style>
