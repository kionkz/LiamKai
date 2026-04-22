<template>
  <div class="orders-container">
    <div class="actions-bar">
      <div class="selection-state">
        <strong>{{ selectedOrder ? `Order #${selectedOrder.id.toString().padStart(4, '0')}` : 'No order selected' }}</strong>
        <span>{{ selectedOrder ? 'Order actions are enabled.' : 'Select a row to review or edit order details.' }}</span>
      </div>
      <div class="toolbar-actions">
        <button @click="showCreateModal = true" class="btn btn-primary">Create Order</button>
        <button @click="openEditModal" class="btn btn-secondary" :disabled="!selectedOrder">Edit</button>
        <button @click="openSummaryModal" class="btn btn-secondary" :disabled="!selectedOrder">View</button>
        <button @click="printReceipt" class="btn btn-secondary" :disabled="!selectedOrder">Print Receipt</button>
        <button @click="openDeleteConfirm" class="btn btn-danger" :disabled="!canCancelSelectedOrder">Cancel Order</button>
      </div>
    </div>

    <div class="filters">
      <input v-model="searchQuery" type="text" placeholder="Search order # or customer name..." />
      <select v-model="filterFulfillment" data-searchable="off">
        <option value="">All Fulfillment Types</option>
        <option value="delivery">Delivery</option>
        <option value="pickup">Pickup</option>
      </select>
      <select v-model="sortBy" data-searchable="off" @change="fetchOrders(1)">
        <option value="created_at">Order Date</option>
        <option value="id">Order #</option>
        <option value="customer">Customer</option>
        <option value="pricing">Pricing</option>
        <option value="fulfillment_type">Fulfillment</option>
        <option value="scheduled_for">Scheduled Date</option>
        <option value="total_amount">Total</option>
      </select>
      <select v-model="sortDirection" data-searchable="off" @change="fetchOrders(1)">
        <option value="desc">Descending</option>
        <option value="asc">Ascending</option>
      </select>
      <button @click="fetchOrders(1)" class="btn btn-secondary">Search</button>
    </div>

    <div v-if="loading" class="loading-state">
      <p>Loading orders...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="fetchOrders(1)" class="btn btn-secondary">Retry</button>
    </div>

    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th class="select-column"></th>
            <th>Order #</th>
            <th>Customer</th>
            <th>Pricing</th>
            <th>Fulfillment</th>
            <th>Scheduled</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Order Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="orders.length === 0">
            <td colspan="10" class="no-data">No orders found matching your criteria.</td>
          </tr>
          <tr
            v-for="order in orders"
            :key="order.id"
            @click="selectOrder(order)"
            :class="{ 'selected-row': selectedOrderId === order.id, 'cancelled-row': order.order_status === 'cancelled' }"
          >
            <td class="select-column" @click.stop>
              <input type="checkbox" :checked="selectedOrderId === order.id" @change="selectOrder(order)" />
            </td>
            <td>#{{ order.id.toString().padStart(4, '0') }}</td>
            <td>{{ order.customer?.name || 'N/A' }}</td>
            <td><span class="badge" :class="order.type">{{ formatPricingType(order.type) }}</span></td>
            <td><span class="badge" :class="order.fulfillment_type">{{ formatFulfillmentType(order.fulfillment_type) }}</span></td>
            <td>{{ formatScheduled(order.scheduled_for) }}</td>
            <td>₱{{ Number(order.total_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</td>
            <td><span class="pay-status" :class="order.payment_status">{{ formatPaymentStatus(order.payment_status) }}</span></td>
            <td><span class="status" :class="order.order_status">{{ formatOrderStatus(order.order_status) }}</span></td>
            <td>{{ formatOrderDate(order.order_date || order.created_at) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="pagination" v-if="pagination.last_page > 1">
        <button class="btn btn-secondary" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">Previous</button>
        <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button class="btn btn-secondary" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">Next</button>
      </div>
    </div>

    <div v-if="showCreateModal" class="modal-overlay" @click="showCreateModal = false">
      <div class="modal-content modal-wide" @click.stop>
        <CreateOrder is-modal @created="handleOrderCreated" @close="showCreateModal = false" />
      </div>
    </div>

    <div v-if="showSummaryModal" class="modal-overlay" @click="showSummaryModal = false">
      <div class="modal-content summary-modal" @click.stop>
        <h3>Order Summary</h3>
        <div v-if="selectedOrder" class="summary-block">
          <div class="summary-meta">
            <p><strong>Order #:</strong> #{{ selectedOrder.id.toString().padStart(4, '0') }}</p>
            <p><strong>Customer:</strong> {{ selectedOrder.customer?.name || 'N/A' }}</p>
            <p><strong>Pricing:</strong> {{ formatPricingType(selectedOrder.type) }}</p>
            <p><strong>Fulfillment:</strong> {{ formatFulfillmentType(selectedOrder.fulfillment_type) }}</p>
            <p><strong>Scheduled:</strong> {{ formatScheduled(selectedOrder.scheduled_for) }}</p>
            <p><strong>Status:</strong> {{ formatOrderStatus(selectedOrder.order_status) }}</p>
            <p><strong>Logistics Status:</strong> {{ formatFulfillmentStatus(selectedOrder.fulfillment_status) }}</p>
            <p v-if="selectedOrder.delivery_address"><strong>Delivery Address:</strong> {{ selectedOrder.delivery_address }}</p>
            <p v-if="selectedOrder.notes"><strong>Notes:</strong> {{ selectedOrder.notes }}</p>
          </div>

          <div class="summary-items">
            <h4>Items Ordered</h4>
            <table class="summary-items-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th class="text-right">Qty</th>
                  <th class="text-right">Unit Price</th>
                  <th class="text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in (selectedOrder.order_items || [])" :key="item.id">
                  <td>{{ item.product?.name || 'Unknown' }}</td>
                  <td class="text-right">{{ Number(item.quantity).toLocaleString() }}</td>
                  <td class="text-right">₱{{ Number(item.unit_price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
                  <td class="text-right">₱{{ Number(item.subtotal || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
                </tr>
                <tr v-if="!selectedOrder.order_items || selectedOrder.order_items.length === 0">
                  <td colspan="4" class="no-items">No items found.</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="summary-total-row">
                  <td colspan="3"><strong>Total</strong></td>
                  <td class="text-right"><strong>₱{{ Number(selectedOrder.total_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
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
            <label>Fulfillment Type</label>
            <SearchableSelect v-model="editForm.fulfillment_type" :options="fulfillmentTypeOptions" placeholder="Select fulfillment type" />
          </div>
          <div class="form-group">
            <label>Scheduled Date &amp; Time</label>
            <input v-model="editForm.scheduled_for" type="datetime-local" />
          </div>
          <div class="form-group" v-if="editForm.fulfillment_type === 'delivery'">
            <label>Delivery Address</label>
            <input v-model="editForm.delivery_address" type="text" />
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

    <div v-if="showDeleteConfirm" class="modal-overlay" @click="showDeleteConfirm = false">
      <div class="modal-content small-modal" @click.stop>
        <h3>Cancel Order</h3>
        <p>Cancel selected order #{{ selectedOrder?.id?.toString().padStart(4, '0') }}?</p>
        <p class="warning-text">This will mark the order as cancelled and return all ordered quantities to inventory.</p>
        <div class="modal-actions">
          <button @click="showDeleteConfirm = false" class="btn btn-secondary">Go Back</button>
          <button @click="confirmDelete" :disabled="deleting" class="btn btn-danger">{{ deleting ? 'Cancelling...' : 'Confirm Cancel' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import api from '../../api';
import SearchableSelect from '../../components/SearchableSelect.vue';
import CreateOrder from './CreateOrder.vue';

const orders = ref([]);
const loading = ref(false);
const error = ref('');
const searchQuery = ref('');
const filterFulfillment = ref('');
const sortBy = ref('created_at');
const sortDirection = ref('desc');
const pagination = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
const selectedOrderId = ref(null);
const showCreateModal = ref(false);
const showSummaryModal = ref(false);
const showEditModal = ref(false);
const showDeleteConfirm = ref(false);
const deleting = ref(false);
const savingEdit = ref(false);

const editForm = ref({
  fulfillment_type: 'delivery',
  scheduled_for: '',
  delivery_address: '',
  notes: '',
});

const fulfillmentTypeOptions = [
  { value: 'delivery', label: 'Delivery' },
  { value: 'pickup', label: 'Pickup' },
];

const selectedOrder = computed(() => orders.value.find((order) => order.id === selectedOrderId.value) || null);
const canCancelSelectedOrder = computed(() => {
  if (!selectedOrder.value) return false;

  const hasLogisticsProgress = selectedOrder.value.fulfillment_status !== 'pending'
    || selectedOrder.value.delivery_status !== 'pending';
  const hasPaymentActivity = ['paid', 'partially_paid', 'utang'].includes(selectedOrder.value.payment_status)
    || Number(selectedOrder.value.outstanding_balance || 0) < Number(selectedOrder.value.total_amount || 0);

  return !hasLogisticsProgress && !hasPaymentActivity;
});

const formatOrderDate = (value) => {
  if (!value) return '--';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '--' : date.toLocaleDateString();
};

const formatScheduled = (value) => {
  if (!value) return '--';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '--' : date.toLocaleString();
};

const formatDateTimeLocal = (value) => {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
};

const formatFulfillmentType = (value) => value === 'pickup' ? 'Pickup' : 'Delivery';
const formatPricingType = (value) => value === 'wholesale' ? 'Wholesale' : 'Retail';

const formatFulfillmentStatus = (value) => {
  if (value === 'in_progress') return 'In Progress';
  if (value === 'completed') return 'Completed';
  if (value === 'cancelled') return 'Cancelled';
  return 'Pending';
};

const formatOrderStatus = (value) => {
  if (value === 'complete') return 'Complete';
  if (value === 'cancelled') return 'Cancelled';
  return 'Pending';
};

const formatPaymentStatus = (value) => {
  if (value === 'paid') return 'Paid';
  if (value === 'partially_paid' || value === 'utang') return 'Partial';
  return 'Unpaid';
};

const selectOrder = (order) => {
  selectedOrderId.value = selectedOrderId.value === order.id ? null : order.id;
};

const fetchOrders = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/orders', {
      params: {
        page,
        per_page: pagination.value.per_page,
        search: searchQuery.value.trim() || undefined,
        fulfillment_type: filterFulfillment.value || undefined,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
      },
    });
    if (response.data.success) {
      orders.value = response.data.data;
      pagination.value = response.data.pagination || pagination.value;
      if (selectedOrderId.value && !orders.value.some((order) => order.id === selectedOrderId.value)) {
        selectedOrderId.value = null;
      }
      return;
    }
    error.value = response.data.message || 'Failed to load orders';
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

const handleOrderCreated = async () => {
  showCreateModal.value = false;
  await fetchOrders(1);
};

const openSummaryModal = () => {
  if (!selectedOrder.value) return;
  showSummaryModal.value = true;
};

const openEditModal = () => {
  if (!selectedOrder.value) return;
  editForm.value = {
    fulfillment_type: selectedOrder.value.fulfillment_type || 'delivery',
    scheduled_for: formatDateTimeLocal(selectedOrder.value.scheduled_for),
    delivery_address: selectedOrder.value.delivery_address || '',
    notes: selectedOrder.value.notes || '',
  };
  showEditModal.value = true;
};

const saveOrderEdit = async () => {
  if (!selectedOrder.value) return;
  savingEdit.value = true;
  try {
    const response = await api.put(`/orders/${selectedOrder.value.id}`, editForm.value);
    if (response.data.success) {
      await fetchOrders(pagination.value.current_page);
      showEditModal.value = false;
      return;
    }
    alert(response.data.message || 'Failed to update order');
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to update order');
  } finally {
    savingEdit.value = false;
  }
};

const printReceipt = async () => {
  if (!selectedOrder.value) return;

  let order = selectedOrder.value;
  // Fetch full order details if items aren't loaded
  if (!order.items || order.items.length === 0) {
    try {
      const response = await api.get(`/orders/${order.id}`);
      if (response.data.success) order = response.data.data;
    } catch {
      alert('Failed to load order details for receipt');
      return;
    }
  }

  const doc = new jsPDF();
  const formatMoney = (val) => `PHP ${Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

  // Header
  doc.setFontSize(18);
  doc.setFont(undefined, 'bold');
  doc.text('LiamKai Fish Wholesale', 105, 18, { align: 'center' });
  doc.setFontSize(11);
  doc.setFont(undefined, 'normal');
  doc.text('OFFICIAL RECEIPT', 105, 26, { align: 'center' });

  // Divider
  doc.setDrawColor(229, 124, 42);
  doc.setLineWidth(0.5);
  doc.line(15, 30, 195, 30);

  // Order meta
  doc.setFontSize(10);
  const leftX = 15;
  const rightX = 110;
  doc.text(`Order #: ${String(order.id).padStart(4, '0')}`, leftX, 38);
  doc.text(`Date: ${formatOrderDate(order.order_date || order.created_at)}`, leftX, 45);
  doc.text(`Customer: ${order.customer?.name || 'N/A'}`, leftX, 52);
  doc.text(`Payment: ${formatPaymentStatus(order.payment_status)}`, leftX, 59);
  doc.text(`Fulfillment: ${formatFulfillmentType(order.fulfillment_type)}`, rightX, 38);
  if (order.scheduled_for) doc.text(`Scheduled: ${formatScheduled(order.scheduled_for)}`, rightX, 45);
  if (order.delivery_address) doc.text(`Address: ${order.delivery_address}`, rightX, 52);

  // Items table
  const items = order.items || order.order_items || [];
  autoTable(doc, {
    startY: 68,
    head: [['Product', 'Qty', 'Unit', 'Unit Price', 'Subtotal']],
    body: items.map((item) => [
      item.product?.name || `Product #${item.product_id}`,
      Number(item.quantity || 0).toFixed(2),
      item.unit || item.product?.unit_of_measure || '',
      formatMoney(item.unit_price),
      formatMoney(item.subtotal ?? item.total),
    ]),
    styles: { fontSize: 9, cellPadding: 4 },
    headStyles: { fillColor: [229, 124, 42], textColor: 255 },
    foot: [['', '', '', 'TOTAL', formatMoney(order.total_amount)]],
    footStyles: { fillColor: [255, 247, 237], textColor: [124, 45, 18], fontStyle: 'bold' },
  });

  const finalY = doc.lastAutoTable.finalY + 12;
  doc.setFontSize(9);
  doc.setTextColor(100);
  doc.text('Thank you for your business!', 105, finalY, { align: 'center' });

  doc.save(`Receipt-Order-${String(order.id).padStart(4, '0')}.pdf`);
};

const openDeleteConfirm = () => {
  if (!canCancelSelectedOrder.value) return;
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
      const targetPage = orders.value.length === 1 && pagination.value.current_page > 1 ? pagination.value.current_page - 1 : pagination.value.current_page;
      await fetchOrders(targetPage);
      return;
    }
    alert(response.data.message || 'Failed to cancel order');
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to cancel order');
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

.page-summary {
  margin: 8px 0 0;
  color: #607089;
  font-size: 14px;
}

.actions-bar {
  position: sticky;
  top: 8px;
  z-index: 20;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 16px;
  padding: 14px;
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 10px;
}

.selection-state {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.selection-state strong {
  color: #102746;
  font-size: 15px;
}

.selection-state span {
  color: #607089;
  font-size: 13px;
}

.toolbar-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
  padding: 14px;
  background: #fff;
  border-radius: 10px;
  border: 1px solid #e9ecef;
}

.filters input,
.filters select,
.form-group input,
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
  background: #fff7ed !important;
  outline: 2px solid #e57c2a;
}

.cancelled-row {
  background: #f3f4f6;
  color: #6b7280;
}

.cancelled-row td {
  color: #6b7280;
}

.cancelled-row:hover {
  background: #e5e7eb !important;
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

.badge,
.status {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.pay-status { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
.pay-status.paid { background: #ecfdf3; color: #027a48; }
.pay-status.unpaid { background: #fff3e0; color: #ef6c00; }
.pay-status.partially_paid, .pay-status.utang { background: #eff6ff; color: #1d4ed8; }

.badge.retail { background: #e3f2fd; color: #1976d2; }
.badge.wholesale { background: #f3e5f5; color: #7b1fa2; }
.badge.delivery { background: #eff6ff; color: #1d4ed8; }
.badge.pickup { background: #ecfdf3; color: #027a48; }
.status.pending { background: #fff3e0; color: #ef6c00; }
.status.in_progress { background: #e1f5fe; color: #0277bd; }
.status.completed,
.status.complete { background: #e8f5e9; color: #2e7d32; }
.status.cancelled { background: #e5e7eb; color: #4b5563; }

.btn {
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
}

.btn-primary { background: #e57c2a; color: #fff; }
.btn-secondary { background: #f1f3f5; color: #25303d; }
.btn-danger { background: #dc3545; color: #fff; }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(12, 21, 37, 0.52);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 120;
  padding: 32px 24px;
  overflow-y: auto;
}

.modal-content {
  background: #fff;
  border-radius: 16px;
  width: min(620px, 100%);
  padding: 24px;
  box-shadow: 0 24px 64px rgba(12, 21, 37, 0.22);
  margin: auto;
}

.modal-wide {
  width: min(1280px, 96vw);
  max-height: calc(100vh - 80px);
  padding: 0;
  overflow: hidden;
  overflow-y: auto;
  border-radius: 20px;
  box-shadow: 0 32px 80px rgba(12, 21, 37, 0.26);
  margin: auto;
}
.small-modal { width: min(420px, 100%); }

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

.summary-block p { margin: 6px 0; }

.warning-text {
  margin: 10px 0 0;
  color: #9f1239;
  font-size: 14px;
  line-height: 1.45;
}

.summary-modal {
  width: 620px;
  max-width: 95vw;
}

.summary-meta {
  margin-bottom: 20px;
}

.summary-items h4 {
  margin: 0 0 10px 0;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #666;
}

.summary-items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.summary-items-table th {
  padding: 8px 10px;
  background: #f5f5f5;
  font-weight: 600;
  font-size: 12px;
  color: #555;
  border-bottom: 2px solid #e0e0e0;
}

.summary-items-table td {
  padding: 9px 10px;
  border-bottom: 1px solid #eee;
}

.summary-items-table tfoot td {
  border-top: 2px solid #e0e0e0;
  border-bottom: none;
  padding: 10px;
}

.summary-total-row td {
  background: #fafafa;
}

.text-right {
  text-align: right;
}

.no-items {
  text-align: center;
  color: #999;
  padding: 16px;
}

@media (max-width: 900px) {
  .modal-overlay {
    padding: 0;
    align-items: flex-end;
  }

  .modal-wide {
    width: 100%;
    max-height: 94vh;
    border-radius: 20px 20px 0 0;
    margin: 0;
  }

  .modal-content {
    width: 100%;
    border-radius: 20px 20px 0 0;
    margin: 0;
  }
}
</style>
