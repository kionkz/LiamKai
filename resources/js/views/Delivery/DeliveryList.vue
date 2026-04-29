<template>
  <div class="logistics-page">

    <!-- Stat cards -->
    <div class="stat-grid">
      <article class="stat-card">
        <div class="stat-icon blue">&#8594;</div>
        <div>
          <p class="stat-label">In Progress</p>
          <p class="stat-value">{{ meta.in_progress }}</p>
        </div>
      </article>
      <article class="stat-card">
        <div class="stat-icon green">&#10003;</div>
        <div>
          <p class="stat-label">Completed</p>
          <p class="stat-value">{{ meta.completed }}</p>
        </div>
      </article>
      <article class="stat-card">
        <div class="stat-icon orange">&#9679;</div>
        <div>
          <p class="stat-label">Pending</p>
          <p class="stat-value">{{ meta.pending }}</p>
        </div>
      </article>
      <article class="stat-card">
        <div class="stat-icon red">!</div>
        <div>
          <p class="stat-label">Urgent Open</p>
          <p class="stat-value">{{ meta.urgent }}</p>
        </div>
      </article>
      <article class="stat-card">
        <div class="stat-icon dark">&#8801;</div>
        <div>
          <p class="stat-label">Total Orders</p>
          <p class="stat-value">{{ meta.total }}</p>
        </div>
      </article>
    </div>

    <div class="tabs">
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeFulfillmentType === 'delivery' }"
        @click="changeFulfillmentTab('delivery')"
      >
        Deliveries
      </button>
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeFulfillmentType === 'pickup' }"
        @click="changeFulfillmentTab('pickup')"
      >
        Pickups
      </button>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <input
        v-model="searchTerm"
        class="search-input"
        type="text"
        placeholder="Search by order #, customer or address..."
        @keyup.enter="applyFilter"
      />
      <div class="filter-group">
        <label class="filter-toggle">
          <input v-model="includeAllOrders" type="checkbox" />
          <span>All Orders</span>
        </label>
        <input v-model="dateFrom" class="filter-input date-input" type="date" :disabled="includeAllOrders" />
        <span class="range-separator">to</span>
        <input v-model="dateTo" class="filter-input date-input" type="date" :disabled="includeAllOrders" />
        <select v-model="selectedStatus" class="filter-input" data-searchable="off">
          <option value="all">All Statuses</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="pending">Pending</option>
        </select>
        <select v-model="selectedPriority" class="filter-input" data-searchable="off">
          <option value="all">All Orders</option>
          <option value="regular">Regular Orders</option>
          <option value="urgent">Urgent / Rushed Orders</option>
        </select>
        <select v-model="sortBy" class="filter-input" data-searchable="off">
          <option value="scheduled_for">Scheduled Date</option>
          <option value="priority">Priority</option>
          <option value="id">Order Number</option>
          <option value="created_at">Created Date</option>
          <option value="total_amount">Amount</option>
          <option value="status">Status</option>
        </select>
        <select v-model="sortDirection" class="filter-input" data-searchable="off">
          <option value="asc">Ascending</option>
          <option value="desc">Descending</option>
        </select>
        <button class="filter-btn" type="button" @click="applyFilter">Filter</button>
      </div>
    </div>

    <!-- Action bar -->
    <div class="actions-bar">
      <router-link
        v-if="selectedOrderId"
        :to="`/deliveries/${selectedOrderId}`"
        class="action-btn primary"
      >
        &#9998; Update {{ activeFulfillmentType === 'pickup' ? 'Pickup' : 'Delivery' }}
      </router-link>
      <span v-else class="hint-text">Select a {{ activeFulfillmentType === 'pickup' ? 'pickup' : 'delivery' }} row to update its fulfillment status</span>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <div v-if="loading" class="table-state">Loading orders...</div>
      <div v-else-if="loadError" class="table-state error">{{ loadError }}</div>
      <template v-else>
        <table>
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Priority</th>
              <th>Type</th>
              <th>Address / Note</th>
              <th>Scheduled</th>
              <th>{{ activeFulfillmentType === 'pickup' ? 'Picked Up' : 'Delivered' }}</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Audit</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="order in orders"
              :key="order.id"
              @click="selectedOrderId = order.id"
              :class="{ 'selected-row': selectedOrderId === order.id, 'urgent-row': order.is_urgent && order.status !== 'completed' }"
            >
              <td class="order-no">#{{ String(order.id).padStart(4, '0') }}</td>
              <td>{{ order.customer_name }}</td>
              <td>
                <span class="priority-badge" :class="order.order_priority">
                  {{ order.is_urgent ? 'URGENT' : 'Regular' }}
                </span>
              </td>
              <td>
                <span class="type-badge" :class="order.fulfillment_type">
                  {{ order.fulfillment_type === 'pickup' ? 'Pickup' : 'Delivery' }}
                </span>
              </td>
              <td class="address-cell">{{ order.delivery_address || '—' }}</td>
              <td>{{ formatDateTime(order.scheduled_for) }}</td>
              <td>{{ formatDateTime(order.actual_fulfillment_at) }}</td>
              <td class="amount-cell">{{ formatAmount(order.total_amount) }}</td>
              <td>
                <span class="status-pill" :class="order.status">{{ statusLabel(order.status, order.fulfillment_type) }}</span>
              </td>
              <td class="audit-cell">{{ auditLabel(order) }}</td>
            </tr>
            <tr v-if="orders.length === 0">
              <td colspan="10" class="empty-row">
                {{ emptyStateLabel }}
              </td>
            </tr>
          </tbody>
        </table>

        <div class="pagination" v-if="pagination.last_page > 1">
          <button class="page-btn" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">&#8592; Prev</button>
          <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="page-btn" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">Next &#8594;</button>
        </div>
      </template>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api';
