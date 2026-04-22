<template>
  <div class="purchasing-container">
    <div class="header-section">
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
      <div class="order-filters">
        <select v-model="poStatusFilter" class="filter-input" data-searchable="off">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="received">Received</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input
          v-model="poSupplierFilter"
          type="text"
          class="filter-input"
          placeholder="Filter by supplier name..."
        />
        <select v-model="poCategoryFilter" class="filter-input" data-searchable="off">
          <option value="">All Categories</option>
          <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
        </select>
        <select v-model="poSortBy" class="filter-input" data-searchable="off" @change="fetchPurchaseOrders()">
          <option value="created_at">Created Date</option>
          <option value="id">PO #</option>
          <option value="supplier">Supplier</option>
          <option value="total_amount">Total Amount</option>
          <option value="status">Status</option>
          <option value="expected_delivery_date">Expected Date</option>
        </select>
        <select v-model="poSortDirection" class="filter-input" data-searchable="off" @change="fetchPurchaseOrders()">
          <option value="desc">Descending</option>
          <option value="asc">Ascending</option>
        </select>
      </div>
      <div class="selection-toolbar" v-if="activeTab === 'orders'">
        <div class="selection-summary">
          <strong>{{ selectedPurchaseOrder ? `PO #${selectedPurchaseOrder.id}` : 'No purchase order selected' }}</strong>
          <span>{{ selectedPurchaseOrder ? 'Purchase order actions are enabled.' : 'Select a purchase order row to enable actions.' }}</span>
        </div>
        <div class="selection-actions">
          <button @click="viewPO()" class="btn btn-secondary" :disabled="!selectedPurchaseOrder">View</button>
          <button @click="exportPurchaseReceipt()" class="btn btn-secondary" :disabled="!canExportPurchaseReceipt">
            Receipt
          </button>
          <button @click="editPO()" class="btn btn-secondary" :disabled="!selectedPurchaseOrder || selectedPurchaseOrder.status === 'received'">Edit</button>
          <button
            @click="receivePO()"
            class="btn btn-primary"
            :disabled="!selectedPurchaseOrder || selectedPurchaseOrder.status === 'received' || selectedPurchaseOrder.status === 'cancelled'"
          >
            Receive
          </button>
          <select v-model="selectedPOStatus" class="status-select" data-searchable="off" :disabled="!selectedPurchaseOrder || selectedPurchaseOrder?.status === 'received'">
            <option value="pending">Pending</option>
            <option value="received">Received</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <button @click="updateSelectedPOStatus" class="btn btn-secondary" :disabled="!selectedPurchaseOrder || !selectedPOStatus || selectedPurchaseOrder?.status === 'received'">Apply Status</button>
        </div>
      </div>
      <div v-if="loading" class="loading-message">Loading purchase orders...</div>
      <div v-else-if="filteredPurchaseOrders.length === 0" class="empty-message">
        No purchase orders yet. <router-link to="/purchasing/create">Create one now</router-link>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th class="select-column"></th>
            <th>PO #</th>
            <th>Supplier</th>
            <th>Products</th>
            <th>Total Amount</th>
            <th>Status</th>
            <th>Expected Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="po in paginatedPurchaseOrders" :key="po.id" @click="selectPurchaseOrder(po)" :class="{ 'selected-row': selectedPOId === po.id }">
            <td class="select-column" @click.stop>
              <input type="checkbox" :checked="selectedPOId === po.id" @change="selectPurchaseOrder(po)" />
            </td>
            <td class="po-number">#{{ po.id }}</td>
            <td>{{ po.supplier?.name || 'N/A' }}</td>
              <td>
                <div class="product-tags">
                  <span
                    v-for="item in (po.purchase_order_items || []).slice(0, 2)"
                    :key="item.id"
                    class="product-tag"
                  >{{ item.product?.name || 'Unknown' }}</span>
                  <span
                    v-if="(po.purchase_order_items || []).length > 2"
                    class="more-tag"
                  >+{{ (po.purchase_order_items || []).length - 2 }} more</span>
                  <span v-if="!(po.purchase_order_items || []).length" class="more-tag">—</span>
                </div>
              </td>
            <td class="amount">₱{{ parseFloat(po.total_amount).toFixed(2) }}</td>
            <td>
              <span class="status" :class="po.status">
                {{ formatStatus(po.status) }}
              </span>
            </td>
            <td>{{ formatDate(po.expected_delivery_date) }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="filteredPurchaseOrders.length > PAGE_SIZE" class="pagination">
        <button class="page-btn" :disabled="poPage === 1" @click="poPage--">&#8592; Prev</button>
        <span class="page-info">Page {{ poPage }} of {{ poTotalPages }}</span>
        <button class="page-btn" :disabled="poPage === poTotalPages" @click="poPage++">Next &#8594;</button>
      </div>
    </div>
    <div v-if="activeTab === 'suppliers'" class="tab-content">
      <div class="selection-toolbar">
        <div class="selection-summary">
          <strong>{{ selectedSupplier ? selectedSupplier.name : 'No supplier selected' }}</strong>
          <span>{{ selectedSupplier ? 'Supplier actions are enabled.' : 'Select a supplier row to enable actions.' }}</span>
        </div>
        <div class="selection-actions">
          <button @click="editSupplier()" class="btn btn-secondary" :disabled="!selectedSupplier">Edit</button>
          <button @click="deleteSupplier()" class="btn btn-danger" :disabled="!selectedSupplier">Delete</button>
        </div>
      </div>
      <div class="filter-bar">
        <input
          v-model="supplierSearch"
          type="text"
          class="filter-input"
          placeholder="Search by name, contact, email or phone..."
          @keyup.enter="runSupplierSearch"
        />
        <button @click="runSupplierSearch" class="btn btn-secondary">Search</button>
      </div>
      <div v-if="suppliers.length === 0" class="empty-message">
        No suppliers yet. <button @click="showAddSupplierModal = true" class="link-button">Add one</button>
      </div>
      <div v-else-if="filteredSuppliers.length === 0" class="empty-message">
        No suppliers match your search.
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th class="select-column"></th>
            <th>Name</th>
            <th>Contact Person</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Active Orders</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="supplier in paginatedSuppliers" :key="supplier.id" @click="selectSupplier(supplier)" :class="{ 'selected-row': selectedSupplierId === supplier.id }">
            <td class="select-column" @click.stop>
              <input type="checkbox" :checked="selectedSupplierId === supplier.id" @change="selectSupplier(supplier)" />
            </td>
            <td>{{ supplier.name }}</td>
            <td>{{ supplier.contact_person }}</td>
            <td>{{ supplier.email }}</td>
            <td>{{ supplier.phone }}</td>
            <td>{{ supplier.address }}</td>
            <td class="center">{{ getSupplierOrderCount(supplier.id) }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="filteredSuppliers.length > PAGE_SIZE" class="pagination">
        <button class="page-btn" :disabled="supplierPage === 1" @click="supplierPage--">&#8592; Prev</button>
        <span class="page-info">Page {{ supplierPage }} of {{ supplierTotalPages }}</span>
        <button class="page-btn" :disabled="supplierPage === supplierTotalPages" @click="supplierPage++">Next &#8594;</button>
      </div>
    </div>

    <!-- Add/Edit Supplier Modal -->
    <div v-if="showAddSupplierModal" class="modal-overlay" @click="closeSupplierModal">
      <div class="modal supplier-modal" @click.stop>
        <div class="modal-header">
          <h2>{{ editingSupplier ? 'Edit Supplier' : 'Add New Supplier' }}</h2>
          <button @click="closeSupplierModal" class="close-btn">×</button>
        </div>
        <div class="modal-body">
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
            <div class="detail-row" v-if="viewingPO.status === 'received'">
              <label>Received By:</label>
              <span>{{ viewingPO.received_by || 'N/A' }}</span>
            </div>
            <div class="detail-row" v-if="viewingPO.status === 'received'">
              <label>Payment Type:</label>
              <span>{{ latestPurchasePayment(viewingPO) ? formatStatus(latestPurchasePayment(viewingPO).payment_method) : 'N/A' }}</span>
            </div>
            <div class="detail-row" v-if="viewingPO.status === 'received' && latestPurchasePayment(viewingPO)">
              <label>Payment Ref:</label>
              <span>{{ latestPurchasePayment(viewingPO).reference || 'N/A' }}</span>
            </div>
            <div class="detail-row">
              <label>Notes:</label>
              <span>{{ viewingPO.notes || 'N/A' }}</span>
            </div>
          </div>
          <h3>Items</h3>
          <table v-if="viewingPO.purchase_order_items?.length" class="items-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
                              <tr v-for="item in viewingPO.purchase_order_items" :key="item.id">
                <td>{{ item.product?.name || 'N/A' }}</td>
                <td class="center">{{ item.quantity }}</td>
                <td class="amount">₱{{ parseFloat(item.purchase_price ?? item.unit_cost ?? 0).toFixed(2) }}</td>
                <td class="amount">₱{{ ((parseFloat(item.quantity) || 0) * (parseFloat(item.purchase_price ?? item.unit_cost ?? 0))).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-actions">
          <button @click="exportPurchaseReceipt(viewingPO)" class="btn btn-secondary" :disabled="viewingPO.status !== 'received'">Receipt</button>
          <button @click="showViewModal = false" class="btn btn-secondary">Close</button>
        </div>
      </div>
    </div>

    <!-- Warning Modal -->
    <div v-if="showWarningModal" class="modal-overlay" @click="showWarningModal = false">
      <div class="modal-content warning-modal" @click.stop>
        <div class="modal-header warning-header">
          <h3>{{ warningModalConfig.type === 'suppliers' ? 'No Suppliers Found' : 'No Products Found' }}</h3>
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

    <!-- Cancel Reason Modal -->
    <div v-if="showCancelModal" class="modal-overlay" @click="showCancelModal = false">
      <div class="modal-content warning-modal" @click.stop>
        <div class="modal-header warning-header">
          <h3>Cancel Purchase Order</h3>
          <button @click="showCancelModal = false" class="close-btn">×</button>
        </div>
        <div class="modal-body">
          <p class="warning-message">Please provide a reason for cancelling this purchase order.</p>
          <textarea
            v-model="cancelReason"
            rows="3"
            class="cancel-reason-input"
            placeholder="e.g. Supplier unavailable, order no longer needed..."
          ></textarea>
        </div>
        <div class="modal-actions">
          <button @click="showCancelModal = false" class="btn btn-secondary">Go Back</button>
          <button @click="confirmCancel" class="btn btn-danger" :disabled="!cancelReason.trim()">Confirm Cancel</button>
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
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api';
import { exportReceiptPdf } from '../../utils/receiptPdf';

const router = useRouter();

// State
const activeTab = ref('orders');
const loading = ref(false);
const purchaseOrders = ref([]);
const suppliers = ref([]);
const products = ref([]);
const categories = ref([]);
const showAddSupplierModal = ref(false);
const showViewModal = ref(false);
const showWarningModal = ref(false);
const warningModalConfig = ref({ type: '', message: '', actionText: '', action: null });
const showCancelModal = ref(false);
const cancelReason = ref('');
const viewingPO = ref(null);
const editingSupplier = ref(null);
const successMessage = ref('');
const errorMessage = ref('');
const poStatusFilter = ref('');
const poSupplierFilter = ref('');
const poCategoryFilter = ref('');
const poSortBy = ref('created_at');
const poSortDirection = ref('desc');
const supplierSearch = ref('');
const supplierSearchQuery = ref('');
const selectedPOId = ref(null);
const selectedSupplierId = ref(null);
const selectedPOStatus = ref('');
const PAGE_SIZE = 10;
const poPage = ref(1);
const supplierPage = ref(1);

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

const filteredPurchaseOrders = computed(() => {
  return purchaseOrders.value.filter((po) => {
    const statusMatch = !poStatusFilter.value || po.status === poStatusFilter.value;
    const supplierName = (po.supplier?.name || '').toLowerCase();
    const supplierMatch = !poSupplierFilter.value || supplierName.includes(poSupplierFilter.value.toLowerCase());
    const categoryMatch = !poCategoryFilter.value || (po.purchase_order_items || []).some(
      item => String(item.product?.category_id) === poCategoryFilter.value
    );
    return statusMatch && supplierMatch && categoryMatch;
  });
});

const selectedPurchaseOrder = computed(() => {
  return purchaseOrders.value.find((po) => po.id === selectedPOId.value) || null;
});

const canExportPurchaseReceipt = computed(() => selectedPurchaseOrder.value?.status === 'received');

const poTotalPages = computed(() => Math.max(1, Math.ceil(filteredPurchaseOrders.value.length / PAGE_SIZE)));

const paginatedPurchaseOrders = computed(() => {
  const start = (poPage.value - 1) * PAGE_SIZE;
  return filteredPurchaseOrders.value.slice(start, start + PAGE_SIZE);
});

const filteredSuppliers = computed(() => {
  const q = supplierSearchQuery.value.toLowerCase();
  return suppliers.value.filter(s => {
    return !q ||
      (s.name || '').toLowerCase().includes(q) ||
      (s.contact_person || '').toLowerCase().includes(q) ||
      (s.email || '').toLowerCase().includes(q) ||
      (s.phone || '').toLowerCase().includes(q);
  });
});

const supplierTotalPages = computed(() => Math.max(1, Math.ceil(filteredSuppliers.value.length / PAGE_SIZE)));

const paginatedSuppliers = computed(() => {
  const start = (supplierPage.value - 1) * PAGE_SIZE;
  return filteredSuppliers.value.slice(start, start + PAGE_SIZE);
});

const selectedSupplier = computed(() => {
  return suppliers.value.find((supplier) => supplier.id === selectedSupplierId.value) || null;
});

// Reset pages when filters change
watch(filteredPurchaseOrders, () => { poPage.value = 1; });
watch(filteredSuppliers, () => { supplierPage.value = 1; });
watch(poCategoryFilter, () => { poPage.value = 1; });

// Methods
const runSupplierSearch = () => {
  supplierSearchQuery.value = supplierSearch.value;
  supplierPage.value = 1;
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
    const response = await api.get('/purchase-orders', {
      params: {
        per_page: 250,
        sort_by: poSortBy.value,
        sort_direction: poSortDirection.value,
      },
    });
    if (response.data.success) {
      purchaseOrders.value = response.data.data;
      if (selectedPOId.value && !purchaseOrders.value.some((po) => po.id === selectedPOId.value)) {
        selectedPOId.value = null;
        selectedPOStatus.value = '';
      }
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
    const response = await api.get('/suppliers');
    if (response.data.success) {
      suppliers.value = response.data.data;
      if (selectedSupplierId.value && !suppliers.value.some((supplier) => supplier.id === selectedSupplierId.value)) {
        selectedSupplierId.value = null;
      }
    }
  } catch (error) {
    console.error('Error fetching suppliers:', error);
  }
};

const fetchProducts = async () => {
  try {
    const response = await api.get('/products');
    if (response.data.success) {
      products.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching products:', error);
  }
};

const fetchCategories = async () => {
  try {
    const response = await api.get('/categories');
    if (response.data.success) {
      categories.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching categories:', error);
  }
};

const selectPurchaseOrder = (po) => {
  selectedPOId.value = selectedPOId.value === po.id ? null : po.id;
  selectedPOStatus.value = selectedPOId.value ? po.status : '';
};

const selectSupplier = (supplier) => {
  selectedSupplierId.value = selectedSupplierId.value === supplier.id ? null : supplier.id;
};

const viewPO = (po = selectedPurchaseOrder.value) => {
  if (!po) return;
  viewingPO.value = po;
  showViewModal.value = true;
};

const editPO = (po = selectedPurchaseOrder.value) => {
  if (!po) return;
  router.push(`/purchasing/edit/${po.id}`);
};

const receivePO = (po = selectedPurchaseOrder.value) => {
  if (!po) return;
  router.push(`/purchasing/receive/${po.id}`);
};

const formatCurrency = (value) => `₱${Number(value || 0).toFixed(2)}`;

const latestPurchasePayment = (po) => {
  const payments = po?.payments || [];
  if (!payments.length) return null;
  return [...payments].sort((a, b) => {
    const aDate = new Date(a.payment_date || a.created_at || 0).getTime();
    const bDate = new Date(b.payment_date || b.created_at || 0).getTime();
    return bDate - aDate;
  })[0];
};

const exportPurchaseReceipt = async (po = selectedPurchaseOrder.value) => {
  if (!po || po.status !== 'received') return;

  let purchaseOrder = po;
  try {
    const response = await api.get(`/purchase-orders/${po.id}`);
    if (response.data?.success) {
      purchaseOrder = response.data.data;
    }
  } catch {
    errorMessage.value = 'Failed to load purchase order receipt details.';
    setTimeout(() => errorMessage.value = '', 3000);
    return;
  }

  const items = purchaseOrder.purchase_order_items || [];
  const payment = latestPurchasePayment(purchaseOrder);
  exportReceiptPdf({
    title: `Purchase Receipt ${purchaseOrder.order_number || `PO #${purchaseOrder.id}`}`,
    subtitle: 'Supplier Purchase Order Receipt',
    filename: `purchase-receipt-${purchaseOrder.order_number || purchaseOrder.id}.pdf`,
    meta: [
      { label: 'Supplier', value: purchaseOrder.supplier?.name || 'N/A' },
      { label: 'PO Number', value: purchaseOrder.order_number || `#${purchaseOrder.id}` },
      { label: 'Order Date', value: formatDate(purchaseOrder.order_date) },
      { label: 'Received Date', value: formatDate(purchaseOrder.actual_delivery_date) },
      { label: 'Received By', value: purchaseOrder.received_by || 'N/A' },
      { label: 'Payment Type', value: payment ? formatStatus(payment.payment_method) : 'N/A' },
      { label: 'Payment Date', value: payment ? formatDate(payment.payment_date) : formatDate(purchaseOrder.actual_delivery_date) },
      { label: 'Payment Ref', value: payment?.reference || 'N/A' },
    ],
    items: items.map((item) => {
      const quantity = Number(item.quantity || 0);
      const unitPrice = Number(item.purchase_price ?? item.unit_cost ?? 0);
      return {
        name: item.product?.name || `Product #${item.product_id}`,
        qty: `${quantity.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${item.product?.unit_of_measure || 'kg'}`,
        unitPrice,
        amount: quantity * unitPrice,
      };
    }),
    totals: [
      { label: 'Total', value: Number(purchaseOrder.total_amount || 0) },
    ],
  });
};

const deletePO = async (id) => {
  if (!confirm('Are you sure you want to delete this PO?')) return;
  
  try {
    const response = await api.delete(`/purchase-orders/${id}`);
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

const updatePOStatus = async (po, nextStatus) => {
  if (!po || !nextStatus) return;

  if (po.status === 'received') {
    errorMessage.value = 'Cannot change status of a received purchase order.';
    setTimeout(() => errorMessage.value = '', 3000);
    return;
  }

  if (nextStatus === 'received') {
    receivePO(po);
    return;
  }

  try {
    const response = await api.put(`/purchase-orders/${po.id}`, {
      status: nextStatus,
      expected_delivery_date: po.expected_delivery_date
    });
    if (response.data.success) {
      po.status = nextStatus;
      selectedPOStatus.value = nextStatus;
      successMessage.value = `PO status updated to ${formatStatus(nextStatus)}`;
      setTimeout(() => successMessage.value = '', 3000);
    }
  } catch (error) {
    errorMessage.value = 'Failed to update status';
    setTimeout(() => errorMessage.value = '', 3000);
  }
};

const updateSelectedPOStatus = async () => {
  if (!selectedPurchaseOrder.value || !selectedPOStatus.value) return;
  if (selectedPOStatus.value === 'cancelled') {
    cancelReason.value = '';
    showCancelModal.value = true;
    return;
  }
  await updatePOStatus(selectedPurchaseOrder.value, selectedPOStatus.value);
};

const confirmCancel = async () => {
  if (!cancelReason.value.trim()) return;
  const po = selectedPurchaseOrder.value;
  showCancelModal.value = false;
  try {
    const response = await api.put(`/purchase-orders/${po.id}`, {
      status: 'cancelled',
      expected_delivery_date: po.expected_delivery_date,
      notes: `Cancelled: ${cancelReason.value.trim()}`,
    });
    if (response.data.success) {
      po.status = 'cancelled';
      po.notes = `Cancelled: ${cancelReason.value.trim()}`;
      selectedPOStatus.value = 'cancelled';
      successMessage.value = 'Purchase order cancelled.';
      setTimeout(() => successMessage.value = '', 3000);
    }
  } catch {
    errorMessage.value = 'Failed to cancel purchase order.';
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

const editSupplier = (supplier = selectedSupplier.value) => {
  if (!supplier) return;
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
      const response = await api.put(`/suppliers/${editingSupplier.value.id}`, supplierForm.value);
      if (response.data.success) {
        successMessage.value = 'Supplier updated successfully';
        await fetchSuppliers();
        closeSupplierModal();
      }
    } else {
      const response = await api.post('/suppliers', supplierForm.value);
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

const deleteSupplier = async (id = selectedSupplier.value?.id) => {
  if (!id || !confirm('Are you sure you want to delete this supplier?')) return;
  
  try {
    const response = await api.delete(`/suppliers/${id}`);
    if (response.data.success) {
      suppliers.value = suppliers.value.filter(s => s.id !== id);
      if (selectedSupplierId.value === id) {
        selectedSupplierId.value = null;
      }
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
  return String(status || '')
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
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
  fetchCategories();
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

.selection-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  margin-bottom: 14px;
  background: #fff;
  border: 1px solid #e7ebf2;
  border-radius: 10px;
}

.selection-summary {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.selection-summary strong {
  color: #102746;
  font-size: 15px;
}

.selection-summary span {
  color: #607089;
  font-size: 13px;
}

.selection-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.order-filters,
.filter-bar {
  display: flex;
  gap: 10px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}

.filter-input {
  min-width: 220px;
  padding: 9px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  background: #fff;
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

.btn-primary-action {
  background-color: #0a1d37;
  color: white;
  border-color: #0a1d37;
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

.selected-row {
  background: #fff7ed;
}

.select-column {
  width: 52px;
  text-align: center;
}

.select-column input {
  width: 16px;
  height: 16px;
  cursor: pointer;
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

.product-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.product-tag {
  display: inline-block;
  background: #eef3fb;
  color: #1a3a5c;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
  white-space: nowrap;
}

.more-tag {
  display: inline-block;
  background: #f0f0f0;
  color: #888;
  font-size: 11px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 20px;
  white-space: nowrap;
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

.btn:disabled,
.status-select:disabled {
  opacity: 0.55;
  cursor: not-allowed;
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

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 16px;
}

.page-btn {
  padding: 7px 18px;
  border: 1.5px solid #e0e0e0;
  border-radius: 6px;
  background: #fff;
  color: #0a1d37;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.page-btn:hover:not(:disabled) {
  background: #e57c2a;
  color: #fff;
  border-color: #e57c2a;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-info {
  font-size: 13px;
  color: #607089;
  min-width: 110px;
  text-align: center;
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
  overflow: hidden;
}

.supplier-modal .modal-header {
  padding: 16px 22px;
}

.modal-header {
  position: sticky;
  top: 0;
  z-index: 3;
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
  padding: 16px 22px;
  background: #fff;
}

.supplier-modal .form-group:first-child {
  margin-top: 0;
}

.supplier-modal .form-group:last-child {
  margin-bottom: 12px;
}

.supplier-modal .modal-form {
  padding: 0;
  gap: 10px;
}

.supplier-modal .modal-actions {
  padding: 12px 22px 16px;
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
  margin-bottom: 6px;
  font-weight: 600;
  color: #0a1d37;
  font-size: 13px;
  letter-spacing: 0.3px;
}

.form-group input,
.form-group textarea {
  padding: 10px 12px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  font-size: 13px;
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
  margin: 0 0 12px 0;
}

.cancel-reason-input {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  font-family: inherit;
  resize: vertical;
  box-sizing: border-box;
}

.cancel-reason-input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.btn-danger {
  background-color: #dc2626;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.2s;
}

.btn-danger:hover:not(:disabled) {
  background-color: #b91c1c;
}

.btn-danger:disabled {
  background-color: #fca5a5;
  cursor: not-allowed;
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

  .selection-toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .modal {
    width: 95%;
    max-height: 95vh;
  }
}
</style>
