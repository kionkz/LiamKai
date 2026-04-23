<template>
  <div class="dashboard-container">
    <div v-if="dashboardError" class="dashboard-error">
      {{ dashboardError }}
    </div>

    <!-- ── Sales / Customer Orders role ── -->
    <template v-if="authStore.hasPermission('customer-orders')">
      <div class="widgets-grid">
        <div class="widget card">
          <div class="widget-header"><h3>Today's Sales</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ formatCurrency(summary.headline.todaysSales) }}</p>
            <p class="meta">{{ summary.headline.ordersToday }} order{{ summary.headline.ordersToday !== 1 ? 's' : '' }} today</p>
          </div>
        </div>
        <div class="widget card">
          <div class="widget-header"><h3>This Week</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ formatCurrency(summary.headline.weekRevenue) }}</p>
            <p class="meta">Revenue this week</p>
          </div>
        </div>
        <div class="widget card">
          <div class="widget-header"><h3>This Month</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ formatCurrency(summary.headline.monthRevenue) }}</p>
            <p class="meta">Revenue this month</p>
          </div>
        </div>
        <div class="widget card accent-card">
          <div class="widget-header"><h3>Outstanding Balance</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ formatCurrency(summary.headline.outstanding) }}</p>
            <p class="meta">Unpaid / partially paid orders</p>
          </div>
        </div>
        <div class="widget card">
          <div class="widget-header"><h3>Total Customers</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ summary.customers.total }}</p>
            <p class="meta">+{{ summary.customers.newThisWeek }} new this week</p>
          </div>
        </div>
      </div>

      <div class="card recent-orders-card">
        <div class="card-header-row">
          <h2>Recent Orders</h2>
          <router-link to="/orders" class="view-all-link">View All Orders →</router-link>
        </div>
        <div v-if="loadingOrders" class="loading-text">Loading...</div>
        <div v-else-if="summary.recentOrders.length === 0" class="empty-text">No recent orders</div>
        <table v-else class="data-table clickable-rows">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in summary.recentOrders" :key="order.id" @click="router.push('/orders')" title="Go to Order History">
              <td>{{ order.order_number }}</td>
              <td>{{ order.customer?.name || 'Walk-In' }}</td>
              <td><span class="badge" :class="order.order_type">{{ order.order_type || '-' }}</span></td>
              <td>{{ formatCurrency(order.total_amount) }}</td>
              <td><span class="status" :class="order.status">{{ order.status }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ── Inventory role ── -->
    <template v-else-if="authStore.hasPermission('inventory')">
      <div class="widgets-grid">
        <div class="widget card">
          <div class="widget-header"><h3>Low Stock Alerts</h3></div>
          <div class="widget-body">
            <p class="big-number" :style="summary.inventory.lowStockCount > 0 ? 'color:#e57c2a' : ''">{{ summary.inventory.lowStockCount }}</p>
            <p class="meta">{{ summary.inventory.lowStockCount > 0 ? 'Action required' : 'Stock levels OK' }}</p>
          </div>
        </div>
        <div class="widget card">
          <div class="widget-header"><h3>Current Inventory</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ formatQuantity(summary.inventory.currentStockQuantity) }}</p>
            <p class="meta">Across {{ summary.inventory.totalSkus }} SKUs</p>
          </div>
        </div>
        <div class="widget card accent-card">
          <div class="widget-header"><h3>Inventory Value</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ formatCurrency(summary.inventory.inventoryValue) }}</p>
            <p class="meta">Live stock value</p>
          </div>
        </div>
      </div>
      <div class="card">
        <h2>Priority Reorders</h2>
        <div v-if="summary.inventory.lowStockItems.length === 0" class="empty-text">No urgent reorder items</div>
        <table v-else class="data-table">
          <thead><tr><th>Product</th><th>On Hand</th><th>Reorder Point</th></tr></thead>
          <tbody>
            <tr v-for="item in summary.inventory.lowStockItems" :key="item.product">
              <td>{{ item.product }}</td>
              <td>{{ formatQuantity(item.current) }}</td>
              <td>{{ formatQuantity(item.reorder) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ── Purchasing role ── -->
    <template v-else-if="authStore.hasPermission('purchasing')">
      <div class="widgets-grid">
        <div class="widget card">
          <div class="widget-header"><h3>Open Purchase Orders</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ summary.operations.openPurchaseOrders }}</p>
            <p class="meta">Pending / partially received</p>
          </div>
        </div>
        <div class="widget card">
          <div class="widget-header"><h3>Low Stock Items</h3></div>
          <div class="widget-body">
            <p class="big-number" :style="summary.inventory.lowStockCount > 0 ? 'color:#e57c2a' : ''">{{ summary.inventory.lowStockCount }}</p>
            <p class="meta">{{ summary.inventory.lowStockCount > 0 ? 'Action required' : 'Stock levels OK' }}</p>
          </div>
        </div>
      </div>
      <div class="card">
        <h2>Priority Reorders</h2>
        <div v-if="summary.inventory.lowStockItems.length === 0" class="empty-text">No urgent reorder items</div>
        <table v-else class="data-table">
          <thead><tr><th>Product</th><th>On Hand</th><th>Reorder Point</th></tr></thead>
          <tbody>
            <tr v-for="item in summary.inventory.lowStockItems" :key="item.product">
              <td>{{ item.product }}</td>
              <td>{{ formatQuantity(item.current) }}</td>
              <td>{{ formatQuantity(item.reorder) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ── Delivery role ── -->
    <template v-else-if="authStore.hasPermission('logistics')">
      <div class="widgets-grid">
        <div class="widget card">
          <div class="widget-header"><h3>Pending Deliveries</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ summary.operations.pendingDeliveries }}</p>
            <p class="meta">Awaiting dispatch</p>
          </div>
        </div>
        <div class="widget card">
          <div class="widget-header"><h3>En Route</h3></div>
          <div class="widget-body">
            <p class="big-number">{{ summary.operations.enRouteDeliveries }}</p>
            <p class="meta">Currently in transit</p>
          </div>
        </div>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api';