import { formatPeso } from '../../utils/currency';

const orders = ref([]);
const loading = ref(false);
const loadError = ref('');
const selectedOrderId = ref(null);
const activeFulfillmentType = ref('delivery');

const searchTerm = ref('');
const selectedStatus = ref('all');
const selectedPriority = ref('all');
const includeAllOrders = ref(false);
const dateFrom = ref(new Date().toISOString().slice(0, 10));
const dateTo = ref(new Date().toISOString().slice(0, 10));
const appliedDateFrom = ref(new Date().toISOString().slice(0, 10));
const appliedDateTo = ref(new Date().toISOString().slice(0, 10));
const appliedIncludeAll = ref(false);
const sortBy = ref('scheduled_for');
const sortDirection = ref('asc');

const pagination = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
const meta = ref({ total: 0, pending: 0, in_progress: 0, completed: 0, urgent: 0 });

const statusLabel = (status, fulfillmentType = 'delivery') => {
  if (status === 'in_progress') return fulfillmentType === 'pickup' ? 'Ready for Pickup' : 'En-route';
  if (status === 'completed') return fulfillmentType === 'pickup' ? 'Picked Up' : 'Delivered';
  return 'Pending';
};

const formatDateTime = (value) => {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatDateDisplay = (value) => {
  if (!value) return 'selected date';
  const d = new Date(value + 'T00:00:00');
  return d.toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
};

const emptyStateLabel = computed(() => {
  if (appliedIncludeAll.value) {
    return `No ${activeFulfillmentType.value === 'pickup' ? 'pickup' : 'delivery'} orders matched the current filters.`;
  }

  if (appliedDateFrom.value && appliedDateTo.value && appliedDateFrom.value !== appliedDateTo.value) {
    return `No orders scheduled from ${formatDateDisplay(appliedDateFrom.value)} to ${formatDateDisplay(appliedDateTo.value)}.`;
  }

  return `No orders scheduled for ${formatDateDisplay(appliedDateFrom.value)}.`;
});

const formatAmount = formatPeso;

const userName = (user) => user?.name || user?.username || 'Unknown user';

const auditLabel = (order) => {
  if (!order.fulfillment_action && !order.fulfillment_updated_by) {
    return 'No logistics update yet';
  }

  return `${userName(order.fulfillment_updated_by)} ${order.fulfillment_action || 'updated logistics'}`;
};

const loadOrders = async (page = 1) => {
  loading.value = true;
  loadError.value = '';
  try {
    const params = {
      page,
      per_page: pagination.value.per_page,
      include_all: appliedIncludeAll.value,
      fulfillment_type: activeFulfillmentType.value,
      sort_by: sortBy.value,
      sort_direction: sortDirection.value,
    };
    if (!appliedIncludeAll.value) {
      if (appliedDateFrom.value && appliedDateTo.value) {
        params.date_from = appliedDateFrom.value;
        params.date_to = appliedDateTo.value;
      } else if (appliedDateFrom.value) {
        params.date = appliedDateFrom.value;
      }
    }
    if (searchTerm.value.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value !== 'all') params.status = selectedStatus.value;
    if (selectedPriority.value !== 'all') params.priority = selectedPriority.value;

    const response = await api.get('/orders/logistics', { params });

    if (response.data?.success) {
      orders.value = response.data.data || [];
      pagination.value = response.data.pagination || pagination.value;
      meta.value = response.data.meta?.counts || meta.value;
      if (selectedOrderId.value && !orders.value.some(o => o.id === selectedOrderId.value)) {
        selectedOrderId.value = null;
      }
      return;
    }
    loadError.value = response.data?.message || 'Failed to load orders';
  } catch (error) {
    loadError.value = error.response?.data?.message || 'Failed to load orders. Please try again.';
  } finally {
    loading.value = false;
  }
};

const changeFulfillmentTab = (type) => {
  if (activeFulfillmentType.value === type) return;
  activeFulfillmentType.value = type;
  selectedOrderId.value = null;
  loadOrders(1);
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  loadOrders(page);
};

const applyFilter = () => {
  if (!includeAllOrders.value) {
    const start = dateFrom.value || dateTo.value;
    const end = dateTo.value || dateFrom.value;

    if (!start || !end) {
      loadError.value = 'Choose both start and end dates, or enable All Orders.';
      return;
    }

    if (start > end) {
      loadError.value = 'The start date must be earlier than or equal to the end date.';
      return;
    }

    appliedDateFrom.value = start;
    appliedDateTo.value = end;
  }

  appliedIncludeAll.value = includeAllOrders.value;
  loadOrders(1);
};

onMounted(() => loadOrders(1));
</script>

<style scoped>
.logistics-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  animation: fadeIn 0.25s ease;
}

