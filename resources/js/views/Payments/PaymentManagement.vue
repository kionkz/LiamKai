<template>
  <div class="payments-page">
    <div class="stat-grid">
      <article
        v-for="card in statCards"
        :key="card.key"
        class="stat-card"
        :class="{ active: statusFilter === card.key }"
        @click="setStatusFilter(card.key)"
      >
        <p class="stat-label">{{ card.label }}</p>
        <p class="stat-value">{{ card.value }}</p>
      </article>
    </div>

    <div class="toolbar">
      <div class="toolbar-field search-field">
        <label for="payment-search">Search</label>
        <input
          id="payment-search"
          v-model="searchQuery"
          type="text"
          placeholder="Search by order ID or customer"
          @keyup.enter="applyFilters"
        />
      </div>
      <div class="toolbar-field">
        <label for="payment-status">Category</label>
        <select id="payment-status" v-model="statusFilter">
          <option value="">All</option>
          <option value="paid">Paid</option>
          <option value="unpaid">Unpaid</option>
          <option value="partially_paid">Partially Paid</option>
        </select>
      </div>
      <div class="toolbar-field">
        <label for="payment-sort-by">Sort By</label>
        <select id="payment-sort-by" v-model="sortBy" @change="fetchOrders(1)">
          <option value="created_at">Payment Date</option>
          <option value="id">Order ID</option>
          <option value="customer">Customer</option>
          <option value="total_amount">Total Amount</option>
          <option value="amount_paid">Amount Paid</option>
          <option value="remaining_balance">Remaining Balance</option>
          <option value="payment_status">Status</option>
        </select>
      </div>
      <div class="toolbar-field">
        <label for="payment-sort-direction">Order</label>
        <select id="payment-sort-direction" v-model="sortDirection" @change="fetchOrders(1)">
          <option value="desc">Descending</option>
          <option value="asc">Ascending</option>
        </select>
      </div>
      <div class="toolbar-actions">
        <button class="btn btn-secondary" type="button" @click="applyFilters">Apply</button>
      </div>
    </div>

    <div class="selection-bar">
      <div>
        <strong>{{ selectedOrder ? `Order #${String(selectedOrder.order_id).padStart(4, '0')}` : 'No order selected' }}</strong>
        <p>{{ selectedOrder ? `Remaining balance: ${formatCurrency(selectedOrder.remaining_balance)}` : 'Select a payment row to record a payment.' }}</p>
      </div>
      <div class="selection-actions">
        <button class="btn btn-primary" :disabled="!selectedOrder || selectedOrder.remaining_balance <= 0" @click="openPaymentModal">Record Payment</button>
        <button class="btn btn-secondary" :disabled="!selectedOrder" @click="openHistoryModal">Payment History</button>
      </div>
    </div>

    <div v-if="feedback" class="feedback success">{{ feedback }}</div>
    <div v-if="error" class="feedback error">{{ error }}</div>

    <div class="table-card">
      <div v-if="loading" class="table-state">Loading payment management data...</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th class="select-column"></th>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Total Amount</th>
            <th>Amount Paid</th>
            <th>Remaining Balance</th>
            <th>Payment Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="orders.length === 0">
            <td colspan="7" class="no-data">No payment records found for this category.</td>
          </tr>
          <tr
            v-for="order in orders"
            :key="order.id"
            :class="{ 'selected-row': selectedOrderId === order.id }"
            @click="selectOrder(order)"
          >
            <td class="select-column" @click.stop>
              <input type="checkbox" :checked="selectedOrderId === order.id" @change="selectOrder(order)" />
            </td>
            <td>#{{ String(order.order_id).padStart(4, '0') }}</td>
            <td>{{ order.customer_name }}</td>
            <td>{{ formatCurrency(order.total_amount) }}</td>
            <td>{{ formatCurrency(order.amount_paid) }}</td>
            <td>{{ formatCurrency(order.remaining_balance) }}</td>
            <td><span class="status-pill" :class="order.payment_status">{{ formatStatus(order.payment_status) }}</span></td>
          </tr>
        </tbody>
      </table>

      <div v-if="pagination.total > 0" class="pagination">
        <div class="pagination-summary">
          Showing {{ paginationStart }}-{{ paginationEnd }} of {{ pagination.total }}
        </div>
        <div class="pagination-controls">
          <button class="page-btn" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Previous</button>
          <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="page-btn" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

    <div v-if="showPaymentModal" class="modal-overlay" @click="closePaymentModal">
      <div class="modal-content" @click.stop>
        <h3>Record Payment</h3>
        <p v-if="selectedOrder" class="modal-summary">
          Order #{{ String(selectedOrder.order_id).padStart(4, '0') }} | Total: {{ formatCurrency(selectedOrder.total_amount) }} | Remaining: {{ formatCurrency(selectedOrder.remaining_balance) }}
        </p>
        <form @submit.prevent="submitPayment">
          <div class="form-group">
            <label>Amount Paid</label>
            <input v-model.number="paymentForm.amount" type="number" step="0.01" min="0.01" :max="selectedOrder?.remaining_balance || null" required @input="paymentForm.amount = Math.max(0, paymentForm.amount || 0)" />
          </div>
          <div class="form-group">
            <label>Payment Date</label>
            <input v-model="paymentForm.payment_date" type="date" required />
          </div>
          <div class="form-group">
            <label>Payment Method</label>
            <SearchableSelect v-model="paymentForm.payment_method" :options="paymentMethodOptions" placeholder="Select payment method" />
          </div>
          <div v-if="paymentForm.payment_method === 'bank_transfer'" class="form-group">
            <label>Bank Name *</label>
            <input v-model="paymentForm.bank_name" type="text" placeholder="e.g. BDO, BPI, Metrobank" required />
          </div>
          <div v-if="['gcash','bank_transfer','check'].includes(paymentForm.payment_method)" class="form-group">
            <label>Reference Number *</label>
            <input v-model="paymentForm.reference" type="text" placeholder="e.g. TXN-20260422-001" required />
          </div>
          <div v-if="paymentForm.payment_method === 'check'" class="form-group">
            <label>Deposit Date</label>
            <input v-model="paymentForm.deposit_date" type="date" required />
          </div>
          <div v-if="paymentForm.payment_method === 'check'" class="form-group">
            <label>Check From</label>
            <input v-model="paymentForm.check_from" type="text" placeholder="Issuer name" required />
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" @click="closePaymentModal">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="savingPayment">{{ savingPayment ? 'Saving...' : 'Save Payment' }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showHistoryModal" class="modal-overlay" @click="showHistoryModal = false">
      <div class="modal-content" @click.stop>
        <h3>Payment History</h3>
        <div v-if="!selectedOrder?.payments?.length" class="history-empty">No payments recorded for this order yet.</div>
        <table v-else class="history-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Method</th>
              <th>Details</th>
              <th>Reference</th>
              <th>Recorded By</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in selectedOrder.payments" :key="payment.id">
              <td>{{ payment.payment_date || '--' }}</td>
              <td>{{ formatMethod(payment.payment_method) }}</td>
              <td>{{ payment.bank_name || '--' }}</td>
              <td>{{ payment.reference || '--' }}</td>
              <td>{{ formatUser(payment.recorded_by) }}</td>
              <td>{{ formatCurrency(payment.amount) }}</td>
            </tr>
          </tbody>
        </table>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" @click="showHistoryModal = false">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api';
