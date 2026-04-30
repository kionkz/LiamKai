<template>
  <div class="app-wrapper">
    <nav class="sidebar">
      <div class="logo">
        <h2>LiamKai</h2>
        <p>Management Portal</p>
      </div>

      <ul class="nav-menu">
        <li>
          <router-link to="/" :class="{ active: activeMenu === 'dashboard' }" @click="setActiveMenu('dashboard')">
            <ClipboardList class="menu-icon" :size="18" />
            <span class="tab-label">Operations Dashboard</span>
          </router-link>
        </li>
        <li class="menu-group">
          <button class="group-head" :class="{ active: activeMenu === 'customer-group' }" type="button" @click="toggleAndActivate('customer', 'customer-group')">
            <ClipboardList class="menu-icon" :size="18" />
            <span>Sales & Customers</span>
            <span class="group-caret">{{ customerOrdersOpen ? '▾' : '▸' }}</span>
          </button>
          <div v-if="customerOrdersOpen" class="sub-links">
            <router-link to="/customers" :class="{ active: activeMenu === 'customer-profile' }" @click="setActiveMenu('customer-profile')">Customers</router-link>
            <router-link to="/orders" :class="{ active: activeMenu === 'orders' }" @click="setActiveMenu('orders')">Sales Orders</router-link>
            <router-link to="/payments" :class="{ active: activeMenu === 'payment-management' }" @click="setActiveMenu('payment-management')">Collections</router-link>
            <router-link to="/pos" :class="{ active: activeMenu === 'walk-in' }" @click="setActiveMenu('walk-in')">Point of Sale</router-link>
          </div>
        </li>
        <li>
          <router-link to="/deliveries" :class="{ active: activeMenu === 'logistics' }" @click="setActiveMenu('logistics')">
            <MapPinned class="menu-icon" :size="18" />
            <span class="tab-label">Order Fulfillment</span>
          </router-link>
        </li>
        <li class="menu-group">
          <button class="group-head" :class="{ active: activeMenu === 'inventory-group' }" type="button" @click="toggleAndActivate('inventory', 'inventory-group')">
            <Boxes class="menu-icon" :size="18" />
            <span>Inventory Control</span>
            <span class="group-caret">{{ inventoryOpen ? '▾' : '▸' }}</span>
          </button>
          <div v-if="inventoryOpen" class="sub-links">
            <router-link to="/inventory" :class="{ active: activeMenu === 'current-stock' }" @click="setActiveMenu('current-stock')">Stock On Hand</router-link>
            <router-link to="/inventory/categories" :class="{ active: activeMenu === 'inventory-categories' }" @click="setActiveMenu('inventory-categories')">Product Categories</router-link>
            <router-link to="/inventory/movements" :class="{ active: activeMenu === 'stock-movement' }" @click="setActiveMenu('stock-movement')">Inventory Audit</router-link>
          </div>
        </li>
        <li class="menu-group">
          <button class="group-head" :class="{ active: activeMenu === 'purchasing-group' }" type="button" @click="toggleAndActivate('purchasing', 'purchasing-group')">
            <Truck class="menu-icon" :size="18" />
            <span>Purchasing</span>
            <span class="group-caret">{{ purchasingOpen ? '▾' : '▸' }}</span>
          </button>
          <div v-if="purchasingOpen" class="sub-links">
            <router-link to="/purchasing" :class="{ active: activeMenu === 'suppliers-profile' }" @click="setActiveMenu('suppliers-profile')">Purchase Orders</router-link>
            <router-link to="/reports" :class="{ active: activeMenu === 'sales-report' }" @click="setActiveMenu('sales-report')">Reports & Analytics</router-link>
          </div>
        </li>
        <li v-if="authStore.isAdmin" class="menu-group">
          <button class="group-head" :class="{ active: activeMenu === 'employees-group' }" type="button" @click="toggleAndActivate('employee-admin', 'employees-group')">
            <Users class="menu-icon" :size="18" />
            <span>Employees & Accounts</span>
            <span class="group-caret">{{ employeeAdminOpen ? '▾' : '▸' }}</span>
          </button>
          <div v-if="employeeAdminOpen" class="sub-links">
            <router-link to="/employees" :class="{ active: activeMenu === 'employees' }" @click="setActiveMenu('employees')">Employees</router-link>
            <router-link to="/login-audit-logs" :class="{ active: activeMenu === 'login-audit' }" @click="setActiveMenu('login-audit')">Login Audit</router-link>
          </div>
        </li>
      </ul>

      <div class="sidebar-footer">
        <div class="user-info" @click="router.push('/profile')">
          <div class="user-avatar">U</div>
          <div>
            <p class="user-name">{{ displayName }}</p>
            <p class="user-role">{{ displayRole }}</p>
          </div>
        </div>
        <button @click="logout" class="logout-btn">
          <span class="logout-icon">&#x2192;</span>
          <span>Log out</span>
        </button>
      </div>
    </nav>

    <div class="main-content">
      <header class="top-bar">
        <div>
          <p class="eyebrow">LiamKai Operations</p>
          <h1>{{ pageTitle }}</h1>
          <p class="page-summary">{{ pageSummary }}</p>
        </div>
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
import { computed, ref, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { Boxes, ClipboardList, MapPinned, Truck, Users } from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const currentTime = ref('');
const customerOrdersOpen = ref(false);
const inventoryOpen = ref(false);
const purchasingOpen = ref(false);
const employeeAdminOpen = ref(false);
const activeMenu = ref('');

const displayName = computed(() => authStore.user?.name || authStore.user?.username || 'User');
const displayRole = computed(() => authStore.user?.role || 'Owner');
const isCustomerOrdersRoute = computed(() => route.path.startsWith('/customers') || route.path === '/pos' || route.path.startsWith('/orders') || route.path.startsWith('/payments'));
const isInventoryRoute = computed(() => route.path.startsWith('/inventory'));
const isPurchasingRoute = computed(() => route.path.startsWith('/purchasing') || route.path === '/reports');
const isEmployeeAdminRoute = computed(() => route.path === '/employees' || route.path === '/login-audit-logs');
const isPurchasingProfileRoute = computed(() => {
  return (
    route.path === '/purchasing' ||
    route.path.startsWith('/purchasing/create') ||
    route.path.startsWith('/purchasing/edit') ||
    route.path.startsWith('/purchasing/receive')
  );
});

const closeAllGroups = () => {
  customerOrdersOpen.value = false;
  inventoryOpen.value = false;
  purchasingOpen.value = false;
  employeeAdminOpen.value = false;
};

const openOnlyGroup = (group) => {
  closeAllGroups();

  if (group === 'customer') {
    customerOrdersOpen.value = true;
  }
  if (group === 'inventory') {
    inventoryOpen.value = true;
  }
  if (group === 'purchasing') {
    purchasingOpen.value = true;
  }
  if (group === 'employee-admin') {
    employeeAdminOpen.value = true;
  }
};

const toggleGroup = (group) => {
  const isOpen =
    (group === 'customer' && customerOrdersOpen.value) ||
    (group === 'inventory' && inventoryOpen.value) ||
    (group === 'purchasing' && purchasingOpen.value) ||
    (group === 'employee-admin' && employeeAdminOpen.value);

  if (isOpen) {
    closeAllGroups();
    return;
  }

  openOnlyGroup(group);
};

const setActiveMenu = (menu) => {
  activeMenu.value = menu;
};

const toggleAndActivate = (group, menu) => {
  setActiveMenu(menu);
  toggleGroup(group);
};

const syncActiveMenuByRoute = () => {
  if (route.path === '/' || route.path === '/dashboard') {
    activeMenu.value = 'dashboard';
    return;
  }
  if (route.path.startsWith('/customers')) {
    activeMenu.value = 'customer-profile';
    return;
  }
  if (route.path === '/pos') {
    activeMenu.value = 'walk-in';
    return;
  }
  if (route.path.startsWith('/payments')) {
    activeMenu.value = 'payment-management';
    return;
  }
  if (route.path.startsWith('/orders')) {
    activeMenu.value = 'orders';
    return;
  }
  if (route.path.startsWith('/deliveries')) {
    activeMenu.value = 'logistics';
    return;
  }
  if (route.path === '/inventory') {
    activeMenu.value = 'current-stock';
    return;
  }
  if (route.path === '/inventory/categories') {
    activeMenu.value = 'inventory-categories';
    return;
  }
  if (route.path === '/inventory/movements') {
    activeMenu.value = 'stock-movement';
    return;
  }
  if (route.path === '/reports') {
    activeMenu.value = 'sales-report';
    return;
  }
  if (
    route.path === '/purchasing' ||
    route.path.startsWith('/purchasing/create') ||
    route.path.startsWith('/purchasing/edit') ||
    route.path.startsWith('/purchasing/receive')
  ) {
    activeMenu.value = 'suppliers-profile';
    return;
  }
  if (route.path === '/employees') {
    activeMenu.value = 'employees';
    return;
  }
  if (route.path === '/login-audit-logs') {
    activeMenu.value = 'login-audit';
    return;
  }

  activeMenu.value = '';
};

const syncGroupByRoute = () => {
  if (isCustomerOrdersRoute.value) {
    openOnlyGroup('customer');
    return;
  }
  if (isInventoryRoute.value) {
    openOnlyGroup('inventory');
    return;
  }
  if (isPurchasingRoute.value) {
    openOnlyGroup('purchasing');
    return;
  }
  if (isEmployeeAdminRoute.value) {
    openOnlyGroup('employee-admin');
    return;
  }

  closeAllGroups();
};

const pageTitle = computed(() => {
  const titles = {
    'Dashboard': 'Operations Dashboard',
    'OrdersList': 'Sales Orders',
    'CreateOrder': 'Create Order',
    'PaymentManagement': 'Collections',
    'CustomerList': 'Customers',
    'CustomerProfile': 'Customer Profile',
    'POSScreen': 'Point of Sale',
    'DeliveryList': 'Order Fulfillment',
    'DeliveryDetails': 'Fulfillment Details',
    'PurchasingDashboard': 'Purchase Orders',
    'CreatePurchaseOrder': 'Create Purchase Order',
    'ReceivingReport': 'Receiving Report',
    'InventoryView': 'Stock On Hand',
    'CategoriesView': 'Product Categories',
    'StockMovement': 'Inventory Audit',
    'EmployeeManagement': 'Employees & Accounts',
    'LoginAuditLog': 'Login Audit',
    'ProfilePage': 'My Profile',
    'ReportsPage': 'Reports & Analytics'
  };
  return titles[route.name] || 'Dashboard';
});

const pageSummary = computed(() => {
  const summaries = {
    Dashboard: 'Live operational metrics across sales, inventory, logistics, and purchasing.',
    OrdersList: 'Review sales orders, create new orders, and manage fulfillment scheduling from one screen.',
    CreateOrder: 'Create customer orders with customer-driven pricing, item quantities, and logistics scheduling.',
    PaymentManagement: 'Track paid, unpaid, and partially paid orders while recording customer collections.',
    CustomerList: 'Manage customer records and relationship details.',
    CustomerProfile: 'View customer history, orders, and contact information.',
    POSScreen: 'Process walk-in sales with live product and stock data.',
    DeliveryList: 'Review delivery and pickup schedules, then update order fulfillment progress.',
    DeliveryDetails: 'Inspect delivery, pickup, and order fulfillment details.',
    PurchasingDashboard: 'Monitor suppliers, purchase orders, and receiving.',
    CreatePurchaseOrder: 'Create supplier purchase orders from current inventory demand.',
    ReceivingReport: 'Confirm received stock, defects, and shortages against a purchase order.',
    InventoryView: 'Review stock on hand, reorder points, and adjustments.',
    CategoriesView: 'Create, edit, and maintain the product categories used across inventory and product management.',
    StockMovement: 'Audit every inbound, outbound, defect, and manual stock event.',
    EmployeeManagement: 'Maintain employee records, roles, and user accounts.',
    LoginAuditLog: 'Audit login attempts, blocked account access, and logout activity.',
    ProfilePage: 'Update account identity and access information.',
    ReportsPage: 'Analyze sales, payments, inventory, and customer performance'
  };

  return summaries[route.name] || 'Monitor daily activity and key business operations.';
});

const logout = async () => {
  await authStore.logout();
  router.push('/login');
};

onMounted(() => {
  syncGroupByRoute();
  syncActiveMenuByRoute();

  setInterval(() => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString();
  }, 1000);
});

