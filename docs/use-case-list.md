# Use Case List

> Derived from source code analysis of the LiamKai system (routes, controllers, Vue router, and RBAC config).
> Items marked **[ADDED]** were not in the original list but are present in the codebase.

---

## Actors

| Actor | Role Description |
|-------|-----------------|
| **Admin / Manager** | Full system access |
| **Sales Employee** | Customer orders, POS, customer/product lookup |
| **Delivery Employee** | Logistics and delivery management |
| **Inventory Employee** | Inventory, stock, categories |
| **Purchasing Employee** | Purchase orders, suppliers, reports |

---

## USER & AUTHENTICATION MODULE
*(All authenticated users)*

1. Log In
2. Log Out
3. View My Profile
4. Update My Profile
5. Change Login Credentials
6. **[ADDED] Force Change Password on First Login** — triggered automatically when `must_change_password = true`

---

## CUSTOMER ORDER MANAGER
*(Admin, Sales)*

1. Create Customer Order
2. **[ADDED] Process POS Transaction** — walk-in / point-of-sale order distinct from regular orders
3. View Order History
4. **[ADDED] View Order Details**
5. Update Order Status
6. Cancel Order *(Admin only)*
7. Set / Update Order Priority
8. Manage Customer Profile *(view, create, update — Admin; view only for Sales)*
9. **[ADDED] View Customer List**
10. Process Payment
11. View Payment Management
12. Add Payment
13. View Payment History

---

## LOGISTICS & DELIVERY MODULE
*(Admin, Delivery Employee)*

1. View Delivery Schedule
2. View Delivery Details
3. Update Delivery Status
4. **[ADDED] Create / Schedule Delivery** *(Admin only — POST /deliveries)*
5. **[ADDED] View Logistics Orders** — filtered order list used by delivery staff

---

## PURCHASING & SUPPLIERS MANAGER
*(Admin, Purchasing Employee)*

1. Create Purchase Order
2. View Purchase Orders
3. **[ADDED] Edit Purchase Order**
4. Update Purchase Order Status
5. **[ADDED] Delete Purchase Order** *(Admin only)*
6. Record Product Receipt *(Receiving Report — Admin, Inventory)*
7. **[ADDED] View Receiving Report**
8. Manage Supplier Profile *(create, view, update — Admin; view for Purchasing)*
9. **[ADDED] View Supplier Details**
10. Generate Business Report
11. Record Losses *(inventory damage/theft adjustment)*

---

## INVENTORY MODULE
*(Admin, Inventory Employee, Purchasing Employee)*

1. View All Inventory
2. View Inventory Details
3. Manage Product Catalog *(Admin only for write; all inventory roles for view)*
4. **[ADDED] View Product Details**
5. Manage Category *(Admin only for write)*
6. View Stock Movements
7. Monitor Stock Levels
8. **[ADDED] View Low Stock Alerts**
9. Adjust Inventory
10. Edit Product Details *(Admin only)*
11. Edit Pricing *(Admin only)*
12. View Product Profile
13. Select Reason for Adjustment
14. Select Batch for Adjustment
15. Edit Discounted Amount
16. Add Unit of Measure

---

## ADMINISTRATION MODULE
*(Admin / Manager only)*

1. View Dashboard Summary
2. View Analytics
3. Create Employee Account *(creates system login for an employee)*
4. Manage Employee Records *(create, view, update, deactivate)*
5. **[ADDED] Reset Employee Login Credentials**
6. Activate / Deactivate Employee Account
7. Assign Roles & Permissions *(set during employee/account creation)*
8. Revoke Employee Login Account
9. Manage Categories
10. Manage Products & Pricing
11. Generate Business Reports
12. View Audit Logs
13. Search / Filter Audit Logs
14. Refresh Login Audit Log
15. Export Audit Logs

---

## Relationship Summary

| Relationship | Type | Description |
|---|---|---|
| Log In → Validate Credentials | `<<include>>` | Login always validates credentials |
| Log In → Force Change Password | `<<extend>>` | Extends when `must_change_password = true` |
| Create Customer Order → Process Payment | `<<extend>>` | Payment may be added at order time |
| Process POS Transaction → Process Payment | `<<include>>` | POS always processes immediate payment |
| Adjust Inventory → Select Reason for Adjustment | `<<include>>` | Reason is required for every adjustment |
| Adjust Inventory → Select Batch for Adjustment | `<<include>>` | Batch must be selected for FIFO deduction |
| Record Product Receipt → Update Inventory | `<<include>>` | Receiving a PO automatically updates stock |
| Create Purchase Order → Manage Supplier Profile | `<<include>>` | A supplier must be selected / linked |
| View Purchase Orders → View Receiving Report | `<<extend>>` | Receiving report is optional detail view |
| Monitor Stock Levels → View Low Stock Alerts | `<<extend>>` | Low-stock alert extends normal monitoring |
| Update My Profile → Change Login Credentials | `<<extend>>` | Password change is optional during profile update |
| Generate Business Report → View Analytics | `<<include>>` | Analytics data is always included in reports |
| Manage Employee Records → Activate/Deactivate Employee Account | `<<extend>>` | Account status can be toggled from employee record |
| Create Employee Account → Assign Roles & Permissions | `<<include>>` | Role is assigned at account creation |
| Revoke Employee Login Account → Deactivate Employee | `<<extend>>` | Revoking account may also deactivate employee |