import { useAuthStore } from '../../stores/authStore';
import { formatPeso } from '../../utils/currency';

const authStore = useAuthStore();
const router = useRouter();

const loadingOrders = ref(false);
const dashboardError = ref('');
const summary = ref({
  headline: {
    todaysSales: 0,
    ordersToday: 0,
    weekRevenue: 0,
    monthRevenue: 0,
    outstanding: 0,
  },
  operations: {
    pendingDeliveries: 0,
    enRouteDeliveries: 0,
    openPurchaseOrders: 0,
    receivedToday: 0,
  },
  inventory: {
    totalSkus: 0,
    currentStockQuantity: 0,
    lowStockCount: 0,
    inventoryValue: 0,
    lowStockItems: [],
  },
  recentOrders: [],
  customers: {
    total: 0,
    newToday: 0,
    newThisWeek: 0,
    topCustomers: [],
  },
});

const formatCurrency = formatPeso;

const formatQuantity = (value) => `${Number(value || 0).toFixed(2)} kg`;

const fetchDashboardData = async () => {
  loadingOrders.value = true;
  dashboardError.value = '';
  try {
    const response = await api.get('/reports/dashboard-summary');
    if (response.data?.success) {
      summary.value = response.data.data;
      return;
    }
    dashboardError.value = response.data?.message || 'Dashboard data could not be loaded.';
  } catch (e) {
    console.error('Dashboard fetch error:', e);
    dashboardError.value = e.response?.data?.message || 'Dashboard data could not be loaded.';
  } finally {
    loadingOrders.value = false;
  }
};

onMounted(fetchDashboardData);
</script>

