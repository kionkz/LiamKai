<template>
  <div class="orders-container">
    <div class="header-section">
      <h1>Orders Management</h1>
    </div>

    <div class="actions-bar">
      <button @click="showCreateOrderModal = true" class="btn btn-primary">+ Create Order</button>
      <button @click="openEditModal" class="btn btn-secondary">Edit</button>
      <button @click="openSummaryModal" class="btn btn-secondary">View</button>
      <button @click="openPaymentModal" class="btn btn-secondary">Payment</button>
      <button @click="openDeleteConfirm" class="btn btn-danger">Archive</button>
    </div>

    <div class="filters">
      <input v-model="searchQuery" type="text" placeholder="Search order #, customer name..." />
      <select v-model="filterStatus" data-searchable="off">
        <option value="">All Payment Status</option>
        <option value="pending">Pending</option>
        <option value="partial">Partial</option>
        <option value="paid">Paid</option>
      </select>
      <button @click="fetchOrders" class="btn btn-secondary">Search</button>
    </div>

    <div v-if="loading" class="loading-state">
      <p>Loading orders...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="fetchOrders" class="btn btn-secondary">Retry</button>
    </div>

    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Payment</th>
            <th>Delivery</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredOrders.length === 0">
            <td colspan="7" class="no-data">No orders found matching your criteria.</td>
          </tr>
          <tr
            v-for="order in filteredOrders"
            :key="order.id"
            @click="selectOrder(order)"
            :class="{ 'selected-row': selectedOrderId === order.id }"
          >
            <td>#{{ order.id.toString().padStart(4, '0') }}</td>
            <td>{{ order.customer?.name || 'N/A' }}</td>
            <td>
              <span class="badge" :class="order.type">
                {{ order.type === 'retail' ? 'Retail' : 'Wholesale' }}
              </span>
            </td>
            <td>₱{{ Number(order.total_amount || 0).toLocaleString() }}</td>
            <td><span class="status" :class="order.status">{{ order.status }}</span></td>
            <td><span class="status" :class="order.delivery_status">{{ order.delivery_status || 'pending' }}</span></td>
            <td>{{ new Date(order.created_at).toLocaleDateString() }}</td>
          </tr>
        </tbody>
      </table>

      <div class="pagination" v-if="pagination.last_page > 1">
        <button class="btn btn-secondary" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">
          Previous
        </button>
        <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button class="btn btn-secondary" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">
          Next
        </button>
      </div>
    </div>

    <div v-if="showSummaryModal" class="modal-overlay" @click="showSummaryModal = false">
      <div class="modal-content" @click.stop>
        <h3>Order Summary</h3>
        <div v-if="selectedOrder" class="summary-block">
          <p><strong>Order #:</strong> {{ selectedOrder.id }}</p>
          <p><strong>Customer:</strong> {{ selectedOrder.customer?.name || 'N/A' }}</p>
          <p><strong>Type:</strong> {{ selectedOrder.type }}</p>
          <p><strong>Total:</strong> ₱{{ Number(selectedOrder.total_amount || 0).toFixed(2) }}</p>
          <p><strong>Outstanding:</strong> ₱{{ Number(selectedOrder.outstanding_balance || 0).toFixed(2) }}</p>
          <p><strong>Payment Status:</strong> {{ selectedOrder.status }}</p>
          <p><strong>Delivery Status:</strong> {{ selectedOrder.delivery_status || 'pending' }}</p>
        </div>
        <div class="modal-actions">
          <button @click="showSummaryModal = false" class="btn btn-secondary">Close</button>
        </div>
      </div>
    </div>

    <div v-if="showEditModal" class="modal-overlay" @click="showEditModal = false">
      <div class="modal-content" @click.stop>
        <h3>Edit Selected Order</h3>
        <form @submit.prevent="saveOrderEdit">
          <div class="form-group">
            <label>Order Type</label>
            <SearchableSelect v-model="editForm.order_type" :options="orderTypeOptions" placeholder="Select order type" />
            <p class="rule-note">{{ getOrderTypeRuleMessage(editForm.order_type) }}</p>
          </div>
          <div class="form-group">
            <label>Delivery Address</label>
            <input v-model="editForm.delivery_address" type="text" />
          </div>
          <div class="form-group">
            <label>Delivery Date</label>
            <input v-model="editForm.delivery_date" type="date" />
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea v-model="editForm.notes" rows="3"></textarea>
          </div>
          <div class="modal-actions">
            <button type="button" @click="showEditModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" :disabled="savingEdit" class="btn btn-primary">{{ savingEdit ? 'Saving...' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showPaymentModal" class="modal-overlay" @click="showPaymentModal = false">
      <div class="modal-content" @click.stop>
        <h3>Payment</h3>
        <p v-if="selectedOrder">
          Remaining balance: ₱{{ Number(selectedOrder.outstanding_balance || 0).toFixed(2) }}
        </p>
        <form @submit.prevent="submitPayment">
          <div class="form-group">
            <label>Amount</label>
            <input v-model.number="paymentForm.amount" type="number" step="0.01" min="0.01" required />
          </div>
          <div class="form-group">
            <label>Date</label>
            <input v-model="paymentForm.payment_date" type="date" required />
          </div>
          <div class="form-group">
            <label>Payment Method</label>
            <SearchableSelect v-model="paymentForm.payment_method" :options="paymentMethodOptions" placeholder="Select payment method" />
          </div>
          <div v-if="paymentForm.payment_method === 'check'" class="form-group">
            <label>Deposit Date</label>
            <input v-model="paymentForm.deposit_date" type="date" required />
          </div>
          <div v-if="paymentForm.payment_method === 'check'" class="form-group">
            <label>Check From</label>
            <input v-model="paymentForm.check_from" type="text" placeholder="Issuer name" required />
          </div>
          <p class="auto-ref-note">Reference number is automatically generated for every payment.</p>
          <div class="modal-actions">
            <button type="button" @click="showPaymentModal = false" class="btn btn-secondary">Cancel</button>
            <button type="submit" :disabled="submittingPayment" class="btn btn-primary">
              {{ submittingPayment ? 'Saving...' : 'Save Payment' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showDeleteConfirm" class="modal-overlay" @click="showDeleteConfirm = false">
      <div class="modal-content small-modal" @click.stop>
        <h3>Confirm Archive</h3>
        <p>Archive selected order #{{ selectedOrder?.id?.toString().padStart(4, '0') }}?</p>
        <div class="modal-actions">
          <button @click="showDeleteConfirm = false" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="btn btn-danger">
            {{ deleting ? 'Archiving...' : 'Archive Order' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showCreateOrderModal" class="modal-overlay create-order-overlay" @click="showCreateOrderModal = false">
      <div class="modal-content create-order-modal" @click.stop>
        <CreateOrder :isModal="true" @close="showCreateOrderModal = false" @created="fetchOrders" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api';
import CreateOrder from './CreateOrder.vue';
import { findOrderTypeQuantityViolation, getApiErrorMessage, getOrderTypeRuleMessage } from '../../utils/orderValidation';
import SearchableSelect from '../../components/SearchableSelect.vue';

const orders = ref([]);
const loading = ref(false);
const error = ref('');
const searchQuery = ref('');
const filterStatus = ref('');
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

const selectedOrderId = ref(null);

const showCreateOrderModal = ref(false);
const showSummaryModal = ref(false);
const showEditModal = ref(false);
const showPaymentModal = ref(false);
const showDeleteConfirm = ref(false);

const deleting = ref(false);
const savingEdit = ref(false);
const submittingPayment = ref(false);

const editForm = ref({
  order_type: 'retail',
  delivery_address: '',
  delivery_date: '',
  notes: '',
});

const paymentForm = ref({
  amount: null,
  payment_date: new Date().toISOString().slice(0, 10),
  payment_method: 'cash',
  deposit_date: new Date().toISOString().slice(0, 10),
  check_from: '',
});

const orderTypeOptions = [
  { value: 'retail', label: 'Retail' },
  { value: 'wholesale', label: 'Wholesale' },
];

const paymentMethodOptions = [
  { value: 'cash', label: 'Cash' },
  { value: 'check', label: 'Check' },
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'credit', label: 'Credit' },
];

const filteredOrders = computed(() => {
  let filtered = orders.value;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter((order) =>
      order.id.toString().includes(query) ||
      order.customer?.name?.toLowerCase().includes(query)
    );
  }

  if (filterStatus.value) {
    filtered = filtered.filter((order) => order.status === filterStatus.value);
  }

  return filtered;
});

const selectedOrder = computed(() => {
  return orders.value.find((order) => order.id === selectedOrderId.value) || null;
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

const fetchOrders = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/orders', {
      params: { page, per_page: pagination.value.per_page }
    });
    if (response.data.success) {
      orders.value = response.data.data;
      pagination.value = response.data.pagination || pagination.value;
      if (selectedOrderId.value && !orders.value.some((order) => order.id === selectedOrderId.value)) {
        selectedOrderId.value = null;
      }
    } else {
      error.value = response.data.message || 'Failed to load orders';
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load orders';
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  fetchOrders(page);
};

const openSummaryModal = () => {
  if (!requireSelectedOrder()) return;
  showSummaryModal.value = true;
};

const openEditModal = () => {
  if (!requireSelectedOrder()) return;

  editForm.value = {
    order_type: selectedOrder.value.type || 'retail',
    delivery_address: selectedOrder.value.delivery_address || '',
    delivery_date: selectedOrder.value.delivery_date || '',
    notes: selectedOrder.value.notes || '',
  };

  showEditModal.value = true;
};

const saveOrderEdit = async () => {
  if (!selectedOrder.value) return;

  const quantityViolation = findOrderTypeQuantityViolation(selectedOrder.value.items || [], editForm.value.order_type);
  if (quantityViolation) {
    alert(quantityViolation.message);
    return;
  }

  savingEdit.value = true;
  try {
    const response = await api.put(`/orders/${selectedOrder.value.id}`, editForm.value);
    if (response.data.success) {
      await fetchOrders(pagination.value.current_page);
      showEditModal.value = false;
    } else {
      alert(response.data.message || 'Failed to update order');
    }
  } catch (err) {
    alert(getApiErrorMessage(err, 'Failed to update order'));
  } finally {
    savingEdit.value = false;
  }
};

const openPaymentModal = () => {
  if (!requireSelectedOrder()) return;

  paymentForm.value = {
    amount: Number(selectedOrder.value.outstanding_balance || 0),
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    deposit_date: new Date().toISOString().slice(0, 10),
    check_from: '',
  };

  showPaymentModal.value = true;
};

const submitPayment = async () => {
  if (!selectedOrder.value) return;

  submittingPayment.value = true;
  try {
    const payload = {
      order_id: selectedOrder.value.id,
      amount: Number(paymentForm.value.amount || 0),
      payment_date: paymentForm.value.payment_date,
      payment_method: paymentForm.value.payment_method,
      deposit_date: paymentForm.value.payment_method === 'check' ? paymentForm.value.deposit_date : null,
      check_from: paymentForm.value.payment_method === 'check' ? paymentForm.value.check_from : null,
    };

    const response = await api.post('/payments', payload);
    if (response.data.success) {
      await fetchOrders(pagination.value.current_page);
      showPaymentModal.value = false;
    } else {
      alert(response.data.message || 'Failed to save payment');
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save payment');
  } finally {
    submittingPayment.value = false;
  }
};

const openDeleteConfirm = () => {
  if (!requireSelectedOrder()) return;
  showDeleteConfirm.value = true;
};

const confirmDelete = async () => {
  if (!selectedOrder.value) return;

  deleting.value = true;
  try {
    const response = await api.delete(`/orders/${selectedOrder.value.id}`);
    if (response.data.success) {
      showDeleteConfirm.value = false;
      selectedOrderId.value = null;
      const targetPage = orders.value.length === 1 && pagination.value.current_page > 1
        ? pagination.value.current_page - 1
        : pagination.value.current_page;
      await fetchOrders(targetPage);
    } else {
      alert(response.data.message || 'Failed to archive order');
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to archive order');
  } finally {
    deleting.value = false;
  }
};

onMounted(() => fetchOrders(1));
</script>

<style scoped>
.orders-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px 0;
}

.header-section {
  margin-bottom: 16px;
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
}

.actions-bar {
  position: sticky;
  top: 8px;
  z-index: 20;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 16px;
  padding: 14px;
  background: #ffffff;
  border: 1px solid #e9ecef;
  border-radius: 10px;
}

.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
  padding: 14px;
  background: white;
  border-radius: 10px;
  border: 1px solid #e9ecef;
}

.filters input,
.filters select,
.form-group input,
.form-group select,
.form-group textarea {
  padding: 10px 12px;
  border: 1px solid #d8dde3;
  border-radius: 8px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-top: 1px solid #eef1f4;
}

.page-info {
  font-size: 13px;
  color: #4a5565;
}

.data-table th,
.data-table td {
  padding: 12px;
  border-bottom: 1px solid #eef1f4;
}

.data-table tbody tr {
  cursor: pointer;
}

.data-table tbody tr:hover {
  background: #f7f9fc;
}

.selected-row {
  background: #e8f1ff !important;
  outline: 2px solid #7aa2ff;
}

.badge,
.status {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.badge.retail {
  background: #e3f2fd;
  color: #1976d2;
}

.badge.wholesale {
  background: #f3e5f5;
  color: #7b1fa2;
}

.status.pending,
.status.partial {
  background: #fff3e0;
  color: #ef6c00;
}

.status.paid,
.status.delivered {
  background: #e8f5e9;
  color: #2e7d32;
}

.status.processing {
  background: #e1f5fe;
  color: #0277bd;
}

.status.cancelled {
  background: #fdecea;
  color: #b71c1c;
}

.btn {
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}

.btn-primary {
  background: #e57c2a;
  color: #fff;
}

.btn-secondary {
  background: #f1f3f5;
  color: #25303d;
}

.btn-danger {
  background: #dc3545;
  color: #fff;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(12, 21, 37, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 120;
  padding: 16px;
}

.modal-content {
  background: #fff;
  border-radius: 12px;
  width: min(620px, 100%);
  padding: 20px;
}

.small-modal {
  width: min(420px, 100%);
}

.modal-actions {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 10px;
}

.summary-block p {
  margin: 6px 0;
}

.auto-ref-note {
  margin: 4px 0 0;
  font-size: 12px;
  color: #5f6b7a;
}

.rule-note {
  margin: 6px 0 0;
  font-size: 12px;
  color: #5f6b7a;
}

.create-order-modal {
  width: min(980px, calc(100% - 32px));
  max-height: calc(100vh - 32px);
  padding: 0;
  overflow-y: auto;
}

.create-order-overlay {
  left: 280px;
  width: calc(100vw - 280px);
}
</style>
