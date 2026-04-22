<template>
  <div class="reports-container">
    <div class="report-controls">
      <div class="control-group">
        <label>Report Type:</label>
        <select v-model="selectedReport" data-searchable="off" @change="updateReportData">
          <option value="sales">Sales Report</option>
          <option value="payments">Payment Report</option>
          <option value="inventory">Inventory Report</option>
          <option value="customers">Customer Report</option>
        </select>
      </div>

      <div class="control-group">
        <label>Period:</label>
        <select v-model="reportPeriod" data-searchable="off" @change="updateReportData">
          <option value="today">Today</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="all">All</option>
          <option value="custom">Custom Range</option>
        </select>
      </div>

      <div v-if="reportPeriod === 'custom'" class="date-range">
        <input v-model="fromDate" type="date" />
        <span>to</span>
        <input v-model="toDate" type="date" />
      </div>

      <button @click="generateReport" class="btn btn-primary">Generate Report</button>
      <button @click="exportPDF" class="btn btn-secondary">Export PDF</button>
      <button @click="printReport" class="btn btn-secondary">Print</button>
    </div>

    <p v-if="loading" class="report-state">Loading report data...</p>
    <p v-if="successMessage" class="report-state success">{{ successMessage }}</p>
    <p v-if="errorMessage" class="report-state error">{{ errorMessage }}</p>

    <!-- Sales Report -->
    <div v-if="selectedReport === 'sales'" class="report-section">
      <h2>Sales Report - {{ getReportTitle() }}</h2>

      <div class="metrics-grid">
        <div class="metric-card">
          <p class="metric-label">Total Sales</p>
          <p class="metric-value">₱{{ totalSales.toFixed(2) }}</p>
          <p class="metric-change">Recorded sales for the selected period</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Total Orders</p>
          <p class="metric-value">{{ totalOrders }}</p>
          <p class="metric-change">Completed orders included in this range</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Average Order Value</p>
          <p class="metric-value">₱{{ avgOrderValue.toFixed(2) }}</p>
          <p class="metric-change">Average revenue per order</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Retail vs Discounted</p>
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
            <th>Discounted Sales</th>
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
          <p class="metric-change">Completed payment collections</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Outstanding</p>
          <p class="metric-value">₱{{ totalOutstanding.toFixed(2) }}</p>
          <p class="metric-change">Open receivables still unpaid</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Collection Rate</p>
          <p class="metric-value">{{ collectionRate }}%</p>
          <p class="metric-change">Healthy</p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Payment Methods</p>
          <p class="metric-value">{{ paymentMethods.length }}</p>
          <p class="metric-change">Payment channels used in this period</p>
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
          <tr v-for="(method, idx) in paymentMethods" :key="idx">
            <td>{{ method.method }}</td>
            <td>{{ method.transactions }}</td>
            <td>₱{{ method.amount.toFixed(2) }}</td>
            <td>{{ method.percentage }}%</td>
          </tr>
          <tr v-if="paymentMethods.length === 0">
            <td colspan="4">No payment records found for this period.</td>
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
          <p class="metric-value">{{ totalSales > 0 && totalInventoryValue > 0 ? (totalSales / totalInventoryValue).toFixed(2) + 'x' : '0.00x' }}</p>
          <p class="metric-change">Sales value relative to current inventory value</p>
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
          <p class="metric-change">Customers added during this period</p>
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
import { ref, computed, onMounted } from 'vue';
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import api from '../../api';

const selectedReport = ref('sales');
const reportPeriod = ref('month');
const fromDate = ref('');
const toDate = ref('');
const loading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const salesData = ref([]);
const paymentMethods = ref([]);
const lowStockItems = ref([]);
const topCustomers = ref([]);

const totalSales = ref(0);
const totalOrders = ref(0);
const avgOrderValue = ref(0);
const retailPercentage = ref(0);
const wholesalePercentage = ref(0);

const totalCollected = ref(0);
const totalOutstanding = ref(0);
const collectionRate = ref(0);

const totalSKUs = ref(0);
const lowStockCount = ref(0);
const totalInventoryValue = ref(0);

const totalCustomers = ref(0);
const newCustomers = ref(0);
const repeatRate = ref(0);
const avgCustomerValue = ref(0);

const getReportTitle = () => {
  const titles = {
    today: 'Today',
    week: 'This Week',
    month: 'This Month',
    all: 'All Time',
    custom: `${fromDate.value} to ${toDate.value}`,
  };
  return titles[reportPeriod.value];
};

