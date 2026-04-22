<template>
  <div class="movements-container">
    <div class="filters-bar">
      <div class="filter-group">
        <label>Movement Type:</label>
        <select v-model="filterType" data-searchable="off">
          <option value="">All Types</option>
          <option value="stock_in">Stock In</option>
          <option value="stock_out">Stock Out</option>
          <option value="defect">Defect</option>
          <option value="adjustment">Adjustment</option>
        </select>
      </div>

      <div class="filter-group">
        <label>From Date:</label>
        <input v-model="fromDate" type="date" />
      </div>

      <div class="filter-group">
        <label>To Date:</label>
        <input v-model="toDate" type="date" />
      </div>

      <div class="filter-group">
        <label>Sort By:</label>
        <select v-model="sortBy" data-searchable="off">
          <option value="created_at">Date &amp; Time</option>
          <option value="product">Product</option>
          <option value="type">Type</option>
          <option value="quantity">Quantity</option>
          <option value="expiration_date">Expiration Date</option>
          <option value="reference">Reference</option>
        </select>
      </div>

      <div class="filter-group">
        <label>Order:</label>
        <select v-model="sortDirection" data-searchable="off">
          <option value="desc">Descending</option>
          <option value="asc">Ascending</option>
        </select>
      </div>

      <button @click="applyFilters" class="btn-filter">Filter</button>
      <button @click="clearFilters" class="btn-clear">Clear Filters</button>
    </div>

    <div v-if="loading" class="state-card">Loading stock movements...</div>
    <div v-else-if="error" class="state-card error">{{ error }}</div>

    <table v-else class="movements-table">
      <thead>
        <tr>
          <th>Date & Time</th>
          <th>Product</th>
          <th>Type</th>
          <th>Quantity</th>
          <th>Expiration Date</th>
          <th>Reference</th>
          <th>Reason</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="displayedMovements.length === 0">
          <td colspan="8" class="no-data">No stock movements found for selected filters.</td>
        </tr>
        <tr v-for="movement in displayedMovements" :key="movement.id" :class="['movement-row', movement.badgeClass]">
          <td>{{ formatDate(movement.created_at) }}</td>
          <td>{{ movement.product }}</td>
          <td>
            <span class="badge" :class="movement.badgeClass">{{ getTypeLabel(movement) }}</span>
          </td>
          <td class="quantity" :class="movement.badgeClass">{{ getQuantityDisplay(movement) }}</td>
          <td>{{ movement.expiration_date ? formatExpirationDate(movement.expiration_date) : '—' }}</td>
          <td>{{ movement.reference || '—' }}</td>
          <td>{{ movement.reason || '—' }}</td>
          <td class="notes">{{ movement.notes || '—' }}</td>
        </tr>
      </tbody>
    </table>

    <div class="pagination">
      <button @click="previousPage" :disabled="currentPage === 1" class="btn-small">← Previous</button>
      <span class="page-info">Page {{ currentPage }} of {{ totalPages }}</span>
      <button @click="nextPage" :disabled="currentPage === totalPages" class="btn-small">Next →</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../api';

const filterType = ref('');
const fromDate = ref(getDefaultFromDate());
const toDate = ref(getDefaultToDate());
const sortBy = ref('created_at');
const sortDirection = ref('desc');
const currentPage = ref(1);
const itemsPerPage = 15;

const movements = ref([]);
const totalPages = ref(1);
const loading = ref(false);
const error = ref('');

function getDefaultFromDate() {
  const date = new Date();
  date.setDate(date.getDate() - 30);
  return date.toISOString().split('T')[0];
}

function getDefaultToDate() {
  return new Date().toISOString().split('T')[0];
}

const displayedMovements = computed(() => movements.value);

const fetchMovements = async () => {
  loading.value = true;
  error.value = '';

  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage,
      from_date: fromDate.value,
      to_date: toDate.value,
      sort_by: sortBy.value,
      sort_direction: sortDirection.value,
    };

    if (filterType.value) {
      params.type = filterType.value;
    }

    const response = await api.get('/inventory/movements', { params });

    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to fetch stock movements');
    }

    movements.value = (response.data.data || []).map((m) => ({
      id: m.id,
      created_at: m.created_at,
      product: m.product?.name || `Product #${m.product_id}`,
      type: m.type,
      movement_type: m.movement_type,
      quantity: Number(m.quantity || 0),
      reference: m.reference,
      reason: m.reason,
      badgeClass: m.movement_type === 'defect' ? 'defect' : m.type,
      notes: m.notes,
      expiration_date: m.expiration_date || null,
    }));

    totalPages.value = Math.max(1, response.data.pagination?.last_page || 1);
  } catch (err) {
    error.value = err.response?.data?.message || err.message || 'Failed to fetch stock movements';
    movements.value = [];
    totalPages.value = 1;
  } finally {
    loading.value = false;
  }
};