import SearchableSelect from '../../components/SearchableSelect.vue';
import { formatPhp } from '../../utils/currency';

const orders = ref([]);
const loading = ref(false);
const error = ref('');
const feedback = ref('');
const searchQuery = ref('');
const statusFilter = ref('');
const sortBy = ref('created_at');
const sortDirection = ref('desc');
const selectedOrderId = ref(null);
const rowsPerPage = 10;
const pagination = ref({ current_page: 1, last_page: 1, per_page: rowsPerPage, total: 0 });
const counts = ref({ paid: 0, unpaid: 0, partially_paid: 0 });

const showPaymentModal = ref(false);
const showHistoryModal = ref(false);
const savingPayment = ref(false);

const paymentForm = ref({
  amount: null,
  payment_date: new Date().toISOString().slice(0, 10),
  payment_method: 'cash',
  deposit_date: new Date().toISOString().slice(0, 10),
  check_from: '',
  bank_name: '',
  reference: '',
});

const paymentMethodOptions = [
  { value: 'cash', label: 'Cash' },
  { value: 'gcash', label: 'GCash' },
  { value: 'check', label: 'Check' },
  { value: 'bank_transfer', label: 'Bank Transfer' },
];

const selectedOrder = computed(() => orders.value.find((order) => order.id === selectedOrderId.value) || null);

