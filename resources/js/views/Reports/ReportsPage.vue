<template>
  <div class="reports-container">
    <div class="header-section">
      <h1>Business Reports</h1>
    </div>

    <div class="report-controls">
      <div class="control-group">
        <label>Report Type:</label>
        <select v-model="selectedReport" @change="updateReportData">
          <option value="sales">Sales Report</option>
          <option value="payments">Payment Report</option>
          <option value="inventory">Inventory Report</option>
          <option value="customers">Customer Report</option>
        </select>
      </div>

      <div class="control-group">
        <label>Period:</label>
        <select v-model="reportPeriod" @change="updateReportData">
          <option value="today">Today</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="custom">Custom Range</option>
        </select>
      </div>

      <div v-if="reportPeriod === 'custom'" class="date-range">
        <input v-model="fromDate" type="date" />
        <span>to</span>
        <input v-model="toDate" type="date" />
      </div>

      <button @click="generateReport" class="btn btn-primary">Generate Report</button>
      <button @click="exportPDF" class="btn btn-secondary">📄 Export PDF</button>
      <button @click="printReport" class="btn btn-secondary">🖨️ Print</button>
    </div>

    <!-- Sales Report -->
    <div v-if="selectedReport === 'sales'" class="report-section">
      <h2>Sales Report - {{ getReportTitle() }}</h2>

      <div class="metrics-grid">
        <div class="metric-card">
          <p class="metric-label">Total Sales</p>
          <p class="metric-value">₱{{ totalSales.toFixed(2) }}</p>
          <p class="metric-change">+12.5% from last period</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Total Orders</p>
          <p class="metric-value">{{ totalOrders }}</p>
          <p class="metric-change">+8 orders</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Average Order Value</p>
          <p class="metric-value">₱{{ avgOrderValue.toFixed(2) }}</p>
          <p class="metric-change">+5.2% from last period</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Retail vs Wholesale</p>
          <p class="metric-value">{{ retailPercentage }}% / {{ wholesalePercentage }}%</p>
          <p class="metric-change">Balanced mix</p>
        </div>
      </div>

      <h3>Daily Sales Breakdown</h3>
      <table class="report-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Orders</th>
            <th>Retail Sales</th>
            <th>Wholesale Sales</th>
            <th>Total Sales</th>
            <th>Avg Order Value</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in salesData" :key="idx">
            <td>{{ row.date }}</td>
            <td>{{ row.orders }}</td>
            <td>₱{{ row.retail.toFixed(2) }}</td>
            <td>₱{{ row.wholesale.toFixed(2) }}</td>
            <td class="total">₱{{ (row.retail + row.wholesale).toFixed(2) }}</td>
            <td>₱{{ ((row.retail + row.wholesale) / row.orders).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Payment Report -->
    <div v-if="selectedReport === 'payments'" class="report-section">
      <h2>Payment Report - {{ getReportTitle() }}</h2>

      <div class="metrics-grid">
        <div class="metric-card">
          <p class="metric-label">Total Collected</p>
          <p class="metric-value">₱{{ totalCollected.toFixed(2) }}</p>
          <p class="metric-change">+9.3% from last period</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Outstanding</p>
          <p class="metric-value">₱{{ totalOutstanding.toFixed(2) }}</p>
          <p class="metric-change">-3.1% from last period</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Collection Rate</p>
          <p class="metric-value">{{ collectionRate }}%</p>
          <p class="metric-change">Healthy</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Payment Methods</p>
          <p class="metric-value">Cash / Card / COD</p>
          <p class="metric-change">Well distributed</p>
        </div>
      </div>

      <h3>Payment Method Summary</h3>
      <table class="report-table">
        <thead>
          <tr>
            <th>Method</th>
            <th>Transactions</th>
            <th>Amount</th>
            <th>% of Total</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Cash</td>
            <td>24</td>
            <td>₱{{ (totalCollected * 0.45).toFixed(2) }}</td>
            <td>45%</td>
          </tr>
          <tr>
            <td>Card</td>
            <td>18</td>
            <td>₱{{ (totalCollected * 0.35).toFixed(2) }}</td>
            <td>35%</td>
          </tr>
          <tr>
            <td>COD</td>
            <td>10</td>
            <td>₱{{ (totalCollected * 0.20).toFixed(2) }}</td>
            <td>20%</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Inventory Report -->
    <div v-if="selectedReport === 'inventory'" class="report-section">
      <h2>Inventory Report - {{ getReportTitle() }}</h2>

      <div class="metrics-grid">
        <div class="metric-card">
          <p class="metric-label">Total SKUs</p>
          <p class="metric-value">{{ totalSKUs }}</p>
          <p class="metric-change">Actively managed</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Low Stock Items</p>
          <p class="metric-value">{{ lowStockCount }}</p>
          <p class="metric-change" style="color: #ff9800;">Attention needed</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Total Inventory Value</p>
          <p class="metric-value">₱{{ totalInventoryValue.toFixed(2) }}</p>
          <p class="metric-change">At current prices</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Stock Turnover</p>
          <p class="metric-value">4.2x</p>
          <p class="metric-change">Good velocity</p>
        </div>
      </div>

      <h3>Low Stock Alert Items</h3>
      <table class="report-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Current Stock</th>
            <th>Reorder Level</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, idx) in lowStockItems" :key="idx">
            <td>{{ item.product }}</td>
            <td>{{ item.current }}</td>
            <td>{{ item.reorder }}</td>
            <td><span class="badge warning">Reorder</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Customer Report -->
    <div v-if="selectedReport === 'customers'" class="report-section">
      <h2>Customer Report - {{ getReportTitle() }}</h2>

      <div class="metrics-grid">
        <div class="metric-card">
          <p class="metric-label">Total Customers</p>
          <p class="metric-value">{{ totalCustomers }}</p>
          <p class="metric-change">Active customers</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">New Customers</p>
          <p class="metric-value">{{ newCustomers }}</p>
          <p class="metric-change">+15% growth</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Avg Customer Value</p>
          <p class="metric-value">₱{{ avgCustomerValue.toFixed(2) }}</p>
          <p class="metric-change">Lifetime revenue</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Repeat Rate</p>
          <p class="metric-value">{{ repeatRate }}%</p>
          <p class="metric-change">Loyalty indicator</p>
        </div>
      </div>

      <h3>Top Customers</h3>
      <table class="report-table">
        <thead>
          <tr>
            <th>Customer</th>
            <th>Type</th>
            <th>Orders</th>
            <th>Total Spent</th>
            <th>Last Order</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(cust, idx) in topCustomers" :key="idx">
            <td>{{ cust.name }}</td>
            <td><span class="badge" :class="cust.type">{{ cust.type }}</span></td>
            <td>{{ cust.orders }}</td>
            <td>₱{{ cust.spent.toFixed(2) }}</td>
            <td>{{ cust.lastOrder }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const selectedReport = ref('sales');
const reportPeriod = ref('month');
const fromDate = ref('2024-02-01');
const toDate = ref('2024-02-18');

// Sales Data
const salesData = ref([
  { date: 'Feb 18', orders: 12, retail: 45000, wholesale: 35000 },
  { date: 'Feb 17', orders: 10, retail: 38000, wholesale: 32000 },
  { date: 'Feb 16', orders: 15, retail: 52000, wholesale: 48000 },
  { date: 'Feb 15', orders: 8, retail: 28000, wholesale: 22000 },
]);

const totalSales = computed(() => salesData.value.reduce((sum, row) => sum + row.retail + row.wholesale, 0));
const totalOrders = computed(() => salesData.value.reduce((sum, row) => sum + row.orders, 0));
const avgOrderValue = computed(() => totalSales.value / totalOrders.value);
const retailPercentage = computed(() => {
  const retail = salesData.value.reduce((sum, row) => sum + row.retail, 0);
  return ((retail / totalSales.value) * 100).toFixed(1);
});
const wholesalePercentage = computed(() => (100 - parseFloat(retailPercentage.value)).toFixed(1));

// Payment Data
const totalCollected = ref(325000);
const totalOutstanding = ref(48000);
const collectionRate = computed(() => ((totalCollected.value / (totalCollected.value + totalOutstanding.value)) * 100).toFixed(1));

// Inventory Data
const totalSKUs = 48;
const lowStockCount = 6;
const totalInventoryValue = ref(2450000);

const lowStockItems = [
  { product: 'Mouse Wireless', current: 2, reorder: 10 },
  { product: 'Monitor 27"', current: 3, reorder: 5 },
  { product: 'USB Cable', current: 5, reorder: 20 },
];

// Customer Data
const totalCustomers = 156;
const newCustomers = 12;
const repeatRate = 68;
const avgCustomerValue = computed(() => totalCollected.value / totalCustomers);

const topCustomers = [
  { name: 'ABC Corporation', type: 'wholesale', orders: 24, spent: 450000, lastOrder: 'Feb 15' },
  { name: 'Golden Tech Retail', type: 'retail', orders: 18, spent: 285000, lastOrder: 'Feb 18' },
  { name: 'Metro Hardware', type: 'wholesale', orders: 15, spent: 220000, lastOrder: 'Feb 16' },
];

const getReportTitle = () => {
  const titles = {
    today: 'Today',
    week: 'This Week',
    month: 'February 2024',
    custom: `${fromDate.value} to ${toDate.value}`,
  };
  return titles[reportPeriod.value];
};

const updateReportData = () => {
  console.log('Report updated:', selectedReport.value, reportPeriod.value);
};

const generateReport = () => {
  alert(`Generated ${selectedReport.value} report for ${getReportTitle()}`);
};

const exportPDF = () => {
  alert(`Exporting ${selectedReport.value} report as PDF...`);
  // In production, would generate actual PDF
};

const printReport = () => {
  window.print();
};
</script>

<style scoped>
.reports-container {
  animation: fadeIn 0.3s ease-in;
}

.header-section {
  margin-bottom: 25px;
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
}

.report-controls {
  background: white;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 25px;
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  align-items: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.control-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.control-group label {
  font-weight: 600;
  color: #666;
  font-size: 14px;
  white-space: nowrap;
}

.control-group select {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  background: white;
}

.control-group select:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.date-range {
  display: flex;
  gap: 10px;
  align-items: center;
}

.date-range input {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.date-range input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
  font-size: 14px;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover {
  background-color: #d46a1a;
}

.btn-secondary {
  background-color: #f0f0f0;
  color: #333;
}

.btn-secondary:hover {
  background-color: #e0e0e0;
}

.report-section {
  animation: slideIn 0.3s ease-in;
}

.report-section h2 {
  color: #0a1d37;
  margin-top: 0;
  margin-bottom: 20px;
}

.report-section h3 {
  color: #333;
  margin-top: 25px;
  margin-bottom: 15px;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 30px;
}

.metric-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  border-left: 4px solid #e57c2a;
}

.metric-label {
  margin: 0;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
}

.metric-value {
  margin: 8px 0;
  font-size: 24px;
  font-weight: 700;
  color: #0a1d37;
}

.metric-change {
  margin: 0;
  font-size: 12px;
  color: #4caf50;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px;
}

.report-table thead {
  background-color: #f9f9f9;
}

.report-table th {
  padding: 15px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.report-table td {
  padding: 12px 15px;
  border-bottom: 1px solid #e0e0e0;
}

.report-table tbody tr:hover {
  background-color: #f9f9f9;
}

.report-table td.total {
  font-weight: 600;
  color: #e57c2a;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.badge.retail {
  background-color: #e3f2fd;
  color: #1976d2;
}

.badge.wholesale {
  background-color: #f3e5f5;
  color: #7b1fa2;
}

.badge.warning {
  background-color: #fff3e0;
  color: #f57c00;
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

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@media (max-width: 768px) {
  .report-controls {
    flex-direction: column;
    align-items: stretch;
  }

  .control-group {
    flex-direction: column;
  }

  .metrics-grid {
    grid-template-columns: 1fr;
  }

  .report-table {
    font-size: 13px;
  }

  .report-table th,
  .report-table td {
    padding: 10px;
  }
}
</style>