watch(() => route.path, () => {
  syncGroupByRoute();
  syncActiveMenuByRoute();
});
</script>

<style scoped>
.app-wrapper {
  display: flex;
  height: 100vh;
  background-color: #f4f6f8;
  align-items: stretch;
  overflow: hidden;
}

.sidebar {
  width: 280px;
  background: linear-gradient(180deg, #0a1d37 0%, #08172c 100%);
  color: white;
  padding: 18px 16px;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  position: sticky;
  top: 0;
  z-index: 20;
  flex-shrink: 0;
  overflow-y: auto;
  box-shadow: 4px 0 18px rgba(15, 23, 42, 0.16);
}

.logo {
  margin-bottom: 16px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-bottom: 2px solid #e57c2a;
  border-radius: 12px;
  padding: 14px 12px;
  background: rgba(255, 255, 255, 0.04);
}

.logo h2 {
  color: #e57c2a;
  font-size: 36px;
  line-height: 1;
  margin: 0;
  letter-spacing: 0.01em;
}

.logo p {
  margin: 4px 0 0;
  color: #b7c3d4;
  font-size: 13px;
  line-height: 1;
}

.nav-menu {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}

.nav-menu > li {
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 10px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.025);
}

.nav-menu a {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 10px 12px;
  color: #f0f4fa;
  text-decoration: none;
  border-radius: 0;
  transition: all 0.18s ease;
  font-size: 15px;
  font-weight: 700;
}