const statCards = computed(() => [
  { key: 'paid', label: 'Paid', value: counts.value.paid },
  { key: 'unpaid', label: 'Unpaid', value: counts.value.unpaid },
  { key: 'partially_paid', label: 'Partially Paid', value: counts.value.partially_paid },
]);

const paginationStart = computed(() => {
  if (!pagination.value.total) return 0;
  return ((pagination.value.current_page - 1) * pagination.value.per_page) + 1;
});

const paginationEnd = computed(() => Math.min(
  pagination.value.current_page * pagination.value.per_page,
  pagination.value.total
));

const formatCurrency = formatPhp;
const formatMethod = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const formatUser = (user) => user?.name || user?.username || '--';
const formatStatus = (value) => {
  if (value === 'partially_paid') return 'Partially Paid';
  if (value === 'utang') return 'Partially Paid';
  if (value === 'unpaid') return 'Unpaid';
  return 'Paid';
};

const fetchOrders = async (page = 1) => {
  loading.value = true;
  error.value = '';
  feedback.value = '';

  try {
    const response = await api.get('/payments/management', {
      params: {
        page,
        per_page: rowsPerPage,
        search: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
      },
    });

    if (response.data?.success) {
      orders.value = response.data.data || [];
      counts.value = response.data.meta?.counts || counts.value;
      pagination.value = response.data.pagination || pagination.value;
      if (selectedOrderId.value && !orders.value.some((order) => order.id === selectedOrderId.value)) {
        selectedOrderId.value = null;
      }
      return;
    }

    error.value = response.data?.message || 'Failed to load payment management data.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load payment management data.';
  } finally {
    loading.value = false;
  }
};

const selectOrder = (order) => {
  selectedOrderId.value = selectedOrderId.value === order.id ? null : order.id;
};

const setStatusFilter = (status) => {
  statusFilter.value = statusFilter.value === status ? '' : status;
  fetchOrders(1);
};

const applyFilters = () => {
  fetchOrders(1);
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) {
    return;
  }

  fetchOrders(page);
};

const openPaymentModal = () => {
  if (!selectedOrder.value) {
    return;
  }

  paymentForm.value = {
    amount: Number(selectedOrder.value.remaining_balance || 0),
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    deposit_date: new Date().toISOString().slice(0, 10),
    check_from: '',
    bank_name: '',
    reference: '',
  };
  showPaymentModal.value = true;
};

const closePaymentModal = () => {
  showPaymentModal.value = false;
};

const openHistoryModal = () => {
  if (!selectedOrder.value) {
    return;
  }

  showHistoryModal.value = true;
};

const submitPayment = async () => {
  if (!selectedOrder.value) {
    return;
  }

  savingPayment.value = true;
  error.value = '';
  feedback.value = '';

  try {
    const payload = {
      order_id: selectedOrder.value.order_id,
      amount: Number(paymentForm.value.amount || 0),
      payment_date: paymentForm.value.payment_date,
      payment_method: paymentForm.value.payment_method,
      deposit_date: paymentForm.value.payment_method === 'check' ? paymentForm.value.deposit_date : null,
      check_from: paymentForm.value.payment_method === 'check' ? paymentForm.value.check_from : null,
      bank_name: paymentForm.value.payment_method === 'bank_transfer' ? paymentForm.value.bank_name : null,
      reference: paymentForm.value.reference || null,
    };

    const response = await api.post('/payments', payload);
    if (response.data?.success) {
      feedback.value = response.data.message || 'Payment recorded successfully.';
      closePaymentModal();
      await fetchOrders(pagination.value.current_page);
      return;
    }

    error.value = response.data?.message || 'Failed to save payment.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save payment.';
  } finally {
    savingPayment.value = false;
  }
};