/* Stat cards */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
}

.stat-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e6eaf2;
  box-shadow: 0 2px 6px rgba(10, 25, 52, 0.06);
  padding: 16px 18px;
  display: flex;
  align-items: center;
  gap: 14px;
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-weight: 700;
  color: #fff;
  font-size: 18px;
  flex-shrink: 0;
}

.stat-icon.blue   { background: #58a8ea; }
.stat-icon.green  { background: #54c081; }
.stat-icon.orange { background: #e28937; }
.stat-icon.red    { background: #dc2626; }
.stat-icon.dark   { background: #0a1d37; }

.stat-label { margin: 0; font-size: 11px; color: #7b8598; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-value { margin: 0; font-size: 32px; line-height: 1; color: #122544; font-weight: 800; }

.tabs {
  display: flex;
  gap: 20px;
  padding: 0;
  border-bottom: 1px solid #d8dde6;
}

.tab-btn {
  appearance: none;
  border: none;
  border-bottom: 3px solid transparent;
  background: transparent;
  color: #5f6470;
  cursor: pointer;
  font-size: 14px;
  font-weight: 700;
  padding: 14px 0 12px;
}

.tab-btn.active {
  color: #e57c2a;
  border-bottom-color: #e57c2a;
}

.tab-btn:hover {
  color: #c45f17;
}

/* Toolbar */
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input {
  flex: 1;
  min-width: 240px;
  height: 40px;
  border: 1px solid #dce2ec;
  border-radius: 8px;
  padding: 0 14px;
  font-size: 14px;
  color: #25334a;
  background: #fff;
}

.filter-group {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.filter-input {
  height: 40px;
  border: 1px solid #dce2ec;
  border-radius: 8px;
  padding: 0 12px;
  font-size: 14px;
  color: #25334a;
  background: #fff;
}

.date-input { min-width: 150px; }

.filter-toggle {
  height: 40px;
  padding: 0 12px;
  border: 1px solid #dce2ec;
  border-radius: 8px;
  background: #fff;
  color: #25334a;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.filter-toggle input {
  margin: 0;
}

.range-separator {
  color: #64748b;
  font-size: 13px;
  font-weight: 600;
}

.filter-btn {
  height: 40px;
  border: none;
  border-radius: 8px;
  background: #e28937;
  color: #fff;
  font-weight: 700;
  padding: 0 20px;
  cursor: pointer;
  white-space: nowrap;
}

/* Actions bar */
.actions-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  background: #fff;
  border-radius: 10px;
  border: 1px solid #e6eaf2;
  min-height: 48px;
}

.action-btn {
  display: inline-block;
  text-decoration: none;
  border-radius: 8px;
  padding: 8px 20px;
  font-size: 13px;
  font-weight: 700;
}

.action-btn.primary { background: #0a1d37; color: #fff; }
.hint-text { font-size: 13px; color: #7b8598; }

/* Table */
.table-wrap {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e6eaf2;
  overflow: hidden;
}

.table-state {
  padding: 40px;
  text-align: center;
  font-size: 14px;
  color: #607089;
}
.table-state.error { color: #c0392b; }

table { width: 100%; border-collapse: collapse; }

thead { background: #f8fafc; }
th {
  padding: 12px 14px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: #6b7a99;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #e7ebf2;
  white-space: nowrap;
}

td {
  padding: 13px 14px;
  font-size: 13px;
  color: #2b3650;
  border-bottom: 1px solid #eef1f5;
}

tbody tr { cursor: pointer; transition: background 0.12s; }
tbody tr:hover { background: #f9fafb; }
tbody tr:last-child td { border-bottom: none; }

.selected-row { background: #fff7ed !important; }
.selected-row td:first-child { border-left: 3px solid #e28937; }
.urgent-row { background: #fff8f8; }
.urgent-row td:first-child { border-left: 3px solid #dc2626; }

.order-no { font-weight: 700; color: #0a1d37; }
.address-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #5a6882; }
.amount-cell { font-weight: 600; color: #0a1d37; }
.audit-cell { color: #5a6882; font-size: 12px; max-width: 220px; }

/* Badges */
.type-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: capitalize;
}
.type-badge.delivery { background: #e3f2fd; color: #1565c0; }
.type-badge.pickup   { background: #f3e5f5; color: #6a1b9a; }

.priority-badge {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 4px 9px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}
.priority-badge.urgent {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}
.priority-badge.regular {
  background: #eef2f7;
  color: #475569;
}

.status-pill {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}
.status-pill.in_progress { background: #d3ecff; color: #2f7db7; }
.status-pill.completed   { background: #daf5e3; color: #2a8d57; }
.status-pill.pending     { background: #fef3e2; color: #9a6f20; }

.empty-row {
  text-align: center;
  color: #73809a;
  padding: 40px;
  font-size: 14px;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-top: 1px solid #e7ebf2;
}
.page-btn {
  height: 34px;
  border: 1px solid #dce2ec;
  border-radius: 6px;
  background: #fff;
  color: #2a3b57;
  font-weight: 600;
  padding: 0 14px;
  cursor: pointer;
  font-size: 13px;
}
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 13px; color: #607089; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>