.nav-menu a:hover {
  background-color: rgba(255, 255, 255, 0.08);
}

.nav-menu a.active {
  background-color: rgba(255, 255, 255, 0.12);
  color: white;
  position: relative;
}

.nav-menu a.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 8px;
  bottom: 8px;
  width: 3px;
  border-radius: 3px;
  background: #e57c2a;
}

.menu-icon {
  width: 22px;
  text-align: center;
  font-size: 16px;
  line-height: 1;
}

.tab-label {
  line-height: 1.1;
}

.menu-group {
  margin-bottom: 0;
}

.group-head {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 44px;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
  color: #f0f4fa;
  font-size: 15px;
  font-weight: 700;
  padding: 10px 12px;
  border-radius: 0;
  position: relative;
}

.group-head.active {
  background-color: rgba(184, 201, 224, 0.28);
  color: white;
}

.group-head.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 8px;
  bottom: 8px;
  width: 3px;
  border-radius: 3px;
  background: #e57c2a;
}

.group-head:hover {
  background-color: rgba(255, 255, 255, 0.08);
}

.group-caret {
  margin-left: auto;
  font-size: 14px;
  color: #cfd8e6;
}

.sub-links {
  margin-left: 0;
  display: grid;
  gap: 0;
  border-top: 1px solid rgba(255, 255, 255, 0.16);
  background: rgba(0, 0, 0, 0.12);
}