const fetchReportData = async () => {
  loading.value = true;
  successMessage.value = '';
  errorMessage.value = '';

  try {
    const params = {
      period: reportPeriod.value,
    };

    if (reportPeriod.value === 'custom' && fromDate.value && toDate.value) {
      params.from_date = fromDate.value;
      params.to_date = toDate.value;
    }

    const response = await api.get('/reports/analytics', { params });

    if (!response.data?.success) {
      errorMessage.value = response.data?.message || 'Failed to load report data';
      return;
    }

    const reportData = response.data.data;

    totalSales.value = Number(reportData.sales?.totalSales || 0);
    totalOrders.value = Number(reportData.sales?.totalOrders || 0);
    avgOrderValue.value = Number(reportData.sales?.avgOrderValue || 0);
    retailPercentage.value = Number(reportData.sales?.retailPercentage || 0);
    wholesalePercentage.value = Number(reportData.sales?.wholesalePercentage || 0);
    salesData.value = reportData.sales?.dailyBreakdown || [];

    totalCollected.value = Number(reportData.payments?.totalCollected || 0);
    totalOutstanding.value = Number(reportData.payments?.totalOutstanding || 0);
    collectionRate.value = Number(reportData.payments?.collectionRate || 0);
    paymentMethods.value = reportData.payments?.methods || [];

    totalSKUs.value = Number(reportData.inventory?.totalSKUs || 0);
    lowStockCount.value = Number(reportData.inventory?.lowStockCount || 0);
    totalInventoryValue.value = Number(reportData.inventory?.totalInventoryValue || 0);
    lowStockItems.value = reportData.inventory?.lowStockItems || [];

    totalCustomers.value = Number(reportData.customers?.totalCustomers || 0);
    newCustomers.value = Number(reportData.customers?.newCustomers || 0);
    repeatRate.value = Number(reportData.customers?.repeatRate || 0);
    avgCustomerValue.value = Number(reportData.customers?.avgCustomerValue || 0);
    topCustomers.value = reportData.customers?.topCustomers || [];
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to load report data';
  } finally {
    loading.value = false;
  }
};

const updateReportData = () => {
  fetchReportData();
};

const generateReport = () => {
  if (reportPeriod.value === 'custom' && (!fromDate.value || !toDate.value)) {
    errorMessage.value = 'Choose both start and end dates for a custom report.';
    return;
  }

  if (selectedReport.value !== 'sales') {
    fetchReportData();
    return;
  }

  const params = {
    period: reportPeriod.value,
  };

  if (reportPeriod.value === 'custom' && fromDate.value && toDate.value) {
    params.from_date = fromDate.value;
    params.to_date = toDate.value;
  }

  loading.value = true;
  successMessage.value = '';
  errorMessage.value = '';

  api.post('/reports/sales/generate', null, { params })
    .then((response) => {
      if (response.data?.success) {
        const savedRows = response.data?.data?.saved_rows ?? 0;
        successMessage.value = `Sales report saved to database (${savedRows} row(s)).`;
        fetchReportData();
        return;
      }

      errorMessage.value = response.data?.message || 'Failed to save sales report';
    })
    .catch((error) => {
      errorMessage.value = error.response?.data?.message || 'Failed to save sales report';
    })
    .finally(() => {
      loading.value = false;
    });
};

const formatPeso = (value) => `P${Number(value || 0).toFixed(2)}`;

const addSectionHeader = (doc, y, title) => {
  doc.setFontSize(12);
  doc.setTextColor(10, 29, 55);
  doc.text(title, 14, y);
  return y + 6;
};

