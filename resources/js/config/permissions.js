/**
 * Role-based access control (RBAC) configuration.
 *
 * MODULE_PERMISSIONS maps each module key to the roles that are allowed to access it.
 * Any route tagged with a module that is not in the user's allowed list will be
 * blocked in the router guard and hidden from the sidebar.
 *
 * Roles that exist in the system:
 *   admin       – full access
 *   sales       – customer orders, customer/product lookup, walk-in/POS
 *   delivery    – logistics only
 *   inventory   – inventory (stock, categories, movements)
 *   purchasing  – purchasing + inventory (read) + reports
 */

export const MODULE_PERMISSIONS = {
  dashboard:       ['admin'],
  'customer-orders': ['admin', 'sales'],
  payments:        ['admin'],
  inventory:       ['admin', 'inventory', 'purchasing'],
  purchase_orders: ['admin', 'purchasing', 'inventory'],
  purchasing:      ['admin', 'purchasing'],
  receiving:       ['admin', 'inventory'],
  logistics:       ['admin', 'delivery'],
  employees:       ['admin'],
  reports:         ['admin', 'purchasing'],
  profile:         ['admin', 'sales', 'delivery', 'inventory', 'purchasing'], // always visible
};

export const ACTION_PERMISSIONS = {
  'orders.create': ['admin', 'sales'],
  'orders.edit': ['admin', 'sales'],
  'orders.cancel': ['admin'],
  'products.write': ['admin'],
  'categories.write': ['admin'],
  'inventory.adjust': ['admin', 'inventory'],
  'inventory.profile.write': ['admin'],
  'customers.write': ['admin'],
  'payments.manage': ['admin'],
  'purchase_orders.create': ['admin', 'purchasing'],
  'purchase_orders.edit': ['admin', 'purchasing'],
  'purchase_orders.receive': ['admin', 'inventory'],
  'purchase_orders.status': ['admin', 'purchasing'],
  'suppliers.write': ['admin'],
  'reports.generate': ['admin', 'purchasing'],
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
  PaymentManagement:  'payments',
  CustomerList:       'customer-orders',
  CustomerProfile:    'customer-orders',
  POSScreen:          'customer-orders',
  DeliveryList:       'logistics',
  DeliveryDetails:    'logistics',
  PurchasingDashboard:'purchase_orders',
  CreatePurchaseOrder:'purchasing',
  EditPurchaseOrder:  'purchasing',
  ReceivingReport:    'receiving',
  ReportsPage:        'reports',
  InventoryView:      'inventory',
  StockMovement:      'inventory',
  CategoriesView:     'inventory',
  ProductList:        'inventory',
  EmployeeManagement: 'employees',
  LoginAuditLog:      'employees',
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

export function canPerform(role, action) {
  if (!role || !action) return false;
  const allowed = ACTION_PERMISSIONS[action];
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
