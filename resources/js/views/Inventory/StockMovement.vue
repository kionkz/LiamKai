<template>
  <div class="movements-container">
    <div class="header-section">
      <h1>Stock Movements</h1>
      <router-link to="/inventory" class="link-back">← Back to Inventory</router-link>
    </div>

    <div class="filters-bar">
      <div class="filter-group">
        <label>Movement Type:</label>
        <select v-model="filterType">
          <option value="">All Types</option>
          <option value="in">Stock In</option>
          <option value="out">Stock Out</option>
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

      <button @click="applyFilters" class="btn-filter">Filter</button>
      <button @click="clearFilters" class="btn-clear">Clear Filters</button>
    </div>

    <table class="movements-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Product</th>
          <th>Type</th>
          <th>Quantity</th>
          <th>Reference</th>
          <th>Reason</th>
          <th>Responsible Staff</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="movement in displayedMovements" :key="movement.id" :class="['movement-row', movement.type]">
          <td>{{ formatDate(movement.date) }}</td>
          <td>{{ movement.product }}</td>
          <td>
            <span class="badge" :class="movement.type">{{ getTypeLabel(movement.type) }}</span>
          </td>
          <td class="quantity" :class="movement.type">{{ getQuantityDisplay(movement) }}</td>
          <td>{{ movement.reference }}</td>
          <td>{{ movement.reason }}</td>
          <td>{{ movement.staff }}</td>
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
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const filterType = ref('');
const fromDate = ref(getDefaultFromDate());
const toDate = ref(getDefaultToDate());
const currentPage = ref(1);
const itemsPerPage = 15;

function getDefaultFromDate() {
  const date = new Date();
  date.setDate(date.getDate() - 30);
  return date.toISOString().split('T')[0];
}

function getDefaultToDate() {
  return new Date().toISOString().split('T')[0];
}

const movements = ref([
  { id: 1, date: '2024-02-18', product: 'Laptop Computer', type: 'in', quantity: 5, reference: 'PO-1001', reason: 'Purchase Order Receipt', staff: 'John Doe', notes: 'Received from supplier ABC' },
  { id: 2, date: '2024-02-18', product: 'USB Cable', type: 'out', quantity: 20, reference: 'ORD-2045', reason: 'Sales Order Fulfillment', staff: 'Jane Smith', notes: 'Retail order' },
  { id: 3, date: '2024-02-17', product: 'Monitor 27"', type: 'adjustment', quantity: -2, reference: 'INV-ADJ-001', reason: 'Damage/Defect Adjustment', staff: 'Mike Johnson', notes: 'Damaged in warehouse' },
  { id: 4, date: '2024-02-17', product: 'Keyboard Mechanical', type: 'in', quantity: 10, reference: 'PO-1000', reason: 'Purchase Order Receipt', staff: 'Sarah Lee', notes: '' },
  { id: 5, date: '2024-02-16', product: 'Mouse Wireless', type: 'out', quantity: 3, reference: 'ORD-2044', reason: 'Sales Order Fulfillment', staff: 'John Doe', notes: 'Wholesale order' },
  { id: 6, date: '2024-02-16', product: 'Software License - Pro', type: 'in', quantity: 50, reference: 'PO-0999', reason: 'Purchase Order Receipt', staff: 'Jane Smith', notes: 'License renewal' },
  { id: 7, date: '2024-02-15', product: 'Printer Inkjet', type: 'out', quantity: 1, reference: 'ORD-2043', reason: 'Sales Order Fulfillment', staff: 'Mike Johnson', notes: '' },
  { id: 8, date: '2024-02-15', product: 'Laptop Computer', type: 'adjustment', quantity: 1, reference: 'INV-ADJ-002', reason: 'Inventory Count Adjustment', staff: 'Sarah Lee', notes: 'Physical count discrepancy' },
  { id: 9, date: '2024-02-14', product: 'USB Cable', type: 'in', quantity: 100, reference: 'PO-0998', reason: 'Purchase Order Receipt', staff: 'John Doe', notes: '' },
  { id: 10, date: '2024-02-14', product: 'Monitor 27"', type: 'out', quantity: 2, reference: 'ORD-2042', reason: 'Sales Order Fulfillment', staff: 'Jane Smith', notes: 'Bulk order' },
]);

const filteredMovements = computed(() => {
  return movements.value.filter(m => {
    const typeMatch = !filterType.value || m.type === filterType.value;
    const dateMatch = m.date >= fromDate.value && m.date <= toDate.value;
    return typeMatch && dateMatch;
  });
});

const displayedMovements = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return filteredMovements.value.slice(start, end);
});

const totalPages = computed(() => {
  return Math.ceil(filteredMovements.value.length / itemsPerPage);
});

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const getTypeLabel = (type) => {
  const labels = { in: 'In', out: 'Out', adjustment: 'Adjustment' };
  return labels[type] || type;
};

const getQuantityDisplay = (movement) => {
  if (movement.type === 'out' || movement.type === 'adjustment') {
    return movement.type === 'adjustment' && movement.quantity > 0 ? `+${movement.quantity}` : movement.quantity;
  }
  return `+${movement.quantity}`;
};

const applyFilters = () => {
  currentPage.value = 1;
};

const clearFilters = () => {
  filterType.value = '';
  fromDate.value = getDefaultFromDate();
  toDate.value = getDefaultToDate();
  currentPage.value = 1;
};

const previousPage = () => {
  if (currentPage.value > 1) currentPage.value--;
};

const nextPage = () => {
  if (currentPage.value < totalPages.value) currentPage.value++;
};
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

.link-back {
  color: #e57c2a;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.3s;
}

.link-back:hover {
  color: #d46a1a;
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

.movement-row.in {
  border-left: 4px solid #4caf50;
}

.movement-row.out {
  border-left: 4px solid #f57c00;
}

.movement-row.adjustment {
  border-left: 4px solid #2196f3;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-weight: 600;
  font-size: 11px;
  text-transform: uppercase;
}

.badge.in {
  background-color: #e8f5e9;
  color: #388e3c;
}

.badge.out {
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

.quantity.in {
  color: #4caf50;
}

.quantity.out {
  color: #f57c00;
}

.quantity.adjustment {
  color: #2196f3;
}

.notes {
  color: #999;
  font-size: 13px;
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
