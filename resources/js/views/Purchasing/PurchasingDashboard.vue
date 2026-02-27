<template>
  <div class="purchasing-container">
    <!-- Header with Back Button and Actions -->
    <div class="header-section">
      <div class="header-left">
        <button @click="goToDashboard" class="btn-back">
          ← Back to Dashboard
        </button>
        <h1>Purchase Orders Management</h1>
      </div>
      <div class="header-actions">
        <button @click="showAddSupplierModal = true" class="btn btn-secondary">
          + Add Supplier
        </button>
        <button 
          @click="goToCreatePO" 
          class="btn btn-primary"
        >
          + New PO
        </button>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <p class="stat-label">Total POs</p>
        <p class="stat-value">{{ purchaseOrders.length }}</p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Pending</p>
        <p class="stat-value">{{ pendingCount }}</p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Received Orders</p>
        <p class="stat-value">{{ receivedCount }}</p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Total Value</p>
        <p class="stat-value">₱{{ totalValue.toFixed(2) }}</p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Suppliers</p>
        <p class="stat-value">{{ suppliers.length }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-section">
      <div class="tabs">
        <button 
          :class="{ active: activeTab === 'orders' }" 
          @click="activeTab = 'orders'"
          class="tab-button"
        >
          Purchase Orders
        </button>
        <button 
          :class="{ active: activeTab === 'suppliers' }" 
          @click="activeTab = 'suppliers'"
          class="tab-button"
        >
          Suppliers
        </button>
      </div>
    </div>

    <!-- Purchase Orders Tab -->
    <div v-if="activeTab === 'orders'" class="tab-content">
      <div v-if="loading" class="loading-message">Loading purchase orders...</div>
      <div v-else-if="purchaseOrders.length === 0" class="empty-message">
        No purchase orders yet. <router-link to="/purchasing/create">Create one now</router-link>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>PO #</th>
            <th>Supplier</th>
            <th>Items</th>
            <th>Total Amount</th>
            <th>Status</th>
            <th>Expected Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="po in purchaseOrders" :key="po.id">
            <td class="po-number">#{{ po.id }}</td>
            <td>{{ po.supplier?.name || 'N/A' }}</td>
            <td class="center">{{ po.purchaseOrderItems?.length || 0 }}</td>
            <td class="amount">₱{{ parseFloat(po.total_amount).toFixed(2) }}</td>
            <td>
              <span class="status" :class="po.status">
                {{ formatStatus(po.status) }}
              </span>
            </td>
            <td>{{ formatDate(po.expected_delivery_date) }}</td>
            <td class="actions-cell">
              <button @click="viewPO(po)" class="btn-small">View</button>
              <button @click="editPO(po)" class="btn-small">Edit</button>
              <select v-model="po.status" @change="updatePOStatus(po)" class="status-select">
                <option value="pending">Pending</option>
                <option value="received">Received</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Suppliers Tab -->
    <div v-if="activeTab === 'suppliers'" class="tab-content">
      <div v-if="suppliers.length === 0" class="empty-message">
        No suppliers yet. <button @click="showAddSupplierModal = true" class="link-button">Add one</button>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Contact Person</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Active Orders</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="supplier in suppliers" :key="supplier.id">
            <td>{{ supplier.name }}</td>
            <td>{{ supplier.contact_person }}</td>
            <td>{{ supplier.email }}</td>
            <td>{{ supplier.phone }}</td>
            <td>{{ supplier.address }}</td>
            <td class="center">{{ getSupplierOrderCount(supplier.id) }}</td>
            <td class="actions-cell">
              <button @click="editSupplier(supplier)" class="btn-small">Edit</button>
              <button @click="deleteSupplier(supplier.id)" class="btn-small btn-danger">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add/Edit Supplier Modal -->
    <div v-if="showAddSupplierModal" class="modal-overlay" @click="closeSupplierModal">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h2>{{ editingSupplier ? 'Edit Supplier' : 'Add New Supplier' }}</h2>
          <button @click="closeSupplierModal" class="close-btn">×</button>
        </div>
        <form @submit.prevent="saveSupplier" class="modal-form">
          <div class="form-group">
            <label>Supplier Name *</label>
            <input v-model="supplierForm.name" type="text" required placeholder="Enter supplier name">
          </div>
          <div class="form-group">
            <label>Contact Person *</label>
            <input v-model="supplierForm.contact_person" type="text" required placeholder="Contact person name">
          </div>
          <div class="form-group">
            <label>Email *</label>
            <input v-model="supplierForm.email" type="email" required placeholder="Email address">
          </div>
          <div class="form-group">
            <label>Phone *</label>
            <input v-model="supplierForm.phone" type="text" required placeholder="Phone number">
          </div>
          <div class="form-group">
            <label>Address *</label>
            <textarea v-model="supplierForm.address" required placeholder="Full address"></textarea>
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea v-model="supplierForm.notes" placeholder="Additional notes"></textarea>
          </div>
          <div class="modal-actions">
            <button type="button" @click="closeSupplierModal" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary">{{ editingSupplier ? 'Update' : 'Create' }} Supplier</button>
          </div>
        </form>
      </div>
    </div>

    <!-- View PO Modal -->
    <div v-if="showViewModal && viewingPO" class="modal-overlay" @click="showViewModal = false">
      <div class="modal modal-lg" @click.stop>
        <div class="modal-header">
          <h2>Purchase Order #{{ viewingPO.id }}</h2>
          <button @click="showViewModal = false" class="close-btn">×</button>
        </div>
        <div class="modal-body">
          <div class="po-details">
            <div class="detail-row">
              <label>Supplier:</label>
              <span>{{ viewingPO.supplier?.name }}</span>
            </div>
            <div class="detail-row">
              <label>Status:</label>
              <span class="status" :class="viewingPO.status">{{ formatStatus(viewingPO.status) }}</span>
            </div>
            <div class="detail-row">
              <label>Total Amount:</label>
              <span class="amount">₱{{ parseFloat(viewingPO.total_amount).toFixed(2) }}</span>
            </div>
            <div class="detail-row">
              <label>Expected Delivery:</label>
              <span>{{ formatDate(viewingPO.expected_delivery_date) }}</span>
            </div>
            <div class="detail-row">
              <label>Notes:</label>
              <span>{{ viewingPO.notes || 'N/A' }}</span>
            </div>
          </div>
          <h3>Items</h3>
          <table v-if="viewingPO.purchaseOrderItems?.length" class="items-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in viewingPO.purchaseOrderItems" :key="item.id">
                <td>{{ item.product?.name || 'N/A' }}</td>
                <td class="center">{{ item.quantity }}</td>
                <td class="amount">₱{{ parseFloat(item.unit_cost).toFixed(2) }}</td>
                <td class="amount">₱{{ (item.quantity * item.unit_cost).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-actions">
          <button @click="showViewModal = false" class="btn btn-secondary">Close</button>
        </div>
      </div>
    </div>

    <!-- Warning Modal -->
    <div v-if="showWarningModal" class="modal-overlay" @click="showWarningModal = false">
      <div class="modal-content warning-modal" @click.stop>
        <div class="modal-header warning-header">
          <h3>⚠️ {{ warningModalConfig.type === 'suppliers' ? 'No Suppliers Found' : 'No Products Found' }}</h3>
          <button @click="showWarningModal = false" class="close-btn">×</button>
        </div>
        <div class="modal-body">
          <p class="warning-message">{{ warningModalConfig.message }}</p>
        </div>
        <div class="modal-actions">
          <button @click="showWarningModal = false" class="btn btn-secondary">Cancel</button>
          <button @click="warningModalConfig.action" class="btn btn-primary">
            {{ warningModalConfig.actionText }} →
          </button>
        </div>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <div v-if="successMessage" class="alert alert-success">
      {{ successMessage }}
      <button @click="successMessage = ''" class="close-alert">×</button>
    </div>
    <div v-if="errorMessage" class="alert alert-error">
      {{ errorMessage }}
      <button @click="errorMessage = ''" class="close-alert">×</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();

// State
const activeTab = ref('orders');
const loading = ref(false);
const purchaseOrders = ref([]);
const suppliers = ref([]);
const products = ref([]);
const showAddSupplierModal = ref(false);
const showViewModal = ref(false);
const showWarningModal = ref(false);
const warningModalConfig = ref({ type: '', message: '', actionText: '', action: null });
const viewingPO = ref(null);
const editingSupplier = ref(null);
const successMessage = ref('');
const errorMessage = ref('');

const supplierForm = ref({
  name: '',
  contact_person: '',
  email: '',
  phone: '',
  address: '',
  notes: '',
});

// Computed Properties
const pendingCount = computed(() => {
  return purchaseOrders.value.filter(po => po.status === 'pending').length;
});

const receivedCount = computed(() => {
  return purchaseOrders.value.filter(po => po.status === 'received').length;
});

const totalValue = computed(() => {
  return purchaseOrders.value.reduce((sum, po) => sum + parseFloat(po.total_amount), 0);
});

// Methods
const goToDashboard = () => {
  router.push('/dashboard');
};

const goToCreatePO = () => {
  if (suppliers.value.length === 0) {
    warningModalConfig.value = {
      type: 'suppliers',
      message: 'You need to add at least one supplier before you can create a purchase order.',
      actionText: 'Add Supplier Now',
      action: () => {
        showWarningModal.value = false;
        showAddSupplierModal.value = true;
      }
    };
    showWarningModal.value = true;
    return;
  }
  if (products.value.length === 0) {
    warningModalConfig.value = {
      type: 'products',
      message: 'You need to add products to your inventory before you can create a purchase order.',
      actionText: 'Go to Products',
      action: () => {
        showWarningModal.value = false;
        router.push('/products');
      }
    };
    showWarningModal.value = true;
    return;
  }
  router.push('/purchasing/create');
};

const fetchPurchaseOrders = async () => {
  try {
    loading.value = true;
    const response = await axios.get('/api/purchase-orders');
    if (response.data.success) {
      purchaseOrders.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching purchase orders:', error);
    errorMessage.value = 'Failed to fetch purchase orders';
  } finally {
    loading.value = false;
  }
};

const fetchSuppliers = async () => {
  try {
    const response = await axios.get('/api/suppliers');
    if (response.data.success) {
      suppliers.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching suppliers:', error);
  }
};

const fetchProducts = async () => {
  try {
    const response = await axios.get('/api/products');
    if (response.data.success) {
      products.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching products:', error);
  }
};

const viewPO = (po) => {
  viewingPO.value = po;
  showViewModal.value = true;
};

const editPO = (po) => {
  router.push(`/purchasing/edit/${po.id}`);
};

const deletePO = async (id) => {
  if (!confirm('Are you sure you want to delete this PO?')) return;
  
  try {
    const response = await axios.delete(`/api/purchase-orders/${id}`);
    if (response.data.success) {
      purchaseOrders.value = purchaseOrders.value.filter(po => po.id !== id);
      successMessage.value = 'Purchase order deleted successfully';
      setTimeout(() => successMessage.value = '', 3000);
    }
  } catch (error) {
    errorMessage.value = 'Failed to delete purchase order';
    setTimeout(() => errorMessage.value = '', 3000);
  }
};

const updatePOStatus = async (po) => {
  try {
    const response = await axios.put(`/api/purchase-orders/${po.id}`, {
      status: po.status,
      expected_delivery_date: po.expected_delivery_date
    });
    if (response.data.success) {
      successMessage.value = `PO status updated to ${po.status}`;
      setTimeout(() => successMessage.value = '', 3000);
    }
  } catch (error) {
    errorMessage.value = 'Failed to update status';
    setTimeout(() => errorMessage.value = '', 3000);
  }
};

const openAddSupplierModal = () => {
  editingSupplier.value = null;
  supplierForm.value = {
    name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    notes: '',
  };
  showAddSupplierModal.value = true;
};

const editSupplier = (supplier) => {
  editingSupplier.value = supplier;
  supplierForm.value = { ...supplier };
  showAddSupplierModal.value = true;
};

const closeSupplierModal = () => {
  showAddSupplierModal.value = false;
  editingSupplier.value = null;
  supplierForm.value = {
    name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    notes: '',
  };
};

const saveSupplier = async () => {
  try {
    if (editingSupplier.value) {
      const response = await axios.put(`/api/suppliers/${editingSupplier.value.id}`, supplierForm.value);
      if (response.data.success) {
        successMessage.value = 'Supplier updated successfully';
        await fetchSuppliers();
        closeSupplierModal();
      }
    } else {
      const response = await axios.post('/api/suppliers', supplierForm.value);
      if (response.data.success) {
        successMessage.value = 'Supplier created successfully';
        await fetchSuppliers();
        closeSupplierModal();
      }
    }
    setTimeout(() => successMessage.value = '', 3000);
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to save supplier';
    setTimeout(() => errorMessage.value = '', 3000);
  }
};

const deleteSupplier = async (id) => {
  if (!confirm('Are you sure you want to delete this supplier?')) return;
  
  try {
    const response = await axios.delete(`/api/suppliers/${id}`);
    if (response.data.success) {
      suppliers.value = suppliers.value.filter(s => s.id !== id);
      successMessage.value = 'Supplier deleted successfully';
      setTimeout(() => successMessage.value = '', 3000);
    }
  } catch (error) {
    errorMessage.value = 'Failed to delete supplier';
    setTimeout(() => errorMessage.value = '', 3000);
  }
};

const getSupplierOrderCount = (supplierId) => {
  return purchaseOrders.value.filter(po => po.supplier_id === supplierId).length;
};

const formatStatus = (status) => {
  return status.charAt(0).toUpperCase() + status.slice(1);
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

// Lifecycle
onMounted(() => {
  fetchPurchaseOrders();
  fetchSuppliers();
  fetchProducts();
});
</script>

<style scoped>
.purchasing-container {
  animation: fadeIn 0.3s ease-in;
}

/* Header Section */
.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  gap: 20px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 15px;
  flex: 1;
}

.header-left h1 {
  margin: 0;
  color: #0a1d37;
  font-size: 30px;
  letter-spacing: -0.4px;
}

.btn-back {
  padding: 8px 16px;
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
  color: #0a1d37;
  text-decoration: none;
}

.btn-back:hover {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
}

.header-actions {
  display: flex;
  gap: 10px;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 25px;
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  border-left: 4px solid #e57c2a;
}

.stat-label {
  margin: 0;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  font-weight: 600;
}

.stat-value {
  margin: 8px 0 0 0;
  font-size: 24px;
  font-weight: 700;
  color: #0a1d37;
}

/* Tabs */
.tabs-section {
  margin-bottom: 20px;
  border-bottom: 2px solid #e0e0e0;
}

.tabs {
  display: flex;
  gap: 0;
}

.tab-button {
  padding: 12px 20px;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  cursor: pointer;
  font-weight: 500;
  color: #666;
  transition: all 0.3s;
  font-size: 14px;
}

.tab-button:hover {
  color: #e57c2a;
}

.tab-button.active {
  color: #e57c2a;
  border-bottom-color: #e57c2a;
}

.tab-content {
  animation: fadeIn 0.3s ease-in;
}

/* Buttons */
.btn {
  padding: 12px 28px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s ease;
  font-size: 14px;
  letter-spacing: 0.2px;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.15);
}

.btn-primary:hover {
  background-color: #d46a1a;
  box-shadow: 0 6px 16px rgba(229, 124, 42, 0.25);
  transform: translateY(-1px);
}

.btn-secondary {
  background-color: #ffffff;
  color: #0a1d37;
  border: 1.5px solid #e0e0e0;
}

.btn-secondary:hover {
  background-color: #f8f8f8;
  border-color: #d0d0d0;
}

.btn-small {
  padding: 6px 12px;
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  transition: all 0.3s;
  margin-right: 5px;
}

.btn-small:hover {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
}

.btn-danger {
  background-color: #ff6b6b;
  color: white;
  border-color: #ff6b6b;
}

.btn-danger:hover {
  background-color: #ff5252;
  border-color: #ff5252;
}

.link-button {
  background: none;
  border: none;
  color: #e57c2a;
  cursor: pointer;
  text-decoration: underline;
  font-weight: 500;
}

.link-button:hover {
  color: #d46a1a;
}

/* Tables */
.data-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.data-table thead {
  background-color: #f9f9f9;
}

.data-table th {
  padding: 15px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.data-table td {
  padding: 15px;
  border-bottom: 1px solid #e0e0e0;
}

.data-table tbody tr:hover {
  background-color: #f9f9f9;
}

.po-number {
  font-weight: 600;
  color: #0a1d37;
}

.amount {
  font-weight: 600;
  color: #e57c2a;
}

.center {
  text-align: center;
}

.actions-cell {
  display: flex;
  gap: 5px;
  align-items: center;
}

.status-select {
  padding: 8px 14px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  background-color: white;
  color: #0a1d37;
  transition: all 0.3s ease;
  outline: none;
  min-width: 120px;
}

.status-select:hover {
  border-color: #e57c2a;
  box-shadow: 0 2px 8px rgba(229, 124, 42, 0.1);
}

.status-select:focus {
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

/* Status Badges */
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

.status.confirmed {
  background-color: #e3f2fd;
  color: #1976d2;
}

.status.received {
  background-color: #e8f5e9;
  color: #388e3c;
}

.status.cancelled {
  background-color: #ffebee;
  color: #d32f2f;
}

.status.shipped {
  background-color: #e3f2fd;
  color: #1976d2;
}

/* Empty/Loading States */
.empty-message {
  text-align: center;
  padding: 40px;
  background: white;
  border-radius: 8px;
  color: #666;
  font-size: 16px;
}

.loading-message {
  text-align: center;
  padding: 40px;
  background: white;
  border-radius: 8px;
  color: #666;
  font-size: 16px;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  overflow-y: auto;
}

.modal {
  background: white;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
  width: 90%;
  max-width: 650px;
  max-height: 90vh;
  overflow-y: auto;
  animation: modalSlideIn 0.3s ease-out;
}

.supplier-modal {
  max-width: 700px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 32px 32px;
  background: linear-gradient(135deg, #0a1d37 0%, #1a3d5c 100%);
  color: white;
  border-bottom: none;
}

.modal-header h2 {
  margin: 0;
  color: white;
  font-size: 20px;
  font-weight: 700;
}



.close-btn {
  background: none;
  border: none;
  font-size: 28px;
  cursor: pointer;
  color: white;
  opacity: 0.8;
  transition: opacity 0.3s;
  line-height: 1;
}

.close-btn:hover {
  opacity: 1;
}

.modal-body {
  padding: 50px;
}

.supplier-modal .modal-body {
  padding: 44px 48px;
  background: #fff;
}

.supplier-modal .form-group:first-child {
  margin-top: 0;
}

.supplier-modal .form-group:last-child {
  margin-bottom: 12px;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 40px;
  padding: 0;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  margin-bottom: 12px;
  font-weight: 600;
  color: #0a1d37;
  font-size: 14px;
  letter-spacing: 0.3px;
}

.form-group input,
.form-group textarea {
  padding: 14px 18px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  background-color: #fafafa;
  line-height: 1.5;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  background-color: white;
  border-color: #e57c2a;
  box-shadow: 0 0 0 4px rgba(229, 124, 42, 0.08);
}

.modal-actions {
  display: flex;
  gap: 16px;
  justify-content: flex-end;
  padding: 32px 44px;
  border-top: 1px solid #f0f0f0;
  background-color: #fafafa;
}

.po-details {
  background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #e0e0e0;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-row label {
  font-weight: 600;
  color: #0a1d37;
  min-width: 150px;
}

.detail-row span {
  color: #666;
  text-align: right;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}

.items-table thead {
  background-color: #f5f5f5;
}

.items-table th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  color: #0a1d37;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.items-table td {
  padding: 12px;
  border-bottom: 1px solid #e0e0e0;
}

.modal-body h3 {
  margin-top: 25px;
  margin-bottom: 15px;
  color: #0a1d37;
  font-size: 16px;
  font-weight: 700;
}

/* Alerts */
.alert {
  padding: 15px 20px;
  border-radius: 6px;
  margin-bottom: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  animation: slideIn 0.3s ease-out;
}

.alert-success {
  background-color: #e8f5e9;
  color: #388e3c;
  border: 1px solid #c8e6c9;
}

.alert-error {
  background-color: #ffebee;
  color: #d32f2f;
  border: 1px solid #ffcdd2;
}

.alert-warning {
  background-color: #fff8e1;
  color: #f57f17;
  border: 1px solid #ffeb3b;
}

.alert-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.alert-content strong {
  font-weight: 600;
  font-size: 15px;
}

.alert-content p {
  margin: 0;
  font-size: 14px;
  opacity: 0.9;
}

.btn-link {
  background: none;
  border: none;
  color: inherit;
  cursor: pointer;
  font-weight: 600;
  text-decoration: underline;
  padding: 0;
  font-size: 14px;
  align-self: flex-start;
  margin-top: 5px;
}

.btn-link:hover {
  opacity: 0.7;
}

.close-alert {
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
  color: inherit;
  margin-left: 20px;
}

.close-alert:hover {
  opacity: 0.7;
}

.close-alert:hover {
  opacity: 0.7;
}

/* Warning Modal */
.warning-modal {
  max-width: 500px;
}

.warning-header {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
}

.warning-message {
  font-size: 16px;
  line-height: 1.6;
  color: #4a5568;
  margin: 0;
}

/* Animations */
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

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

/* Responsive */
@media (max-width: 768px) {
  .header-section {
    flex-direction: column;
    align-items: flex-start;
  }

  .header-actions {
    width: 100%;
    gap: 10px;
  }

  .data-table {
    font-size: 12px;
  }

  .data-table th,
  .data-table td {
    padding: 10px;
  }

  .actions-cell {
    flex-direction: column;
    gap: 3px;
  }

  .btn-small {
    width: 100%;
    margin-right: 0;
  }

  .modal {
    width: 95%;
    max-height: 95vh;
  }
}
</style>
