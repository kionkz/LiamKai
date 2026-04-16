<template>
  <div class="customer-profile">
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <p>Loading customer details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="fetchCustomer" class="btn btn-secondary">Retry</button>
    </div>

    <!-- Customer Profile -->
    <div v-else-if="customer" class="card">
      <div class="profile-header">
        <div class="header-left">
          <button @click="$router.push('/customers')" class="btn btn-back">← Back to Customers</button>
          <h1>{{ customer.name }}</h1>
        </div>
        <div class="header-actions">
          <button @click="showEditForm = true" class="btn btn-primary">Edit Customer</button>
          <button @click="createOrder" class="btn btn-secondary">Create Order</button>
        </div>
      </div>

      <div class="profile-info">
        <div class="info-section">
          <h3>Contact Information</h3>
          <div class="info-row">
            <span class="label">Name:</span>
            <span class="value">{{ customer.name }}</span>
          </div>
          <div class="info-row">
            <span class="label">Phone:</span>
            <span class="value">{{ customer.phone }}</span>
          </div>
          <div class="info-row">
            <span class="label">Email:</span>
            <span class="value">{{ customer.email || '-' }}</span>
          </div>
          <div class="info-row">
            <span class="label">Address:</span>
            <span class="value">{{ customer.address }}</span>
          </div>
        </div>

        <div class="info-section">
          <h3>Account Information</h3>
          <div class="info-row">
            <span class="label">Type:</span>
            <span class="badge" :class="customer.type">{{ customer.type === 'retail' ? 'Retail' : 'Wholesale' }}</span>
          </div>
          <div class="info-row">
            <span class="label">Credit Limit:</span>
            <span class="value">₱{{ customer.credit_limit?.toLocaleString() || '0' }}</span>
          </div>
          <div class="info-row">
            <span class="label">Current Balance:</span>
            <span class="value" style="color: #e57c2a;">₱{{ currentBalance.toLocaleString() }}</span>
          </div>
          <div class="info-row">
            <span class="label">Available Credit:</span>
            <span class="value" :style="{ color: availableCredit >= 0 ? '#388e3c' : '#dc3545' }">
              ₱{{ availableCredit.toLocaleString() }}
            </span>
          </div>
        </div>
      </div>

      <div class="orders-history">
        <div class="orders-header">
          <h3>Order History</h3>
          <div class="orders-actions">
            <button @click="viewSelectedOrder" class="btn btn-secondary btn-small-action">View</button>
            <button @click="openEditOrderModal" class="btn btn-secondary btn-small-action">Edit</button>
          </div>
        </div>
        <div v-if="customer.orders && customer.orders.length === 0" class="no-orders">
          <p>No orders found for this customer.</p>
        </div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Date</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="order in customer.orders"
              :key="order.id"
              @click="selectOrder(order)"
              :class="{ 'selected-row': selectedOrderId === order.id }"
            >
              <td>#{{ order.id.toString().padStart(4, '0') }}</td>
              <td>{{ new Date(order.created_at).toLocaleDateString() }}</td>
              <td>₱{{ order.total_amount?.toLocaleString() || '0' }}</td>
              <td><span class="status" :class="order.payment_status || order.status">{{ order.payment_status || order.status || 'pending' }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showEditOrderModal" class="modal-overlay" @click="showEditOrderModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Edit Customer Order</h2>
          <button @click="showEditOrderModal = false" class="btn-close">×</button>
        </div>
        <form @submit.prevent="saveOrderEdit">
          <div class="form-group">
            <label>Order Type</label>
            <SearchableSelect v-model="orderEditForm.order_type" :options="orderTypeOptions" placeholder="Select order type" />
            <p class="field-note">{{ getOrderTypeRuleMessage(orderEditForm.order_type) }}</p>
          </div>
          <div class="form-group">
            <label>Delivery Address</label>
            <input v-model="orderEditForm.delivery_address" type="text" />
          </div>
          <div class="form-group">
            <label>Delivery Date</label>
            <input v-model="orderEditForm.delivery_date" type="date" />
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea v-model="orderEditForm.notes" rows="3"></textarea>
          </div>
          <div class="modal-actions">
            <button type="button" @click="showEditOrderModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" :disabled="savingOrderEdit" class="btn btn-primary">
              {{ savingOrderEdit ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Customer Modal -->
    <div v-if="showEditForm" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Edit Customer</h2>
          <button @click="closeModal" class="btn-close">×</button>
        </div>
        <form @submit.prevent="updateCustomer">
          <div class="form-group">
            <label for="name">Customer Name *</label>
            <input v-model="editForm.name" type="text" id="name" required />
          </div>

          <div class="form-group">
            <label for="email">Email *</label>
            <input v-model="editForm.email" type="email" id="email" required />
          </div>

          <div class="form-group">
            <label for="phone">Contact Number *</label>
            <input v-model="editForm.phone" type="tel" id="phone" placeholder="+639XXXXXXXXX" required @blur="normalizePhoneField" />
          </div>

          <div class="form-group">
            <label for="address">Address *</label>
            <input v-model="editForm.address" type="text" id="address" required />
          </div>

          <div class="form-group">
            <label for="type">Customer Type *</label>
            <SearchableSelect v-model="editForm.type" :options="orderTypeOptions" placeholder="Select customer type" />
          </div>

          <div class="form-group">
            <label for="credit_limit">Credit Limit (₱)</label>
            <input v-model.number="editForm.credit_limit" type="number" id="credit_limit" min="0" step="100"
              :disabled="editForm.type === 'retail'" :max="editForm.type === 'wholesale' ? 15000 : 0" />
            <p v-if="editForm.type === 'retail'" style="font-size:12px;color:#6c757d;margin-top:6px;">Retail customers cannot have a credit limit.</p>
            <p v-else style="font-size:12px;color:#6c757d;margin-top:6px;">Wholesale credit limit capped at ₱15,000.</p>
          </div>

          <div class="modal-actions">
            <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button type="submit" :disabled="saving" class="btn btn-primary">
              {{ saving ? 'Updating...' : 'Update Customer' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api';
import { findOrderTypeQuantityViolation, getApiErrorMessage, getOrderTypeRuleMessage } from '../../utils/orderValidation';
import SearchableSelect from '../../components/SearchableSelect.vue';

const route = useRoute();
const router = useRouter();
const customer = ref(null);
const loading = ref(false);
const error = ref('');
const showEditForm = ref(false);
const saving = ref(false);
const selectedOrderId = ref(null);
const showEditOrderModal = ref(false);
const savingOrderEdit = ref(false);

const orderEditForm = ref({
  order_type: 'retail',
  delivery_address: '',
  delivery_date: '',
  notes: '',
});

const editForm = ref({
  name: '',
  email: '',
  phone: '',
  address: '',
  type: 'retail',
  credit_limit: 0
});

const orderTypeOptions = [
  { value: 'retail', label: 'Retail' },
  { value: 'wholesale', label: 'Wholesale' },
];

const normalizePhPhone = (value) => {
  const digits = String(value || '').replace(/\D/g, '');
  if (digits.startsWith('09') && digits.length === 11) return `+63${digits.slice(1)}`;
  if (digits.startsWith('9') && digits.length === 10) return `+63${digits}`;
  if (digits.startsWith('63') && digits.length === 12) return `+${digits}`;
  return value;
};

const normalizePhoneField = () => {
  editForm.value.phone = normalizePhPhone(editForm.value.phone);
};

// Keep credit_limit in sync with type selection
watch(() => editForm.value.type, (newType) => {
  if (newType === 'retail') {
    editForm.value.credit_limit = 0;
  } else if (newType === 'wholesale') {
    if (editForm.value.credit_limit > 15000) editForm.value.credit_limit = 15000;
  }
});

const currentBalance = computed(() => {
  if (!customer.value?.orders) return 0;
  return customer.value.orders
    .filter(order => order.status === 'pending' || order.status === 'paid')
    .reduce((sum, order) => sum + (order.total_amount || 0), 0);
});

const availableCredit = computed(() => {
  return (customer.value?.credit_limit || 0) - currentBalance.value;
});

const selectedOrder = computed(() => {
  return customer.value?.orders?.find((order) => order.id === selectedOrderId.value) || null;
});

const selectOrder = (order) => {
  selectedOrderId.value = order.id;
};

const requireSelectedOrder = () => {
  if (!selectedOrder.value) {
    alert('Please select an order row first.');
    return false;
  }
  return true;
};

const viewSelectedOrder = () => {
  if (!requireSelectedOrder()) return;
  router.push(`/orders/${selectedOrder.value.id}`);
};

const openEditOrderModal = () => {
  if (!requireSelectedOrder()) return;

  orderEditForm.value = {
    order_type: selectedOrder.value.order_type || selectedOrder.value.type || 'retail',
    delivery_address: selectedOrder.value.delivery_address || '',
    delivery_date: selectedOrder.value.delivery_date || '',
    notes: selectedOrder.value.notes || '',
  };

  showEditOrderModal.value = true;
};

const saveOrderEdit = async () => {
  if (!selectedOrder.value) return;

  const quantityViolation = findOrderTypeQuantityViolation(selectedOrder.value.order_items || selectedOrder.value.items || [], orderEditForm.value.order_type);
  if (quantityViolation) {
    alert(quantityViolation.message);
    return;
  }

  savingOrderEdit.value = true;
  try {
    const response = await api.put(`/orders/${selectedOrder.value.id}`, orderEditForm.value);
    if (response.data.success) {
      showEditOrderModal.value = false;
      await fetchCustomer();
    } else {
      alert(response.data.message || 'Failed to update order');
    }
  } catch (err) {
    alert(getApiErrorMessage(err, 'Failed to update order'));
  } finally {
    savingOrderEdit.value = false;
  }
};

const fetchCustomer = async () => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get(`/customers/${route.params.id}`);
    if (response.data.success) {
      customer.value = response.data.data;
      if (selectedOrderId.value && !customer.value.orders?.some((order) => order.id === selectedOrderId.value)) {
        selectedOrderId.value = null;
      }
    } else {
      error.value = response.data.message || 'Failed to load customer';
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load customer';
  } finally {
    loading.value = false;
  }
};

const updateCustomer = async () => {
  saving.value = true;
  try {
    normalizePhoneField();

    const payload = { ...editForm.value };
    if (payload.type === 'retail') delete payload.credit_limit;
    else if (payload.credit_limit > 15000) payload.credit_limit = 15000;

    const response = await api.put(`/customers/${customer.value.id}`, payload);
    if (response.data.success) {
      customer.value = { ...customer.value, ...editForm.value };
      closeModal();
    } else {
      alert(response.data.message || 'Failed to update customer');
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to update customer');
  } finally {
    saving.value = false;
  }
};

const createOrder = () => {
  // Navigate to create order page with customer pre-selected
  // For now, just navigate to orders create page
  route.params.customerId = customer.value.id;
  // This would need to be implemented in the CreateOrder component
  alert('Create order functionality will be implemented in the Orders section');
};

const closeModal = () => {
  showEditForm.value = false;
  if (customer.value) {
    editForm.value = {
      name: customer.value.name || '',
      email: customer.value.email || '',
      phone: customer.value.phone || '',
      address: customer.value.address || '',
      type: customer.value.type || 'retail',
      credit_limit: customer.value.credit_limit || 0
    };
  }
};

onMounted(() => {
  fetchCustomer();
});
</script>

<style scoped>
.customer-profile {
  max-width: 900px;
  margin: 0 auto;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 30px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.profile-header h1 {
  margin: 0;
  color: #0a1d37;
}

.btn-small {
  padding: 8px 12px;
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.field-note {
  margin: 6px 0 0;
  font-size: 12px;
  color: #6c757d;
}

.profile-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
  margin-bottom: 30px;
  padding-bottom: 30px;
  border-bottom: 1px solid #e0e0e0;
}

.info-section h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
}

.info-row .label {
  color: #666;
  font-weight: 500;
}

.info-row .value {
  color: #0a1d37;
  font-weight: 600;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.badge.retail {
  background-color: #e3f2fd;
  color: #1976d2;
}

.orders-history {
  margin-bottom: 30px;
}

.orders-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.orders-actions {
  display: flex;
  gap: 8px;
}

.btn-small-action {
  padding: 8px 12px;
  font-size: 12px;
}

.orders-history h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background-color: #f9f9f9;
  border-radius: 6px;
  overflow: hidden;
}

.data-table th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.data-table td {
  padding: 12px;
  border-bottom: 1px solid #e0e0e0;
}

.data-table tbody tr {
  cursor: pointer;
}

.data-table tbody tr:hover {
  background-color: #f5f8fd;
}

.selected-row {
  background: #e8f1ff !important;
  outline: 2px solid #7aa2ff;
}

.status {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
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

.profile-actions {
  display: flex;
  gap: 10px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover {
  background-color: #d46a1a;
}

.btn-secondary {
  background-color: #f0f0f0;
  color: #333;
  border: 1px solid #ddd;
}

.btn-secondary:hover {
  background-color: #e0e0e0;
}

@media (max-width: 768px) {
  .profile-info {
    grid-template-columns: 1fr;
  }
}
</style>
