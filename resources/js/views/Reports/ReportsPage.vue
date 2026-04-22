<template>
  <div class="reports-container">
    <div class="report-controls">
      <div class="control-group">
        <label>Report Type:</label>
        <select v-model="selectedReport" data-searchable="off" @change="handleReportTypeChange">
          <option value="sales">Sales Report</option>
          <option value="payments">Payment Report</option>
          <option value="inventory">Inventory Report</option>
          <option value="customers">Customer Report</option>
        </select>
      </div>

      <div class="control-group">
        <label>Period:</label>
        <select v-model="reportPeriod" data-searchable="off" @change="handleFilterChange">
          <option value="today">Today</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="all">All</option>
          <option value="custom">Custom Range</option>
        </select>
      </div>

      <div v-if="reportPeriod === 'custom'" class="date-range">
        <input v-model="fromDate" type="date" @change="handleFilterChange" />
        <span>to</span>
        <input v-model="toDate" type="date" @change="handleFilterChange" />
      </div>

      <div class="control-group">
        <label>Sort By:</label>
        <select v-model="sortField" data-searchable="off">
          <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>

      <div class="control-group">
        <label>Order:</label>
        <select v-model="sortDirection" data-searchable="off">
          <option value="asc">Ascending</option>
          <option value="desc">Descending</option>
        </select>
      </div>

      <button @click="generateReport" class="btn btn-primary" :disabled="loading">{{ loading ? 'Generating...' : 'Generate Report' }}</button>
    </div>

    <p v-if="successMessage" class="report-state success">{{ successMessage }}</p>
    <p v-if="errorMessage" class="report-state error">{{ errorMessage }}</p>

    <div v-if="reportGenerated" class="report-preview-card">
      <div class="preview-header">
        <div>
          <p class="preview-eyebrow">Generated Report</p>
          <h2>{{ reportHeading }}</h2>
          <p class="preview-subtitle">{{ reportSubtitle }}</p>
        </div>
        <div class="preview-meta">
          <span>{{ selectedReportLabel }}</span>
          <span>{{ generatedAtLabel }}</span>
        </div>
      </div>

      <div class="report-section">
        <div v-if="selectedReport === 'sales'">
          <div class="metrics-grid">
            <div class="metric-card">
              <p class="metric-label">Total Sales</p>
              <p class="metric-value">{{ formatPeso(totalSales) }}</p>
              <p class="metric-change">Recorded sales for the selected period</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Total Orders</p>
              <p class="metric-value">{{ totalOrders }}</p>
              <p class="metric-change">Completed orders included in this range</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Average Order Value</p>
              <p class="metric-value">{{ formatPeso(avgOrderValue) }}</p>
              <p class="metric-change">Average revenue per order</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Retail vs Wholesale</p>
              <p class="metric-value">{{ retailPercentage }}% / {{ wholesalePercentage }}%</p>
              <p class="metric-change">Share of sales mix</p>
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
              <tr v-for="(row, idx) in sortedSalesData" :key="`${row.dateValue}-${idx}`">
                <td>{{ row.date }}</td>
                <td>{{ row.orders }}</td>
                <td>{{ formatPeso(row.retail) }}</td>
                <td>{{ formatPeso(row.wholesale) }}</td>
                <td class="total">{{ formatPeso(row.retail + row.wholesale) }}</td>
                <td>{{ formatPeso(row.orders ? (row.retail + row.wholesale) / row.orders : 0) }}</td>
              </tr>
              <tr v-if="sortedSalesData.length === 0">
                <td colspan="6">No sales records found for this report.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else-if="selectedReport === 'payments'">
          <div class="metrics-grid">
            <div class="metric-card">
              <p class="metric-label">Total Collected</p>
              <p class="metric-value">{{ formatPeso(totalCollected) }}</p>
              <p class="metric-change">Completed payment collections</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Outstanding</p>
              <p class="metric-value">{{ formatPeso(totalOutstanding) }}</p>
              <p class="metric-change">Open receivables still unpaid</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Collection Rate</p>
              <p class="metric-value">{{ collectionRate }}%</p>
              <p class="metric-change">Collected versus collectible balance</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Payment Methods</p>
              <p class="metric-value">{{ paymentMethods.length }}</p>
              <p class="metric-change">Channels used in this period</p>
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
              <tr v-for="(method, idx) in sortedPaymentMethods" :key="`${method.method}-${idx}`">
                <td>{{ method.method }}</td>
                <td>{{ method.transactions }}</td>
                <td>{{ formatPeso(method.amount) }}</td>
                <td>{{ method.percentage }}%</td>
              </tr>
              <tr v-if="sortedPaymentMethods.length === 0">
                <td colspan="4">No payment records found for this report.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else-if="selectedReport === 'inventory'">
          <div class="metrics-grid">
            <div class="metric-card">
              <p class="metric-label">Total SKUs</p>
              <p class="metric-value">{{ totalSKUs }}</p>
              <p class="metric-change">Items tracked in inventory</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Quantity On Hand</p>
              <p class="metric-value">{{ formatQuantity(currentStockQuantity) }}</p>
              <p class="metric-change">Units currently available</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Low Stock Items</p>
              <p class="metric-value">{{ lowStockCount }}</p>
              <p class="metric-change">At or below reorder point</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Total Inventory Value</p>
              <p class="metric-value">{{ formatPeso(totalInventoryValue) }}</p>
              <p class="metric-change">Based on unit cost</p>
            </div>
          </div>

          <h3>Inventory Listing</h3>
          <table class="report-table">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Name</th>
                <th>Description</th>
                <th>Quantity On Hand</th>
                <th>Unit Cost</th>
                <th>Total Inventory Value</th>
                <th>Reorder Point</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in sortedInventoryItems" :key="`${item.sku}-${idx}`">
                <td>{{ item.sku }}</td>
                <td>{{ item.name }}</td>
                <td class="description-cell">{{ item.description || '—' }}</td>
                <td>{{ formatQuantity(item.quantityOnHand) }}</td>
                <td>{{ formatPeso(item.unitCost) }}</td>
                <td class="total">{{ formatPeso(item.totalInventoryValue) }}</td>
                <td>{{ formatQuantity(item.reorderPoint) }}</td>
              </tr>
              <tr v-if="sortedInventoryItems.length === 0">
                <td colspan="7">No inventory records found for this report.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else>
          <div class="metrics-grid">
            <div class="metric-card">
              <p class="metric-label">Total Customers</p>
              <p class="metric-value">{{ totalCustomers }}</p>
              <p class="metric-change">Active customers</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">New Customers</p>
              <p class="metric-value">{{ newCustomers }}</p>
              <p class="metric-change">Added during this period</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Avg Customer Value</p>
              <p class="metric-value">{{ formatPeso(avgCustomerValue) }}</p>
              <p class="metric-change">Revenue per customer</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Repeat Rate</p>
              <p class="metric-value">{{ repeatRate }}%</p>
              <p class="metric-change">Customers with more than one order</p>
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
              <tr v-for="(cust, idx) in sortedTopCustomers" :key="`${cust.name}-${idx}`">
                <td>{{ cust.name }}</td>
                <td><span class="badge" :class="cust.type">{{ cust.type }}</span></td>
                <td>{{ cust.orders }}</td>
                <td>{{ formatPeso(cust.spent) }}</td>
                <td>{{ cust.lastOrder }}</td>
              </tr>
              <tr v-if="sortedTopCustomers.length === 0">
                <td colspan="5">No customer records found for this report.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="preview-actions">
        <button @click="exportReport" class="btn btn-secondary">Export Report</button>
        <button @click="printReport" class="btn btn-secondary">Print</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import api from '../../api';

