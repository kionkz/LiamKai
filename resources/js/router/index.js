import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

const Login = () => import('../views/Login.vue');
const Dashboard = () => import('../views/Dashboard.vue');
const OrdersList = () => import('../views/Orders/OrdersList.vue');
const CreateOrder = () => import('../views/Orders/CreateOrder.vue');
const CustomerProfile = () => import('../views/Customers/CustomerProfile.vue');
const CustomerList = () => import('../views/Customers/CustomerList.vue');
const POSScreen = () => import('../views/POS/POSScreen.vue');
const ProductList = () => import('../views/Products/ProductList.vue');
const DeliveryList = () => import('../views/Delivery/DeliveryList.vue');
const DeliveryDetails = () => import('../views/Delivery/DeliveryDetails.vue');
const PurchasingDashboard = () => import('../views/Purchasing/PurchasingDashboard.vue');
const CreatePurchaseOrder = () => import('../views/Purchasing/CreatePurchaseOrder.vue');
const EditPurchaseOrder = () => import('../views/Purchasing/CreatePurchaseOrder.vue');
const ReceivingReport = () => import('../views/Purchasing/ReceivingReport.vue');
const InventoryView = () => import('../views/Inventory/InventoryView.vue');
const StockMovement = () => import('../views/Inventory/StockMovement.vue');
const EmployeeManagement = () => import('../views/Admin/EmployeeManagement.vue');
const ReportsPage = () => import('../views/Reports/ReportsPage.vue');

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    name: 'Dashboard',
    component: Dashboard,
    meta: { requiresAuth: true }
  },
  {
    path: '/dashboard',
    redirect: '/'
  },
  // Orders
  {
    path: '/orders',
    name: 'OrdersList',
    component: OrdersList,
    meta: { requiresAuth: true }
  },
  {
    path: '/orders/create',
    name: 'CreateOrder',
    component: CreateOrder,
    meta: { requiresAuth: true }
  },
  // Customers
  {
    path: '/customers',
    name: 'CustomerList',
    component: CustomerList,
    meta: { requiresAuth: true }
  },
  // Products
  {
    path: '/products',
    name: 'ProductList',
    component: ProductList,
    meta: { requiresAuth: true }
  },
  {
    path: '/customers/:id',
    name: 'CustomerProfile',
    component: CustomerProfile,
    meta: { requiresAuth: true }
  },
  // POS
  {
    path: '/pos',
    name: 'POSScreen',
    component: POSScreen,
    meta: { requiresAuth: true }
  },
  // Delivery
  {
    path: '/deliveries',
    name: 'DeliveryList',
    component: DeliveryList,
    meta: { requiresAuth: true }
  },
  {
    path: '/deliveries/:id',
    name: 'DeliveryDetails',
    component: DeliveryDetails,
    meta: { requiresAuth: true }
  },
  // Purchasing
  {
    path: '/purchasing',
    name: 'PurchasingDashboard',
    component: PurchasingDashboard,
    meta: { requiresAuth: true }
  },
  {
    path: '/purchasing/create',
    name: 'CreatePurchaseOrder',
    component: CreatePurchaseOrder,
    meta: { requiresAuth: true }
  },
  {
    path: '/purchasing/edit/:id',
    name: 'EditPurchaseOrder',
    component: EditPurchaseOrder,
    meta: { requiresAuth: true }
  },
  {
    path: '/purchasing/receive/:id',
    name: 'ReceivingReport',
    component: ReceivingReport,
    meta: { requiresAuth: true }
  },
  // Inventory
  {
    path: '/inventory',
    name: 'InventoryView',
    component: InventoryView,
    meta: { requiresAuth: true }
  },
  {
    path: '/inventory/movements',
    name: 'StockMovement',
    component: StockMovement,
    meta: { requiresAuth: true }
  },
  // Admin
  {
    path: '/employees',
    name: 'EmployeeManagement',
    component: EmployeeManagement,
    meta: { requiresAuth: true }
  },
  // Reports
  {
    path: '/reports',
    name: 'ReportsPage',
    component: ReportsPage,
    meta: { requiresAuth: true }
  }
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
});

// Navigation guard
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore();
  const isAuthenticated = authStore.isAuthenticated;

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login');
  } else if (to.path === '/login' && isAuthenticated) {
    next('/');
  } else {
    next();
  }
});

export default router;
