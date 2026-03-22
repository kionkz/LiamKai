<template>
  <div class="logistics-page">
    <h1>Logistics</h1>

    <section class="board">
      <div class="stat-grid">
        <article class="stat-card">
          <div class="stat-icon blue">-></div>
          <div>
            <p class="stat-label">En-route</p>
            <p class="stat-value">{{ enRouteCount }}</p>
          </div>
        </article>
        <article class="stat-card">
          <div class="stat-icon green">v</div>
          <div>
            <p class="stat-label">Delivered Today</p>
            <p class="stat-value">{{ deliveredTodayCount }}</p>
          </div>
        </article>
      </div>

      <div class="toolbar">
        <div class="search-wrap">
          <input
            v-model="searchTerm"
            class="search-input"
            type="text"
            placeholder="Search by order #, customer, rider"
            @keyup.enter="applyFilter"
          />
        </div>

        <div class="filter-group">
          <select v-model="selectedStatus" class="status-select">
            <option value="all">All</option>
            <option value="en-route">En-route</option>
            <option value="delivered">Delivered</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
          </select>
          <button class="filter-btn" type="button" @click="applyFilter">Filter</button>
        </div>
      </div>

      <div class="table-wrap">
        <div v-if="loading" class="table-state">Loading deliveries...</div>
        <div v-else-if="loadError" class="table-state error">{{ loadError }}</div>
        <table>
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Rider</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody v-if="!loading && !loadError">
            <tr v-for="delivery in filteredDeliveries" :key="delivery.id">
              <td>{{ delivery.orderNo }}</td>
              <td>{{ delivery.customer }}</td>
              <td>{{ delivery.rider }}</td>
              <td>{{ delivery.date }}</td>
              <td>
                <span class="status-pill" :class="delivery.status">{{ delivery.statusLabel }}</span>
              </td>
              <td>
                <router-link :to="`/deliveries/${delivery.id}`" class="update-btn">Update</router-link>
              </td>
            </tr>
            <tr v-if="filteredDeliveries.length === 0">
              <td colspan="6" class="empty-row">No deliveries found.</td>
            </tr>
          </tbody>
        </table>

        <div class="pagination" v-if="pagination.last_page > 1">
          <button class="filter-btn" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">Previous</button>
          <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="filter-btn" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">Next</button>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api';

const deliveries = ref([]);
const loading = ref(false);
const loadError = ref('');

const searchTerm = ref('');
const selectedStatus = ref('all');
const appliedSearch = ref('');
const appliedStatus = ref('all');
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

const formatDate = (value) => {
  if (!value) return '--';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '--';
  return date.toLocaleDateString('en-GB');
};

const statusToLabel = (status) => {
  if (status === 'in_transit') return 'En-route';
  if (status === 'delivered') return 'Delivered';
  if (status === 'failed') return 'Failed';
  return 'Pending';
};

const statusToClass = (status) => {
  if (status === 'in_transit') return 'en-route';
  if (status === 'delivered') return 'delivered';
  if (status === 'failed') return 'failed';
  return 'pending';
};

const parseOrderNumber = (orderNo) => {
  const numericPart = String(orderNo || '').match(/\d+/);
  return numericPart ? Number(numericPart[0]) : Number.MAX_SAFE_INTEGER;
};

