/**
 * Role-based access control (RBAC) configuration.
 *
 * MODULE_PERMISSIONS maps each module key to the roles that are allowed to access it.
 * Any route tagged with a module that is not in the user's allowed list will be
 * blocked in the router guard and hidden from the sidebar.
 *
 * Roles that exist in the system:
 *   admin       – full access
 *   sales       – customer orders (customers, orders, payments, walk-in/POS)
 *   delivery    – logistics only
 *   inventory   – inventory (stock, categories, movements)
 *   purchasing  – purchasing + inventory (read) + reports
 */

export const MODULE_PERMISSIONS = {
  dashboard:       ['admin'],
  'customer-orders': ['admin', 'sales'],
  inventory:       ['admin', 'inventory', 'purchasing'],
  purchasing:      ['admin', 'purchasing'],
  logistics:       ['admin', 'delivery'],
  employees:       ['admin'],
  profile:         ['admin', 'sales', 'delivery', 'inventory', 'purchasing'], // always visible
};

/**
 * Map of route names → module key.
 * Routes with null module are always accessible to authenticated users.
 */
export const ROUTE_MODULE_MAP = {
  Dashboard:          'dashboard',
  OrdersList:         'customer-orders',
  CreateOrder:        'customer-orders',
  OrderDetail:        'customer-orders',
  PaymentManagement:  'customer-orders',
  CustomerList:       'customer-orders',
  CustomerProfile:    'customer-orders',
  POSScreen:          'customer-orders',
  DeliveryList:       'logistics',
  DeliveryDetails:    'logistics',
  PurchasingDashboard:'purchasing',
  CreatePurchaseOrder:'purchasing',
  EditPurchaseOrder:  'purchasing',
  ReceivingReport:    'purchasing',
  ReportsPage:        'purchasing',
  InventoryView:      'inventory',
  StockMovement:      'inventory',
  CategoriesView:     'inventory',
  ProductList:        'inventory',
  EmployeeManagement: 'employees',
  ProfilePage:        'profile',
};

/**
 * Returns true if the given role can access the given module.
 * @param {string} role
 * @param {string} module
 */
export function canAccess(role, module) {
  if (!role || !module) return false;
  const allowed = MODULE_PERMISSIONS[module];
  if (!allowed) return false;
  return allowed.includes(role);
}

/**
 * Returns the default landing route for a given role (used after login).
 * @param {string} role
 */
export function defaultRouteForRole(role) {
  switch (role) {
    case 'admin':      return '/';
    case 'sales':      return '/orders';
    case 'delivery':   return '/deliveries';
    case 'inventory':  return '/inventory';
    case 'purchasing': return '/purchasing';
    default:           return '/';
  }
}