const selectedReport = ref('sales');
const reportPeriod = ref('month');
const fromDate = ref('');
const toDate = ref('');
const sortField = ref('dateValue');
const sortDirection = ref('desc');
const loading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const reportGenerated = ref(false);
const generatedAt = ref(null);

const salesData = ref([]);
const paymentMethods = ref([]);
const inventoryItems = ref([]);
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
const currentStockQuantity = ref(0);
const lowStockCount = ref(0);
const totalInventoryValue = ref(0);

const totalCustomers = ref(0);
const newCustomers = ref(0);
const repeatRate = ref(0);
const avgCustomerValue = ref(0);

const defaultSortConfig = {
  sales: { field: 'dateValue', direction: 'desc' },
  payments: { field: 'amount', direction: 'desc' },
  inventory: { field: 'name', direction: 'asc' },
  customers: { field: 'spent', direction: 'desc' },
};

const selectedReportLabel = computed(() => ({
  sales: 'Sales Report',
  payments: 'Payment Report',
  inventory: 'Inventory Report',
  customers: 'Customer Report',
}[selectedReport.value]));

const getReportTitle = () => ({
  today: 'Today',
  week: 'This Week',
  month: 'This Month',
  all: 'All Time',
  custom: fromDate.value && toDate.value ? `${fromDate.value} to ${toDate.value}` : 'Custom Range',
}[reportPeriod.value]);

