<template>
  <div class="audit-container">
    <div class="actions-bar">
      <div class="selection-state">
        <strong>Login Audit</strong>
        <span>Review successful logins, failed attempts, blocked inactive accounts, and logouts.</span>
      </div>
      <button class="btn btn-secondary" type="button" @click="loadLogs(pagination.current_page)" :disabled="loading">
        {{ loading ? 'Refreshing...' : 'Refresh' }}
      </button>
    </div>

    <div class="filters">
      <input
        v-model="filters.search"
        type="text"
        placeholder="Search user, username, email, or IP"
        class="search-box"
        @keyup.enter="applyFilters"
      />
      <select v-model="filters.event" class="filter-select" data-searchable="off" @change="applyFilters">
        <option value="">All Events</option>
        <option value="success">Successful Login</option>
        <option value="failed">Failed Login</option>
        <option value="blocked">Blocked Account</option>
        <option value="logout">Logout</option>
      </select>
      <input v-model="filters.date_from" class="filter-select" type="date" @change="applyFilters" />
      <input v-model="filters.date_to" class="filter-select" type="date" @change="applyFilters" />
      <button class="btn btn-secondary" type="button" @click="applyFilters">Search</button>
      <button class="btn btn-secondary btn-ghost" type="button" @click="clearFilters" :disabled="!hasFilters">Clear</button>
    </div>

    <p v-if="loading" class="state-message">Loading audit logs...</p>
    <p v-if="errorMessage" class="state-message error">{{ errorMessage }}</p>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Event</th>
            <th>User</th>
            <th>Identifier</th>
            <th>IP Address</th>
            <th>Reason</th>
            <th>User Agent</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="logs.length === 0 && !loading">
            <td colspan="7" class="no-data">No login audit records found.</td>
          </tr>
          <tr v-for="log in logs" :key="log.id">
            <td class="time-cell">{{ formatDateTime(log.created_at) }}</td>
            <td>
              <span class="event-badge" :class="log.event">{{ eventLabel(log.event) }}</span>
            </td>
            <td>
              <div class="user-cell">
                <strong>{{ log.user?.name || 'Unknown user' }}</strong>
                <span>{{ log.user?.role || '-' }}</span>
              </div>
            </td>
            <td>{{ log.identifier || '-' }}</td>
            <td>{{ log.ip_address || '-' }}</td>
            <td>{{ log.reason || '-' }}</td>
            <td class="agent-cell" :title="log.user_agent || ''">{{ log.user_agent || '-' }}</td>
          </tr>
        </tbody>
      </table>

      <div class="pagination" v-if="pagination.total > 0">
        <span class="page-info">
          Showing {{ paginationStart }}-{{ paginationEnd }} of {{ pagination.total }}
        </span>
        <button class="btn btn-secondary" type="button" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">Previous</button>
        <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button class="btn btn-secondary" type="button" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">Next</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api';

const logs = ref([]);
const loading = ref(false);
const errorMessage = ref('');
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const filters = ref({
  search: '',
  event: '',
  date_from: '',
  date_to: '',
});

const hasFilters = computed(() => Object.values(filters.value).some(Boolean));
const paginationStart = computed(() => {
  if (!pagination.value.total) return 0;
  return ((pagination.value.current_page - 1) * pagination.value.per_page) + 1;
});
const paginationEnd = computed(() => Math.min(
  pagination.value.current_page * pagination.value.per_page,
  pagination.value.total
));

const eventLabel = (event) => ({
  success: 'Success',
  failed: 'Failed',
  blocked: 'Blocked',
  logout: 'Logout',
}[event] || event);

const formatDateTime = (value) => {
  if (!value) return '-';
  return new Date(value).toLocaleString();
};

const loadLogs = async (page = 1) => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const response = await api.get('/login-audit-logs', {
      params: {
        page,
        per_page: pagination.value.per_page,
        ...filters.value,
      },
    });

    logs.value = response.data.data || [];
    pagination.value = response.data.pagination || pagination.value;
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to load login audit logs.';
  } finally {
    loading.value = false;
  }
};

const applyFilters = () => {
  loadLogs(1);
};

const clearFilters = () => {
  filters.value = {
    search: '',
    event: '',
    date_from: '',
    date_to: '',
  };
  loadLogs(1);
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  loadLogs(page);
};

onMounted(() => {
  loadLogs();
});
</script>

<style scoped>
.audit-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.actions-bar,
.filters,
.table-container {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 16px;
}

.selection-state {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.selection-state strong {
  color: #0a1d37;
  font-size: 16px;
}

.selection-state span {
  color: #64748b;
  font-size: 13px;
}

.filters {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 14px;
}

.search-box,
.filter-select {
  min-height: 40px;
  border: 1px solid #d8dee9;
  border-radius: 6px;
  color: #0f172a;
  font-size: 14px;
  padding: 9px 11px;
}

.search-box {
  flex: 1 1 280px;
}

.filter-select {
  flex: 0 1 170px;
  background: #fff;
}

.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 980px;
}

.data-table thead {
  background: #f8fafc;
}

.data-table th,
.data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #edf2f7;
  color: #334155;
  font-size: 14px;
  text-align: left;
  vertical-align: top;
}

.data-table th {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.data-table tbody tr:hover {
  background: #f9fafb;
}

.time-cell {
  white-space: nowrap;
}

.user-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-cell strong {
  color: #0f172a;
}

.user-cell span {
  color: #64748b;
  font-size: 12px;
  text-transform: capitalize;
}

.agent-cell {
  max-width: 280px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.event-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  padding: 5px 10px;
}

.event-badge.success {
  background: #dcfce7;
  color: #166534;
}

.event-badge.failed {
  background: #fee2e2;
  color: #991b1b;
}

.event-badge.blocked {
  background: #ffedd5;
  color: #9a3412;
}

.event-badge.logout {
  background: #dbeafe;
  color: #1e40af;
}

.state-message {
  margin: 0;
  padding: 12px 14px;
  border-radius: 6px;
  background: #eef6ff;
  color: #1d4ed8;
  font-size: 14px;
}

.state-message.error {
  background: #fee2e2;
  color: #991b1b;
}

.no-data {
  color: #64748b;
  text-align: center;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
}

.page-info {
  color: #64748b;
  font-size: 13px;
}

.btn {
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 700;
  min-height: 40px;
  padding: 10px 14px;
  transition: background-color 0.2s, opacity 0.2s;
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.btn-secondary {
  background: #f1f5f9;
  color: #0f172a;
}

.btn-secondary:hover:not(:disabled) {
  background: #e2e8f0;
}

.btn-ghost {
  background: #ffffff;
  border: 1px solid #d8dee9;
}

@media (max-width: 760px) {
  .actions-bar,
  .filters,
  .pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .filter-select,
  .search-box,
  .btn {
    width: 100%;
  }
}
</style>
