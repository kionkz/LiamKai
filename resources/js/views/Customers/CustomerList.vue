<template>
  <div class="customers-container">
    <div class="header-section">
      <div class="header-left">
        <button @click="$router.push('/')" class="btn btn-back">← Back to Dashboard</button>
        <h1>Customer Management</h1>
      </div>
      <button @click="showNewCustomerForm = true" class="btn btn-primary">+ New Customer</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <p>Loading customers...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="fetchCustomers" class="btn btn-secondary">Retry</button>
    </div>

    <!-- Customer Table -->
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Type</th>
            <th>Address</th>
            <th>Credit Limit</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="customers.length === 0">
            <td colspan="6" class="no-data">No customers found. Add your first customer!</td>
          </tr>
          <tr v-for="customer in customers" :key="customer.id">
            <td>{{ customer.name }}</td>
            <td>{{ customer.email || '-' }}</td>
            <td>{{ customer.phone }}</td>
            <td><span class="badge" :class="customer.type">{{ customer.type === 'retail' ? 'Retail' : 'Wholesale' }}</span></td>
            <td>{{ customer.address }}</td>
            <td>{{ customer.credit_limit || '0' }}</td>
            <td>
              <router-link :to="`/customers/${customer.id}`" class="btn-small">View</router-link>
              <button @click="editCustomer(customer)" class="btn-small">Edit</button>
              <button @click="deleteCustomer(customer)" class="btn-small btn-danger">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- New Customer Modal -->
    <div v-if="showNewCustomerForm" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingCustomer ? 'Edit Customer' : 'Add New Customer' }}</h2>
          <button @click="closeModal" class="btn-close">×</button>
        </div>
        <form @submit.prevent="saveCustomer">
          <div class="form-group">
            <label for="name">Customer Name *</label>
            <input v-model="customerForm.name" type="text" id="name" placeholder="Enter customer name" required />
          </div>

          <div class="form-group">
            <label for="email">Email *</label>
            <input v-model="customerForm.email" type="email" id="email" placeholder="Enter email address" required />
          </div>

          <div class="form-group">
            <label for="phone">Phone *</label>
            <input v-model="customerForm.phone" type="tel" id="phone" placeholder="Enter phone number" required />
          </div>

          <div class="form-group">
            <label for="address">Address *</label>
            <input v-model="customerForm.address" type="text" id="address" placeholder="Enter address" required />
          </div>

          <div class="form-group">
            <label for="type">Customer Type *</label>
            <select v-model="customerForm.type" id="type" required>
              <option value="retail">Retail</option>
              <option value="wholesale">Wholesale</option>
            </select>
          </div>

          <div class="form-group">
            <label for="credit_limit">Credit Limit</label>
            <input v-model.number="customerForm.credit_limit" type="number" id="credit_limit" placeholder="0" min="0" step="100"
              :disabled="customerForm.type === 'retail'" :max="customerForm.type === 'wholesale' ? 15000 : 0" />
            <p v-if="customerForm.type === 'retail'" style="font-size:12px;color:#6c757d;margin-top:6px;">Retail customers cannot have a credit limit.</p>
            <p v-else style="font-size:12px;color:#6c757d;margin-top:6px;">Wholesale credit limit capped at ₱15,000.</p>
          </div>

          <div class="modal-actions">
            <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button type="submit" :disabled="saving" class="btn btn-primary">
              {{ saving ? 'Saving...' : (editingCustomer ? 'Update Customer' : 'Add Customer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click="showDeleteConfirm = false">
      <div class="modal-content small-modal" @click.stop>
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete "{{ customerToDelete?.name }}"?</p>
        <p class="warning">This action cannot be undone.</p>
        <div class="modal-actions">
          <button @click="showDeleteConfirm = false" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="btn btn-danger">
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import api from '../../api';

const customers = ref([]);
const loading = ref(false);
const error = ref('');
const showNewCustomerForm = ref(false);
const showDeleteConfirm = ref(false);
const saving = ref(false);
const deleting = ref(false);
const editingCustomer = ref(null);
const customerToDelete = ref(null);

const customerForm = ref({
  name: '',
  email: '',
  phone: '',
  address: '',
  type: 'retail',
  credit_limit: 0
});

// When type changes, ensure credit limit obeys business rules
watch(() => customerForm.value.type, (newType) => {
  if (newType === 'retail') {
    customerForm.value.credit_limit = 0;
  } else if (newType === 'wholesale') {
    if (customerForm.value.credit_limit > 15000) customerForm.value.credit_limit = 15000;
  }
});

const fetchCustomers = async () => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/customers');
    if (response.data.success) {
      customers.value = response.data.data;
    } else {
      error.value = response.data.message || 'Failed to load customers';
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load customers';
  } finally {
    loading.value = false;
  }
};

const saveCustomer = async () => {
  saving.value = true;
  try {
    // Prepare payload according to rules: do not send credit_limit for retail; clamp wholesale
    const payload = { ...customerForm.value };
    if (payload.type === 'retail') {
      delete payload.credit_limit;
    } else {
      if (payload.credit_limit > 15000) payload.credit_limit = 15000;
    }

    let response;
    if (editingCustomer.value) {
      response = await api.put(`/customers/${editingCustomer.value.id}`, payload);
    } else {
      response = await api.post('/customers', payload);
    }

    if (response.data.success) {
      await fetchCustomers();
      closeModal();
    } else {
      alert(response.data.message || 'Failed to save customer');
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save customer');
  } finally {
    saving.value = false;
  }
};

const editCustomer = (customer) => {
  editingCustomer.value = customer;
  // Map backend fields into the form shape
  customerForm.value = {
    name: customer.name || '',
    email: customer.email || '',
    phone: customer.phone || '',
    address: customer.address || '',
    type: customer.type || 'retail',
    credit_limit: customer.credit_limit || 0
  };
  showNewCustomerForm.value = true;
};

const deleteCustomer = (customer) => {
  customerToDelete.value = customer;
  showDeleteConfirm.value = true;
};

const confirmDelete = async () => {
  deleting.value = true;
  try {
    const response = await api.delete(`/customers/${customerToDelete.value.id}`);
    if (response.data.success) {
      await fetchCustomers();
      showDeleteConfirm.value = false;
      customerToDelete.value = null;
    } else {
      alert(response.data.message || 'Failed to delete customer');
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete customer');
  } finally {
    deleting.value = false;
  }
};

const closeModal = () => {
  showNewCustomerForm.value = false;
  editingCustomer.value = null;
  customerForm.value = {
    name: '',
    email: '',
    phone: '',
    address: '',
    type: 'retail',
    credit_limit: 0
  };
};

onMounted(() => {
  fetchCustomers();
});
</script>

<style scoped>
.customers-container {
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

.header-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.btn-back {
  background-color: #6c757d;
  color: white;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  font-size: 14px;
}

.btn-back:hover {
  background-color: #5a6268;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
  font-size: 28px;
  font-weight: 700;
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
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
  background-color: #d16a22;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.3);
}

.btn-secondary {
  background-color: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background-color: #5a6268;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-danger {
  background-color: #dc3545;
  color: white;
}

.btn-danger:hover {
  background-color: #c82333;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}

.btn-small {
  padding: 8px 16px;
  font-size: 12px;
  margin-right: 8px;
  border-radius: 6px;
  text-transform: none;
  letter-spacing: 0;
}

.loading-state, .error-state {
  text-align: center;
  padding: 60px 40px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  margin: 20px 0;
}

.error-state p {
  color: #dc3545;
  margin-bottom: 20px;
  font-size: 16px;
  font-weight: 500;
}

.table-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  overflow: hidden;
  margin-bottom: 20px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 16px 20px;
  text-align: left;
  border-bottom: 1px solid #f0f0f0;
}

.data-table th {
  background-color: #fafbfc;
  font-weight: 600;
  color: #0a1d37;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #e0e0e0;
}

.data-table tr:hover {
  background-color: #fafbfc;
}

.no-data {
  text-align: center;
  color: #6c757d;
  font-style: italic;
  padding: 40px;
  font-size: 16px;
}

.badge {
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

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(2px);
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  animation: modalFadeIn 0.3s ease-out;
}

.small-modal .modal-content {
  max-width: 400px;
}

.modal-header {
  padding: 24px 24px 20px 24px;
  border-bottom: 1px solid #f0f0f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  margin: 0;
  color: #0a1d37;
  font-size: 20px;
  font-weight: 600;
}

.btn-close {
  background: none;
  border: none;
  font-size: 24px;
  color: #999;
  cursor: pointer;
  transition: all 0.3s ease;
  padding: 4px;
  border-radius: 6px;
}

.btn-close:hover {
  color: #333;
  background-color: #f8f9fa;
}

.modal-body {
  padding: 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  background-color: #fafbfc;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
  background-color: white;
}

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 24px;
  padding: 0 24px 24px 24px;
}

.warning {
  color: #dc3545;
  font-weight: 500;
  margin: 12px 0;
  padding: 12px;
  background-color: #fef2f2;
  border-radius: 6px;
  border-left: 4px solid #dc3545;
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

@keyframes modalFadeIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@media (max-width: 768px) {
  .customers-container {
    padding: 10px 0;
  }

  .header-section {
    flex-direction: column;
    gap: 20px;
    text-align: center;
    padding: 20px;
  }

  .header-left {
    justify-content: center;
  }

  .header-section h1 {
    font-size: 24px;
  }

  .btn {
    padding: 10px 20px;
    font-size: 13px;
  }

  .data-table th,
  .data-table td {
    padding: 12px 16px;
  }

  .modal-content {
    width: 95%;
    margin: 20px;
  }

  .modal-header,
  .modal-body,
  .modal-actions {
    padding-left: 20px;
    padding-right: 20px;
  }
}
</style>
