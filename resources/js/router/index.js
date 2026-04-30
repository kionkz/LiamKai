import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';
import { ROUTE_MODULE_MAP, canAccess } from '../config/permissions';

const Login = () => import('../views/Login.vue');
const ChangePassword = () => import('../views/Auth/ChangePassword.vue');
const AppLayout = () => import('../views/Dashboard.vue');
const DashboardHome = () => import('../views/Dashboard/Index.vue');
const OrdersList = () => import('../views/Orders/OrdersList.vue');
const CreateOrder = () => import('../views/Orders/CreateOrder.vue');
const OrderDetail = () => import('../views/Orders/OrderDetail.vue');
const CustomerProfile = () => import('../views/Customers/CustomerProfile.vue');
const CustomerList = () => import('../views/Customers/CustomerList.vue');
const POSScreen = () => import('../views/POS/POSScreen.vue');
const PaymentManagement = () => import('../views/Payments/PaymentManagement.vue');
const ProductList = () => import('../views/Products/ProductList.vue');
const DeliveryList = () => import('../views/Delivery/DeliveryList.vue');
const DeliveryDetails = () => import('../views/Delivery/DeliveryDetails.vue');
const PurchasingDashboard = () => import('../views/Purchasing/PurchasingDashboard.vue');
const CreatePurchaseOrder = () => import('../views/Purchasing/CreatePurchaseOrder.vue');
const EditPurchaseOrder = () => import('../views/Purchasing/CreatePurchaseOrder.vue');
const ReceivingReport = () => import('../views/Purchasing/ReceivingReport.vue');
const InventoryView = () => import('../views/Inventory/InventoryView.vue');
const StockMovement = () => import('../views/Inventory/StockMovement.vue');
const CategoriesView = () => import('../views/Inventory/CategoriesView.vue');
const EmployeeManagement = () => import('../views/Admin/EmployeeManagement.vue');
const LoginAuditLog = () => import('../views/Admin/LoginAuditLog.vue');
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
    path: '/change-password',
    name: 'ChangePassword',
    component: ChangePassword,
    meta: { requiresAuth: true, requiresPasswordChange: true }
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
        path: 'payments',
        name: 'PaymentManagement',
        component: PaymentManagement,
      },
      {
        path: 'orders/:id',
        name: 'OrderDetail',
        component: OrderDetail,
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
        path: 'inventory/categories',
        name: 'CategoriesView',
        component: CategoriesView,
      },
      {
        path: 'employees',
        name: 'EmployeeManagement',
        component: EmployeeManagement,
      },
      {
        path: 'login-audit-logs',
        name: 'LoginAuditLog',
        component: LoginAuditLog,
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
router.beforeEach((to) => {
  const authStore = useAuthStore();

  if (!authChecked) {
    authStore.checkAuth();
    authChecked = true;
  }

  const isAuthenticated = authStore.isAuthenticated;
  const mustChangePassword = isAuthenticated && !!authStore.user?.must_change_password;
  const role = authStore.userRole;

  // Redirect unauthenticated users to login
  if (to.meta.requiresAuth !== false && !isAuthenticated) {
    return '/login';
  }

  // Already logged in — send to role's home instead of /login
  if (to.path === '/login' && isAuthenticated) {
    if (mustChangePassword) {
      return '/change-password';
    }

    // If stored auth data has an unknown role, keep user on login page
    // instead of bouncing between /login and /.
    if (!canAccess(role, 'dashboard')) {
      return true;
    }

    return authStore.homeRoute;
  }

  // Force password change before accessing anything else
  if (mustChangePassword && to.path !== '/change-password') {
    return '/change-password';
  }

  // Check module-level permission for the target route
  if (isAuthenticated && to.name) {
    const module = ROUTE_MODULE_MAP[to.name];
    if (module && !canAccess(authStore.userRole, module)) {
      // Avoid redirect loops when target route is already the computed fallback.
      const fallback = authStore.homeRoute || '/login';
      if (fallback === to.path) return '/login';
      return { path: fallback, query: { access_denied: '1' } };
    }
  }
});

export default router;