const formatExpirationDate = (dateStr) => {
  if (!dateStr) return '—';
  // Normalise to YYYY-MM-DD then append time so it parses as local, not UTC
  const datePart = String(dateStr).slice(0, 10);
  const date = new Date(datePart + 'T00:00:00');
  if (isNaN(date.getTime())) return datePart;
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getTypeLabel = (movement) => {
  if (movement.movement_type === 'defect') return 'Defect';
  if (movement.movement_type === 'shortage') return 'Shortage';
  if (movement.movement_type === 'theft') return 'Theft/Loss';
  const labels = { stock_in: 'Stock In', stock_out: 'Stock Out', adjustment: 'Adjustment' };
  return labels[movement.type] || movement.type;
};

const getQuantityDisplay = (movement) => {
  if (movement.movement_type === 'defect' || movement.movement_type === 'shortage' || movement.movement_type === 'theft' || movement.type === 'stock_out') {
    return `-${movement.quantity.toFixed(2)}`;
  }
  return `+${movement.quantity.toFixed(2)}`;
};

const applyFilters = () => {
  currentPage.value = 1;
  fetchMovements();
};

const clearFilters = () => {
  filterType.value = '';
  fromDate.value = getDefaultFromDate();
  toDate.value = getDefaultToDate();
  sortBy.value = 'created_at';
  sortDirection.value = 'desc';
  currentPage.value = 1;
  fetchMovements();
};

const previousPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--;
    fetchMovements();
  }
};

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++;
    fetchMovements();
  }
};

onMounted(() => {
  fetchMovements();
});
</script>

<style scoped>
.movements-container {
  animation: fadeIn 0.3s ease-in;
}

.header-section {
  margin-bottom: 25px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
}

.filters-bar {
  background: white;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 25px;
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.filter-group label {
  font-weight: 600;
  color: #666;
  font-size: 14px;
  white-space: nowrap;
}

.filter-group input,
.filter-group select {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.filter-group input:focus,
.filter-group select:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.btn-filter,
.btn-clear {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.3s;
}

.btn-filter {
  background-color: #e57c2a;
  color: white;
}

.btn-filter:hover {
  background-color: #d46a1a;
}

.btn-clear {
  background-color: #f0f0f0;
  color: #333;
}

.btn-clear:hover {
  background-color: #e0e0e0;
}

.state-card {
  background: white;
  border-radius: 8px;
  margin-bottom: 20px;
  padding: 16px;
  color: #495057;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.state-card.error {
  color: #b42318;
}

.movements-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  margin-bottom: 25px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  font-size: 14px;
}

.movements-table thead {
  background-color: #f9f9f9;
}

.movements-table th {
  padding: 15px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.movements-table td {
  padding: 12px 15px;
  border-bottom: 1px solid #e0e0e0;
}

.movement-row:hover {
  background-color: #f9f9f9;
}

.movement-row.stock_in {
  border-left: 4px solid #4caf50;
}

.movement-row.stock_out {
  border-left: 4px solid #f57c00;
}

.movement-row.adjustment {
  border-left: 4px solid #2196f3;
}

.movement-row.defect {
  border-left: 4px solid #b42318;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-weight: 600;
  font-size: 11px;
  text-transform: uppercase;
}

.badge.stock_in {
  background-color: #e8f5e9;
  color: #388e3c;
}

.badge.stock_out {
  background-color: #fff3e0;
  color: #f57c00;
}

.badge.adjustment {
  background-color: #e8f1ff;
  color: #1d4ed8;
}

.badge.defect {
  background-color: #fdecec;
  color: #b42318;
}

.badge.stock_out {
  background-color: #fff3e0;
  color: #f57c00;
}

.badge.adjustment {
  background-color: #e3f2fd;
  color: #1976d2;
}

.quantity {
  font-weight: 600;
}

.quantity.stock_in {
  color: #4caf50;
}

.quantity.stock_out {
  color: #f57c00;
}

.quantity.adjustment {
  color: #2196f3;
}

.notes {
  color: #999;
  font-size: 13px;
}

.no-data {
  text-align: center;
  color: #6b7280;
  font-style: italic;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  padding: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.btn-small {
  padding: 8px 16px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.btn-small:hover:not(:disabled) {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
}

.btn-small:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  font-weight: 600;
  color: #666;
  min-width: 120px;
  text-align: center;
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
</style>