<style scoped>
.dashboard-container { max-width: 1400px; margin: 0 auto; animation: fadeIn 0.3s ease-in; padding: 20px 0; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.dashboard-error {
  margin-bottom: 16px;
  padding: 12px 14px;
  border: 1px solid #fecdca;
  border-radius: 10px;
  background: #fff1f2;
  color: #b42318;
  font-size: 14px;
}

/* Widget grid */
.widgets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

/* Base card */
.widget {
  background: #ffffff;
  border-radius: 14px;
  padding: 24px 24px 20px;
  box-shadow: 0 2px 10px rgba(10, 29, 55, 0.07);
  border-left: 5px solid #e57c2a;
  transition: transform 0.2s, box-shadow 0.2s;
}

.widget:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(10,29,55,0.12); }

.widget-header { margin-bottom: 16px; }
.widget-header h3 {
  margin: 0;
  font-size: 11px;
  color: #8a96a8;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  font-weight: 700;
}

.widget-body { padding-top: 4px; }
.big-number {
  font-size: 32px;
  font-weight: 800;
  margin: 0;
  color: #0a1d37;
  line-height: 1.1;
}
.meta {
  font-size: 12px;
  color: #9aaab8;
  margin: 8px 0 0;
}

/* Accent card — Inventory Value */
.accent-card {
  background: linear-gradient(135deg, #0a1d37 0%, #1a3a5c 100%) !important;
  border-left-color: #e57c2a !important;
}
.accent-card .widget-header h3 {
  color: #7ea8d0 !important;
}
.accent-card .big-number {
  color: #ffffff !important;
}
.accent-card .meta {
  color: #7ea8d0 !important;
}

/* Dashboard content */
.recent-orders-card { margin-top: 0; }
.card { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 2px 10px rgba(10,29,55,0.07); margin-bottom: 20px; }
.card h2 { margin: 0 0 20px; color: #0a1d37; font-size: 17px; font-weight: 700; }.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background-color: #f8fafc; }
.data-table th { padding: 12px; text-align: left; font-weight: 700; color: #8a96a8; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #edf2f7; }
.data-table td { padding: 12px; border-bottom: 1px solid #edf2f7; color: #334155; font-size: 14px; }
.badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; }
.badge.retail { background-color: #e3f2fd; color: #1976d2; }
.badge.wholesale { background-color: #f3e5f5; color: #7b1fa2; }
.status { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
.status.pending { background-color: #fff3e0; color: #f57c00; }
.status.partial, .status.partially_paid, .status.partially_received { background-color: #fff7e6; color: #b26a00; }
.status.paid, .status.completed, .status.received { background-color: #e8f5e9; color: #388e3c; }
.status.delivered { background-color: #e0f2f1; color: #00897b; }
.revenue-summary { display: grid; gap: 15px; }
.revenue-item { padding: 15px; background-color: #f9f9f9; border-radius: 8px; border-left: 4px solid #e57c2a; }
.revenue-item .label { font-size: 12px; color: #999; margin: 0; text-transform: uppercase; }
.revenue-item .value { font-size: 24px; font-weight: 700; color: #0a1d37; margin: 5px 0 0; }
.revenue-item .value.compact { font-size: 16px; }
.low-stock-list { display: grid; gap: 10px; margin-top: 8px; }
.low-stock-item { display: flex; justify-content: space-between; gap: 12px; color: #27344d; font-size: 13px; }
.loading-text, .empty-text { text-align: center; color: #999; padding: 30px; }
.card-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.card-header-row h2 { margin: 0; color: #0a1d37; font-size: 17px; font-weight: 700; }
.view-all-link { font-size: 13px; color: #e57c2a; font-weight: 600; text-decoration: none; }
.view-all-link:hover { text-decoration: underline; }
.clickable-rows tbody tr { cursor: pointer; }
.clickable-rows tbody tr:hover { background-color: #f0f7ff; }
</style>
