<template>
  <div class="app-wrapper">
    <nav class="sidebar">
      <div class="logo">
        <h2>LiamKai</h2>
      </div>

      <ul class="nav-menu">
        <li>
          <router-link to="/" :class="{ active: $route.path === '/' }">
            <span class="icon">📊</span> Dashboard
          </router-link>
        </li>
        <li>
          <router-link to="/orders" :class="{ active: $route.path.includes('orders') }">
            <span class="icon">📦</span> Orders
          </router-link>
        </li>
        <li>
          <router-link to="/customers" :class="{ active: $route.path.includes('customers') }">
            <span class="icon">👥</span> Customers
          </router-link>
        </li>
        <li>
          <router-link to="/pos" :class="{ active: $route.path === '/pos' }">
            <span class="icon">🏪</span> POS
          </router-link>
        </li>
        <li>
          <router-link to="/inventory" :class="{ active: $route.path.includes('inventory') }">
            <span class="icon">🧊</span> Inventory
          </router-link>
        </li>
        <li>
          <router-link to="/deliveries" :class="{ active: $route.path.includes('deliveries') }">
            <span class="icon">🚚</span> Delivery
          </router-link>
        </li>
        <li>
          <router-link to="/purchasing" :class="{ active: $route.path.includes('purchasing') }">
            <span class="icon">📦</span> Purchasing
          </router-link>
        </li>
        <li>
          <router-link to="/reports" :class="{ active: $route.path === '/reports' }">
            <span class="icon">📈</span> Reports
          </router-link>
        </li>
        <li>
          <router-link to="/employees" :class="{ active: $route.path === '/employees' }">
            <span class="icon">👨‍💼</span> Admin
          </router-link>
        </li>
      </ul>

      <div class="sidebar-footer">
        <div class="user-info">
          <p class="user-name">{{ authStore.user?.username }}</p>
          <p class="user-role">{{ authStore.user?.role }}</p>
        </div>
        <button @click="logout" class="logout-btn">Logout</button>
      </div>
    </nav>

    <div class="main-content">
      <header class="top-bar">
        <h1>{{ pageTitle }}</h1>
        <div class="header-actions">
          <span class="time">{{ currentTime }}</span>
        </div>
      </header>

      <div class="content-area">
        <router-view />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const currentTime = ref('');

const pageTitle = computed(() => {
  const titles = {
    'Dashboard': '📊 Dashboard',
    'OrdersList': '📦 Orders',
    'CreateOrder': '📦 Create Order',
    'CustomerList': '👥 Customers',
    'CustomerProfile': '👤 Customer Profile',
    'POSScreen': '🏪 Walk-in POS',
    'DeliveryList': '🚚 Delivery Management',
    'DeliveryDetails': '🚚 Delivery Details',
    'PurchasingDashboard': '📦 Purchasing',
    'CreatePurchaseOrder': '📦 Create Purchase Order',
    'ReceivingReport': '📦 Receiving Report',
    'InventoryView': '🧊 Current Stock',
    'StockMovement': '🧊 Stock Movements',
    'EmployeeManagement': '👨‍💼 Employee Management',
    'ReportsPage': '📈 Reports & Analytics'
  };
  return titles[route.name] || 'Dashboard';
});

const logout = () => {
  authStore.logout();
  router.push('/login');
};

onMounted(() => {
  setInterval(() => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString();
  }, 1000);
});
</script>

<style scoped>
.app-wrapper {
  display: flex;
  min-height: 100vh;
  background-color: #f3f4f5;
}

.sidebar {
  width: 280px;
  background-color: #0a1d37;
  color: white;
  padding: 20px;
  display: flex;
  flex-direction: column;
  position: fixed;
  height: 100vh;
  overflow-y: auto;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
}

.logo {
  margin-bottom: 30px;
  border-bottom: 2px solid #e57c2a;
  padding-bottom: 15px;
}

.logo h2 {
  color: #e57c2a;
  font-size: 24px;
  margin: 0;
}

.nav-menu {
  list-style: none;
  padding: 0;
  flex: 1;
}

.nav-menu li {
  margin-bottom: 10px;
}

.nav-menu a {
  display: flex;
  align-items: center;
  padding: 12px 15px;
  color: #bbb;
  text-decoration: none;
  border-radius: 6px;
  transition: all 0.3s;
}

.nav-menu a:hover {
  background-color: rgba(229, 124, 42, 0.1);
  color: #e57c2a;
}

.nav-menu a.active {
  background-color: #e57c2a;
  color: white;
}

.nav-menu .icon {
  margin-right: 12px;
  font-size: 18px;
}

.sidebar-footer {
  border-top: 2px solid #1a3a52;
  padding-top: 15px;
  margin-top: auto;
}

.user-info {
  margin-bottom: 15px;
}

.user-name {
  font-weight: 600;
  margin: 0;
  color: #e57c2a;
}

.user-role {
  font-size: 12px;
  color: #bbb;
  margin: 5px 0 0 0;
  text-transform: capitalize;
}

.logout-btn {
  width: 100%;
  padding: 10px;
  background-color: #c33;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.3s;
}

.logout-btn:hover {
  background-color: #a22;
}

.main-content {
  flex: 1;
  margin-left: 280px;
  display: flex;
  flex-direction: column;
}

.top-bar {
  background: white;
  padding: 20px 30px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.top-bar h1 {
  margin: 0;
  color: #0a1d37;
  font-size: 24px;
}

.header-actions {
  display: flex;
  gap: 20px;
  align-items: center;
}

.time {
  color: #666;
  font-family: monospace;
  font-size: 14px;
}

.content-area {
  flex: 1;
  padding: 30px;
  overflow-y: auto;
}

@media (max-width: 768px) {
  .sidebar {
    width: 250px;
  }

  .main-content {
    margin-left: 250px;
  }

  .content-area {
    padding: 20px;
  }
}
</style>