const sortOptions = computed(() => {
  if (selectedReport.value === 'sales') {
    return [
      { value: 'dateValue', label: 'Date' },
      { value: 'orders', label: 'Orders' },
      { value: 'retail', label: 'Retail Sales' },
      { value: 'wholesale', label: 'Wholesale Sales' },
      { value: 'total', label: 'Total Sales' },
    ];
  }

  if (selectedReport.value === 'payments') {
    return [
      { value: 'method', label: 'Method' },
      { value: 'transactions', label: 'Transactions' },
      { value: 'amount', label: 'Amount' },
      { value: 'percentage', label: 'Percentage of Total' },
    ];
  }

  if (selectedReport.value === 'inventory') {
    return [
      { value: 'sku', label: 'SKU' },
      { value: 'name', label: 'Name' },
      { value: 'quantityOnHand', label: 'Quantity On Hand' },
      { value: 'unitCost', label: 'Unit Cost' },
      { value: 'totalInventoryValue', label: 'Inventory Value' },
      { value: 'reorderPoint', label: 'Reorder Point' },
    ];
  }

  return [
    { value: 'name', label: 'Customer' },
    { value: 'type', label: 'Type' },
    { value: 'orders', label: 'Orders' },
    { value: 'spent', label: 'Total Spent' },
    { value: 'lastOrderDate', label: 'Last Order Date' },
  ];
});

const currentSortLabel = computed(() => sortOptions.value.find((option) => option.value === sortField.value)?.label || 'default order');
const reportHeading = computed(() => `${selectedReportLabel.value} - ${getReportTitle()}`);
const reportSubtitle = computed(() => `Sorted by ${currentSortLabel.value} in ${sortDirection.value === 'asc' ? 'ascending' : 'descending'} order.`);
const generatedAtLabel = computed(() => generatedAt.value ? generatedAt.value.toLocaleString() : 'Not generated');

const compareValues = (left, right) => {
  if (left == null && right == null) return 0;
  if (left == null) return 1;
  if (right == null) return -1;

  if (typeof left === 'number' && typeof right === 'number') {
    return left - right;
  }

  const leftDate = Date.parse(left);
  const rightDate = Date.parse(right);
  if (!Number.isNaN(leftDate) && !Number.isNaN(rightDate)) {
    return leftDate - rightDate;
  }

  return String(left).localeCompare(String(right), undefined, { numeric: true, sensitivity: 'base' });
};

const sortRows = (rows, valueGetter) => {
  const direction = sortDirection.value === 'asc' ? 1 : -1;
  return [...rows].sort((left, right) => direction * compareValues(valueGetter(left), valueGetter(right)));
};

const sortedSalesData = computed(() => sortRows(salesData.value, (row) => {
  if (sortField.value === 'total') {
    return Number(row.retail || 0) + Number(row.wholesale || 0);
  }

  return row[sortField.value];
}));

const sortedPaymentMethods = computed(() => sortRows(paymentMethods.value, (row) => row[sortField.value]));
const sortedInventoryItems = computed(() => sortRows(inventoryItems.value, (row) => row[sortField.value]));
const sortedTopCustomers = computed(() => sortRows(topCustomers.value, (row) => row[sortField.value]));