const exportPDF = () => {
  const doc = new jsPDF();
  const periodTitle = getReportTitle();
  const nowLabel = new Date().toLocaleString();

  doc.setFontSize(16);
  doc.setTextColor(10, 29, 55);
  doc.text('LiamKai Business Report', 14, 16);

  doc.setFontSize(11);
  doc.setTextColor(70, 70, 70);
  doc.text(`Type: ${selectedReport.value.toUpperCase()}`, 14, 24);
  doc.text(`Period: ${periodTitle}`, 14, 30);
  doc.text(`Generated: ${nowLabel}`, 14, 36);

  let currentY = 44;

  if (selectedReport.value === 'sales') {
    currentY = addSectionHeader(doc, currentY, 'Summary');
    autoTable(doc, {
      startY: currentY,
      head: [['Metric', 'Value']],
      body: [
        ['Total Sales', formatPeso(totalSales.value)],
        ['Total Orders', String(totalOrders.value)],
        ['Average Order Value', formatPeso(avgOrderValue.value)],
        ['Retail vs Discounted', `${retailPercentage.value}% / ${wholesalePercentage.value}%`],
      ],
      styles: { fontSize: 10 },
    });

    currentY = doc.lastAutoTable.finalY + 10;
    currentY = addSectionHeader(doc, currentY, 'Daily Sales Breakdown');
    autoTable(doc, {
      startY: currentY,
      head: [['Date', 'Orders', 'Retail Sales', 'Discounted Sales', 'Total Sales', 'Avg Order']],
      body: salesData.value.map((row) => {
        const total = Number(row.retail || 0) + Number(row.wholesale || 0);
        return [
          row.date,
          String(row.orders),
          formatPeso(row.retail),
          formatPeso(row.wholesale),
          formatPeso(total),
          formatPeso(row.orders ? total / row.orders : 0),
        ];
      }),
      styles: { fontSize: 9 },
    });
  }

  if (selectedReport.value === 'payments') {
    currentY = addSectionHeader(doc, currentY, 'Summary');
    autoTable(doc, {
      startY: currentY,
      head: [['Metric', 'Value']],
      body: [
        ['Total Collected', formatPeso(totalCollected.value)],
        ['Outstanding', formatPeso(totalOutstanding.value)],
        ['Collection Rate', `${collectionRate.value}%`],
      ],
      styles: { fontSize: 10 },
    });

    currentY = doc.lastAutoTable.finalY + 10;
    currentY = addSectionHeader(doc, currentY, 'Payment Methods');
    autoTable(doc, {
      startY: currentY,
      head: [['Method', 'Transactions', 'Amount', '% of Total']],
      body: paymentMethods.value.map((method) => [
        method.method,
        String(method.transactions),
        formatPeso(method.amount),
        `${method.percentage}%`,
      ]),
      styles: { fontSize: 9 },
    });
  }

  if (selectedReport.value === 'inventory') {
    currentY = addSectionHeader(doc, currentY, 'Summary');
    autoTable(doc, {
      startY: currentY,
      head: [['Metric', 'Value']],
      body: [
        ['Total SKUs', String(totalSKUs.value)],
        ['Low Stock Items', String(lowStockCount.value)],
        ['Total Inventory Value', formatPeso(totalInventoryValue.value)],
      ],
      styles: { fontSize: 10 },
    });

    currentY = doc.lastAutoTable.finalY + 10;
    currentY = addSectionHeader(doc, currentY, 'Low Stock Items');
    autoTable(doc, {
      startY: currentY,
      head: [['Product', 'Current', 'Reorder Level']],
      body: lowStockItems.value.map((item) => [
        item.product,
        String(item.current),
        String(item.reorder),
      ]),
      styles: { fontSize: 9 },
    });
  }

  if (selectedReport.value === 'customers') {
    currentY = addSectionHeader(doc, currentY, 'Summary');
    autoTable(doc, {
      startY: currentY,
      head: [['Metric', 'Value']],
      body: [
        ['Total Customers', String(totalCustomers.value)],
        ['New Customers', String(newCustomers.value)],
        ['Repeat Rate', `${repeatRate.value}%`],
        ['Average Customer Value', formatPeso(avgCustomerValue.value)],
      ],
      styles: { fontSize: 10 },
    });

    currentY = doc.lastAutoTable.finalY + 10;
    currentY = addSectionHeader(doc, currentY, 'Top Customers');
    autoTable(doc, {
      startY: currentY,
      head: [['Customer', 'Type', 'Orders', 'Total Spent', 'Last Order']],
      body: topCustomers.value.map((cust) => [
        cust.name,
        cust.type,
        String(cust.orders),
        formatPeso(cust.spent),
        cust.lastOrder,
      ]),
      styles: { fontSize: 9 },
    });
  }

  const fileDate = new Date().toISOString().slice(0, 10);
  doc.save(`liamkai-${selectedReport.value}-report-${fileDate}.pdf`);
};

const printReport = () => {
  exportPDF();
};

onMounted(() => {
  fetchReportData();
});
</script>

<style scoped>
.reports-container {
  animation: fadeIn 0.3s ease-in;
}

.header-section {
  margin-bottom: 25px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
}

.report-state {
  margin: 0 0 12px 0;
  color: #334155;
  font-weight: 500;
}

.report-state.error {
  color: #b91c1c;
}

.report-state.success {
  color: #166534;
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

.btn-back {
  border: 1px solid #ddd;
}

.btn-back:hover {
  background-color: #e57c2a;
  color: #fff;
  border-color: #e57c2a;
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