.sub-links a {
  min-height: 38px;
  font-size: 14px;
  padding: 9px 12px 9px 44px;
  border-top: 1px solid rgba(255, 255, 255, 0.10);
  font-weight: 600;
}

.sidebar-footer {
  border-top: 1px solid rgba(255, 255, 255, 0.16);
  padding-top: 14px;
  margin-top: auto;
  display: grid;
  gap: 10px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.04);
  cursor: pointer;
}

.user-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #e57c2a;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}

.user-name {
  font-weight: 700;
  margin: 0;
  color: #fff;
  font-size: 16px;
}

.user-role {
  font-size: 12px;
  color: #9caac2;
  margin: 2px 0 0;
  text-transform: capitalize;
}

.logout-btn {
  width: 100%;
  padding: 11px 12px;
  background-color: rgba(255, 255, 255, 0.04);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 10px;
  cursor: pointer;
  font-weight: 700;
  font-size: 16px;
  transition: all 0.25s;
  display: flex;
  align-items: center;
  gap: 10px;
  justify-content: flex-start;
}

.logout-btn:hover {
  background-color: rgba(255, 255, 255, 0.08);
}

.logout-icon {
  font-size: 22px;
}

.main-content {
  flex: 1;
  min-width: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.top-bar {
  background: white;
  padding: 22px 32px;
  border-bottom: 1px solid #e7edf4;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.eyebrow {
  margin: 0 0 6px;
  color: #d77227;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  font-size: 11px;
  font-weight: 700;
}

.top-bar h1 {
  margin: 0;
  color: #0a1d37;
  font-size: 30px;
  line-height: 1.1;
  font-weight: 800;
}

.page-summary {
  margin: 8px 0 0;
  color: #526277;
  font-size: 14px;
}

.header-actions {
  display: flex;
  gap: 20px;
  align-items: center;
}

.time {
  color: #64748b;
  font-family: monospace;
  font-size: 14px;
  font-weight: 700;
}

.content-area {
  flex: 1;
  min-height: 0;
  padding: 28px 30px;
  overflow-y: auto;
}

@media (max-width: 1024px) and (min-width: 769px) {
  .sidebar {
    width: 270px;
    padding: 14px;
  }

  .logo {
    margin-bottom: 14px;
    padding-bottom: 8px;
  }

  .logo h2 {
    font-size: 34px;
  }

  .logo p {
    font-size: 12px;
  }

  .nav-menu a,
  .group-head {
    font-size: 18px;
  }

  .sub-links a {
    font-size: 17px;
  }

  .user-name {
    font-size: 16px;
  }

  .user-role {
    font-size: 12px;
  }

  .logout-btn {
    font-size: 16px;
  }

  .top-bar {
    padding: 16px 20px;
  }

  .top-bar h1 {
    font-size: 22px;
  }

  .content-area {
    padding: 20px;
  }
}

@media (max-width: 768px) {
  .sidebar {
    width: 230px;
    position: relative;
    top: auto;
    min-height: auto;
  }

  .logo h2 {
    font-size: 30px;
  }

  .logo p {
    font-size: 12px;
  }

  .nav-menu a,
  .group-head {
    font-size: 16px;
  }

  .sub-links a {
    font-size: 15px;
  }

  .content-area {
    padding: 20px;
  }
}
</style>