onMounted(() => {
  fetchOrders(1);
});
</script>

<style scoped>
.payments-page {
  max-width: 1400px;
  margin: 0 auto;
}

.header-section {
  margin-bottom: 18px;
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
}

.page-summary {
  margin: 8px 0 0;
  color: #607089;
  font-size: 14px;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 18px;
}

.stat-card {
  background: #fff;
  border: 1px solid #e7ebf2;
  border-radius: 12px;
  padding: 18px;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
  cursor: pointer;
}

.stat-card.active {
  border-color: #e57c2a;
  box-shadow: 0 0 0 2px rgba(229, 124, 42, 0.15);
}

.stat-label {
  margin: 0 0 8px;
  color: #607089;
  font-size: 13px;
}

.stat-value {
  margin: 0;
  color: #102746;
  font-size: 34px;
  font-weight: 700;
}

.toolbar,
.selection-bar {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  align-items: end;
  padding: 18px;
  background: #fff;
  border: 1px solid #e7ebf2;
  border-radius: 12px;
  margin-bottom: 16px;
}

.toolbar-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 180px;
}

.search-field {
  flex: 1;
}

.toolbar-field label {
  font-size: 13px;
  font-weight: 600;
  color: #344054;
}

.toolbar-field input,
.toolbar-field select,
.form-group input,
.status-select {
  border: 1px solid #d7deea;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 14px;
  background: #fff;
}

.selection-bar {
  justify-content: space-between;
}

.selection-bar p {
  margin: 6px 0 0;
  color: #607089;
}

.selection-actions {
  display: flex;
  gap: 10px;
}

.feedback {
  margin-bottom: 16px;
  padding: 12px 14px;
  border-radius: 10px;
  font-size: 14px;
}

.feedback.success {
  background: #ecfdf3;
  color: #027a48;
  border: 1px solid #abefc6;
}

.feedback.error {
  background: #fff1f2;
  color: #b42318;
  border: 1px solid #fecdca;
}

.table-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e7ebf2;
  overflow: hidden;
}

.table-state {
  padding: 24px;
  text-align: center;
  color: #607089;
}

.data-table,
.history-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td,
.history-table th,
.history-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #eef1f5;
  text-align: left;
}

.data-table th,
.history-table th {
  font-size: 13px;
  color: #475467;
  background: #f8fafc;
}

.data-table tbody tr {
  cursor: pointer;
}

.data-table tbody tr:hover {
  background: #f9fafb;
}

.selected-row {
  background: #fff7ed !important;
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

.status-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.status-pill.paid {
  background: #ecfdf3;
  color: #027a48;
}

.status-pill.unpaid {
  background: #fff7ed;
  color: #c2410c;
}

.status-pill.partially_paid {
  background: #eff6ff;
  color: #1d4ed8;
}

.no-data,
.history-empty {
  text-align: center;
  color: #667085;
  padding: 20px;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 14px 16px;
  border-top: 1px solid #eef1f5;
}

.pagination-summary {
  color: #475467;
  font-size: 13px;
  font-weight: 600;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.page-info {
  min-width: 104px;
  font-size: 13px;
  color: #667085;
  text-align: center;
}

.page-btn {
  height: 36px;
  border: 1px solid #d7deea;
  border-radius: 8px;
  background: #fff;
  color: #25303d;
  cursor: pointer;
  font-weight: 700;
  padding: 0 14px;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(12, 21, 37, 0.45);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  z-index: 120;
  padding: 70px 16px 24px;
  overflow-y: auto;
}

.modal-content {
  position: relative;
  background: #fff;
  border-radius: 12px;
  width: min(620px, 100%);
  padding: 20px;
  overflow: visible;
  z-index: 121;
}

.modal-content :deep(.searchable-select) {
  z-index: 130;
}

.modal-content :deep(.dropdown-panel) {
  z-index: 140;
  max-height: 220px;
}

.modal-summary {
  color: #475467;
  margin: 8px 0 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 14px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 16px;
}

.btn {
  padding: 10px 16px;
  border: none;
  border-radius: 10px;
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

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