const formatPeso = (value) => `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatQuantity = (value) => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });

const buildParams = () => {
  const params = { period: reportPeriod.value };

  if (reportPeriod.value === 'custom' && fromDate.value && toDate.value) {
    params.from_date = fromDate.value;
    params.to_date = toDate.value;
  }

  return params;
};

const applyReportData = (reportData) => {
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
  currentStockQuantity.value = Number(reportData.inventory?.currentStockQuantity || 0);
  lowStockCount.value = Number(reportData.inventory?.lowStockCount || 0);
  totalInventoryValue.value = Number(reportData.inventory?.totalInventoryValue || 0);
  inventoryItems.value = reportData.inventory?.items || [];

  totalCustomers.value = Number(reportData.customers?.totalCustomers || 0);
  newCustomers.value = Number(reportData.customers?.newCustomers || 0);
  repeatRate.value = Number(reportData.customers?.repeatRate || 0);
  avgCustomerValue.value = Number(reportData.customers?.avgCustomerValue || 0);
  topCustomers.value = reportData.customers?.topCustomers || [];
};

const resetPreview = () => {
  reportGenerated.value = false;
  successMessage.value = '';
  errorMessage.value = '';
};

const handleReportTypeChange = () => {
  const defaults = defaultSortConfig[selectedReport.value];
  sortField.value = defaults.field;
  sortDirection.value = defaults.direction;
  resetPreview();
};

const handleFilterChange = () => {
  resetPreview();
};

const generateReport = async () => {
  if (reportPeriod.value === 'custom' && (!fromDate.value || !toDate.value)) {
    errorMessage.value = 'Choose both start and end dates for a custom report.';
    return;
  }

  loading.value = true;
  successMessage.value = '';
  errorMessage.value = '';

  try {
    const response = await api.get('/reports/analytics', { params: buildParams() });

    if (!response.data?.success) {
      errorMessage.value = response.data?.message || 'Failed to load report data';
      return;
    }

    applyReportData(response.data.data);
    reportGenerated.value = true;
    generatedAt.value = new Date();
    successMessage.value = `${selectedReportLabel.value} generated.`;
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to load report data';
  } finally {
    loading.value = false;
  }
};

const addSectionHeader = (doc, y, title) => {
  doc.setFontSize(12);
  doc.setTextColor(10, 29, 55);
  doc.text(title, 14, y);
  return y + 6;
};

const exportReport = () => {
  if (!reportGenerated.value) {
    errorMessage.value = 'Generate a report before exporting it.';
    return;
  }

  const doc = new jsPDF();

  doc.setFontSize(16);
  doc.setTextColor(10, 29, 55);
  doc.text('LiamKai Business Report', 14, 16);

  doc.setFontSize(11);
  doc.setTextColor(70, 70, 70);
  doc.text(`Type: ${selectedReportLabel.value}`, 14, 24);
  doc.text(`Period: ${getReportTitle()}`, 14, 30);
  doc.text(`Generated: ${generatedAtLabel.value}`, 14, 36);
  doc.text(`Sort: ${currentSortLabel.value} (${sortDirection.value})`, 14, 42);

  let currentY = 50;

  if (selectedReport.value === 'sales') {
    currentY = addSectionHeader(doc, currentY, 'Summary');
    autoTable(doc, {
      startY: currentY,
      head: [['Metric', 'Value']],
      body: [
        ['Total Sales', formatPeso(totalSales.value)],
        ['Total Orders', String(totalOrders.value)],
        ['Average Order Value', formatPeso(avgOrderValue.value)],
        ['Retail vs Wholesale', `${retailPercentage.value}% / ${wholesalePercentage.value}%`],
      ],
      styles: { fontSize: 10 },
    });

    currentY = doc.lastAutoTable.finalY + 10;
    currentY = addSectionHeader(doc, currentY, 'Daily Sales Breakdown');
    autoTable(doc, {
      startY: currentY,
      head: [['Date', 'Orders', 'Retail Sales', 'Wholesale Sales', 'Total Sales', 'Avg Order Value']],
      body: sortedSalesData.value.map((row) => {
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
      body: sortedPaymentMethods.value.map((method) => [
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
        ['Quantity On Hand', formatQuantity(currentStockQuantity.value)],
        ['Low Stock Items', String(lowStockCount.value)],
        ['Total Inventory Value', formatPeso(totalInventoryValue.value)],
      ],
      styles: { fontSize: 10 },
    });

    currentY = doc.lastAutoTable.finalY + 10;
    currentY = addSectionHeader(doc, currentY, 'Inventory Listing');
    autoTable(doc, {
      startY: currentY,
      head: [['SKU', 'Name', 'Description', 'Qty On Hand', 'Unit Cost', 'Inventory Value', 'Reorder Point']],
      body: sortedInventoryItems.value.map((item) => [
        item.sku,
        item.name,
        item.description || '-',
        formatQuantity(item.quantityOnHand),
        formatPeso(item.unitCost),
        formatPeso(item.totalInventoryValue),
        formatQuantity(item.reorderPoint),
      ]),
      styles: { fontSize: 8 },
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
      body: sortedTopCustomers.value.map((cust) => [
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

const escapeHtml = (value) => String(value ?? '')
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#39;');

const printableContent = computed(() => {
  if (selectedReport.value === 'sales') {
    return {
      metrics: [
        ['Total Sales', formatPeso(totalSales.value)],
        ['Total Orders', totalOrders.value],
        ['Average Order Value', formatPeso(avgOrderValue.value)],
        ['Retail vs Wholesale', `${retailPercentage.value}% / ${wholesalePercentage.value}%`],
      ],
      headings: ['Date', 'Orders', 'Retail Sales', 'Wholesale Sales', 'Total Sales', 'Avg Order Value'],
      rows: sortedSalesData.value.map((row) => {
        const total = Number(row.retail || 0) + Number(row.wholesale || 0);
        return [row.date, row.orders, formatPeso(row.retail), formatPeso(row.wholesale), formatPeso(total), formatPeso(row.orders ? total / row.orders : 0)];
      }),
    };
  }

  if (selectedReport.value === 'payments') {
    return {
      metrics: [
        ['Total Collected', formatPeso(totalCollected.value)],
        ['Outstanding', formatPeso(totalOutstanding.value)],
        ['Collection Rate', `${collectionRate.value}%`],
      ],
      headings: ['Method', 'Transactions', 'Amount', '% of Total'],
      rows: sortedPaymentMethods.value.map((method) => [method.method, method.transactions, formatPeso(method.amount), `${method.percentage}%`]),
    };
  }

  if (selectedReport.value === 'inventory') {
    return {
      metrics: [
        ['Total SKUs', totalSKUs.value],
        ['Quantity On Hand', formatQuantity(currentStockQuantity.value)],
        ['Low Stock Items', lowStockCount.value],
        ['Total Inventory Value', formatPeso(totalInventoryValue.value)],
      ],
      headings: ['SKU', 'Name', 'Description', 'Quantity On Hand', 'Unit Cost', 'Inventory Value', 'Reorder Point'],
      rows: sortedInventoryItems.value.map((item) => [item.sku, item.name, item.description || '-', formatQuantity(item.quantityOnHand), formatPeso(item.unitCost), formatPeso(item.totalInventoryValue), formatQuantity(item.reorderPoint)]),
    };
  }

  return {
    metrics: [
      ['Total Customers', totalCustomers.value],
      ['New Customers', newCustomers.value],
      ['Repeat Rate', `${repeatRate.value}%`],
      ['Average Customer Value', formatPeso(avgCustomerValue.value)],
    ],
    headings: ['Customer', 'Type', 'Orders', 'Total Spent', 'Last Order'],
    rows: sortedTopCustomers.value.map((cust) => [cust.name, cust.type, cust.orders, formatPeso(cust.spent), cust.lastOrder]),
  };
});

const printReport = () => {
  if (!reportGenerated.value) {
    errorMessage.value = 'Generate a report before printing it.';
    return;
  }

  const printWindow = window.open('', '_blank', 'width=1024,height=768');
  if (!printWindow) {
    errorMessage.value = 'Unable to open the print preview. Allow pop-ups and try again.';
    return;
  }

  const metricsMarkup = printableContent.value.metrics
    .map(([label, value]) => `<div class="metric"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`)
    .join('');
  const rowsMarkup = printableContent.value.rows
    .map((row) => `<tr>${row.map((value) => `<td>${escapeHtml(value)}</td>`).join('')}</tr>`)
    .join('');
  const headingsMarkup = printableContent.value.headings
    .map((heading) => `<th>${escapeHtml(heading)}</th>`)
    .join('');

  printWindow.document.write(`
    <html>
      <head>
        <title>${escapeHtml(reportHeading.value)}</title>
        <style>
          body { font-family: Arial, sans-serif; margin: 24px; color: #102746; }
          h1 { margin-bottom: 4px; }
          p { margin: 0 0 12px; color: #475569; }
          .meta { margin-bottom: 20px; font-size: 13px; }
          .metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 24px; }
          .metric { border: 1px solid #dbe3ef; border-radius: 8px; padding: 12px; }
          .metric span { display: block; font-size: 12px; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
          table { width: 100%; border-collapse: collapse; }
          th, td { border: 1px solid #dbe3ef; padding: 10px; text-align: left; font-size: 12px; vertical-align: top; }
          th { background: #f7f8fb; }
        </style>
      </head>
      <body>
        <h1>${escapeHtml(reportHeading.value)}</h1>
        <p>${escapeHtml(reportSubtitle.value)}</p>
        <div class="meta">Generated: ${escapeHtml(generatedAtLabel.value)}</div>
        <div class="metrics">${metricsMarkup}</div>
        <table>
          <thead><tr>${headingsMarkup}</tr></thead>
          <tbody>${rowsMarkup}</tbody>
        </table>
        <script>
          window.onload = function () {
            window.print();
          };
        <\/script>
      </body>
    </html>
  `);
  printWindow.document.close();
};

handleReportTypeChange();
</script>

<style scoped>
.reports-container {
  animation: fadeIn 0.3s ease-in;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.report-state {
  margin: 0;
  color: #334155;
  font-weight: 500;
}

.report-state.error {
  color: #b91c1c;
}

.report-state.success {
  color: #166534;
}

.report-controls,
.report-preview-card {
  background: white;
  padding: 20px;
  border-radius: 14px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.report-controls {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  align-items: center;
}

.control-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.control-group label {
  font-weight: 600;
  color: #475569;
  font-size: 14px;
  white-space: nowrap;
}

.control-group select,
.date-range input {
  padding: 9px 12px;
  border: 1px solid #d7deea;
  border-radius: 8px;
  font-size: 14px;
  background: white;
}

.control-group select:focus,
.date-range input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.12);
}

.date-range {
  display: flex;
  gap: 10px;
  align-items: center;
}

.btn {
  padding: 10px 16px;
  border: none;
  border-radius: 9px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s ease;
  font-size: 14px;
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #cf6c20;
}

.btn-secondary {
  background-color: #eef2f7;
  color: #22314d;
}

.btn-secondary:hover {
  background-color: #dfe7f1;
}

.preview-header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 18px;
}

.preview-eyebrow {
  margin: 0 0 6px;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #d97706;
  font-weight: 700;
}

.preview-header h2 {
  margin: 0 0 6px;
  color: #0f2745;
}

.preview-subtitle {
  margin: 0;
  color: #64748b;
}

.preview-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  color: #475569;
  font-size: 13px;
  text-align: right;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 15px;
  margin-bottom: 26px;
}

.metric-card {
  background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
  padding: 18px;
  border-radius: 12px;
  border: 1px solid #edf1f6;
  border-left: 4px solid #e57c2a;
}

.metric-label {
  margin: 0;
  color: #64748b;
  font-size: 12px;
  text-transform: uppercase;
}

.metric-value {
  margin: 10px 0 8px;
  font-size: 26px;
  font-weight: 700;
  color: #0a1d37;
}

.metric-change {
  margin: 0;
  font-size: 12px;
  color: #4b5563;
}

.report-section h3 {
  color: #22314d;
  margin: 0 0 12px;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.report-table thead {
  background-color: #f8fafc;
}

.report-table th {
  padding: 14px;
  text-align: left;
  font-weight: 700;
  color: #475569;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0;
}

.report-table td {
  padding: 12px 14px;
  border-bottom: 1px solid #e2e8f0;
  color: #1f2937;
  vertical-align: top;
}

.report-table tbody tr:hover {
  background-color: #f8fafc;
}

.description-cell {
  min-width: 220px;
  color: #475569;
}

.total {
  font-weight: 700;
  color: #102746;
}

.badge {
  display: inline-flex;
  padding: 5px 10px;
  border-radius: 999px;
  text-transform: capitalize;
  font-size: 12px;
  font-weight: 700;
}

.badge.retail {
  background: #e0f2fe;
  color: #0369a1;
}

.badge.wholesale {
  background: #fef3c7;
  color: #b45309;
}

.preview-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 18px;
}

@media (max-width: 860px) {
  .preview-meta {
    text-align: left;
  }

  .preview-actions {
    justify-content: stretch;
    flex-direction: column;
  }

  .preview-actions .btn {
    width: 100%;
  }
}
</style>
