import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

const Login = () => import('../views/Login.vue');
const AppLayout = () => import('../views/Dashboard.vue');
const DashboardHome = () => import('../views/Dashboard/Index.vue');
const OrdersList = () => import('../views/Orders/OrdersList.vue');
const CreateOrder = () => import('../views/Orders/CreateOrder.vue');
const CustomerProfile = () => import('../views/Customers/CustomerProfile.vue');
const CustomerList = () => import('../views/Customers/CustomerList.vue');
const POSScreen = () => import('../views/POS/POSScreen.vue');
const ProductList = () => import('../views/Products/ProductList.vue');
const DeliveryList = () => import('../views/Delivery/DeliveryList.vue');
const DeliveryDetails = () => import('../views/Delivery/DeliveryDetails.vue');
const PurchasingDashboard = () => import('../views/Purchasing/PurchasingDashboard.vue');
const PurchasingPayment = () => import('../views/Purchasing/BlankPurchasingPayment.vue');
const CreatePurchaseOrder = () => import('../views/Purchasing/CreatePurchaseOrder.vue');
const EditPurchaseOrder = () => import('../views/Purchasing/CreatePurchaseOrder.vue');
const ReceivingReport = () => import('../views/Purchasing/ReceivingReport.vue');
const InventoryView = () => import('../views/Inventory/InventoryView.vue');
const StockMovement = () => import('../views/Inventory/StockMovement.vue');
const EmployeeManagement = () => import('../views/Admin/EmployeeManagement.vue');
const ReportsPage = () => import('../views/Reports/ReportsPage.vue');
const ProfilePage = () => import('../views/Profile/ProfilePage.vue');

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'Dashboard',
        component: DashboardHome,
      },
      {
        path: 'orders',
        name: 'OrdersList',
        component: OrdersList,
      },
      {
        path: 'orders/create',
        name: 'CreateOrder',
        component: CreateOrder,
      },
      {
        path: 'customers',
        name: 'CustomerList',
        component: CustomerList,
      },
      {
        path: 'customers/:id',
        name: 'CustomerProfile',
        component: CustomerProfile,
      },
      {
        path: 'products',
        name: 'ProductList',
        component: ProductList,
      },
      {
        path: 'pos',
        name: 'POSScreen',
        component: POSScreen,
      },
      {
        path: 'deliveries',
        name: 'DeliveryList',
        component: DeliveryList,
      },
      {
        path: 'deliveries/:id',
        name: 'DeliveryDetails',
        component: DeliveryDetails,
      },
      {
        path: 'purchasing',
        name: 'PurchasingDashboard',
        component: PurchasingDashboard,
      },
      {
        path: 'purchasing/payments',
        name: 'PurchasingPayment',
        component: PurchasingPayment,
      },
      {
        path: 'purchasing/create',
        name: 'CreatePurchaseOrder',
        component: CreatePurchaseOrder,
      },
      {
        path: 'purchasing/edit/:id',
        name: 'EditPurchaseOrder',
        component: EditPurchaseOrder,
      },
      {
        path: 'purchasing/receive/:id',
        name: 'ReceivingReport',
        component: ReceivingReport,
      },
      {
        path: 'inventory',
        name: 'InventoryView',
        component: InventoryView,
      },
      {
        path: 'inventory/movements',
        name: 'StockMovement',
        component: StockMovement,
      },
      {
        path: 'employees',
        name: 'EmployeeManagement',
        component: EmployeeManagement,
      },
      {
        path: 'profile',
        name: 'ProfilePage',
        component: ProfilePage,
      },
      {
        path: 'reports',
        name: 'ReportsPage',
        component: ReportsPage,
      },
    ],
  },
  {
    path: '/dashboard',
    redirect: '/'
  }
];

const router = createRouter({
  // Use app root for SPA history. Vite's BASE_URL can be /build/ for assets,
  // which incorrectly prefixes route links (e.g., /build/inventory/movements).
  history: createWebHistory('/'),
  routes
});

// Flag to track if auth has been checked
let authChecked = false;

// Navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();
  
  // Ensure auth is checked on first load
  if (!authChecked) {
    authStore.checkAuth();
    authChecked = true;
  }
  
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