const loadDeliveries = async (page = 1) => {
  loading.value = true;
  loadError.value = '';

  try {
    const response = await api.get('/deliveries', {
      params: { page, per_page: pagination.value.per_page }
    });
    if (response.data?.success) {
      pagination.value = response.data.pagination || pagination.value;
      deliveries.value = (response.data.data || []).map((row) => ({
        id: row.id,
        orderNo: row.order?.order_number || `ORD${String(row.order_id || row.id).padStart(3, '0')}`,
        customer: row.order?.customer?.name || 'Walk-In Customer',
        rider: row.employee?.name || 'Unassigned',
        date: formatDate(row.scheduled_delivery || row.created_at),
        status: statusToClass(row.status),
        statusLabel: statusToLabel(row.status),
      }))
        .sort((a, b) => parseOrderNumber(a.orderNo) - parseOrderNumber(b.orderNo));
      return;
    }

    loadError.value = response.data?.message || 'Failed to load deliveries';
  } catch (error) {
    loadError.value = error.response?.data?.message || 'Failed to load deliveries';
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  loadDeliveries(page);
};

const applyFilter = () => {
  appliedSearch.value = searchTerm.value.trim().toLowerCase();
  appliedStatus.value = selectedStatus.value;
};

const filteredDeliveries = computed(() => {
  return deliveries.value.filter((delivery) => {
    const matchesSearch =
      !appliedSearch.value ||
      delivery.orderNo.toLowerCase().includes(appliedSearch.value) ||
      delivery.customer.toLowerCase().includes(appliedSearch.value) ||
      delivery.rider.toLowerCase().includes(appliedSearch.value);

    const matchesStatus = appliedStatus.value === 'all' || delivery.status === appliedStatus.value;

    return matchesSearch && matchesStatus;
  });
});

const enRouteCount = computed(() => deliveries.value.filter((item) => item.status === 'en-route').length);
const deliveredTodayCount = computed(() => deliveries.value.filter((item) => item.status === 'delivered').length);

onMounted(loadDeliveries);
</script>

<style scoped>
.logistics-page {
  animation: fadeIn 0.25s ease;
}

h1 {
  margin: 0 0 14px;
  color: #102746;
  font-size: 44px;
}

.board {
  background: #f3f5f8;
  border-radius: 14px;
  padding: 18px;
  border: 1px solid #eaedf3;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
  margin-bottom: 14px;
}

.stat-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e6eaf2;
  box-shadow: 0 2px 0 rgba(10, 25, 52, 0.08);
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-weight: 700;
  color: #fff;
}

.stat-icon.blue {
  background: #58a8ea;
}

.stat-icon.green {
  background: #54c081;
}

.stat-label {
  margin: 0;
  font-size: 12px;
  color: #7b8598;
}

.stat-value {
  margin: 0;
  font-size: 36px;
  line-height: 1;
  color: #122544;
  font-weight: 700;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.search-wrap {
  flex: 1;
  max-width: 420px;
}

.search-input,
.status-select {
  width: 100%;
  height: 40px;
  border: 1px solid #dce2ec;
  border-radius: 8px;
  padding: 0 12px;
  font-size: 14px;
  color: #25334a;
  background: #fff;
}

.filter-group {
  display: flex;
  gap: 8px;
  min-width: 250px;
}

.filter-btn {
  height: 40px;
  border: none;
  border-radius: 8px;
  background: #e28937;
  color: #fff;
  font-weight: 700;
  padding: 0 16px;
  cursor: pointer;
}

.table-wrap {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e6eaf2;
  overflow: hidden;
}

.table-state {
  padding: 14px;
  text-align: center;
  font-size: 13px;
  color: #607089;
}

.table-state.error {
  color: #a72e2e;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 12px;
  text-align: left;
}

th {
  color: #2a3b57;
  font-size: 13px;
  font-weight: 700;
  border-bottom: 1px solid #e7ebf2;
}

td {
  font-size: 13px;
  color: #2b3650;
  border-bottom: 1px solid #eef1f5;
}

.status-pill {
  display: inline-block;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.status-pill.en-route {
  background: #d3ecff;
  color: #2f7db7;
}

.status-pill.delivered {
  background: #daf5e3;
  color: #2a8d57;
}

.status-pill.pending {
  background: #f7edd9;
  color: #9a6f20;
}

.status-pill.failed {
  background: #fde5e5;
  color: #b44343;
}

.update-btn {
  display: inline-block;
  background: #e28937;
  color: #fff;
  text-decoration: none;
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 700;
}

.empty-row {
  text-align: center;
  color: #73809a;
  padding: 18px;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-top: 1px solid #e7ebf2;
}

.page-info {
  font-size: 13px;
  color: #607089;
}

@media (max-width: 900px) {
  .stat-grid {
    grid-template-columns: 1fr;
  }

  .toolbar {
    flex-direction: column;
  }

  .search-wrap,
  .filter-group {
    max-width: none;
    width: 100%;
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
