# LiamKai — Corrected Use Case List

> All use cases below are verified against the actual routes and controllers in this
> repository. Items that appeared in earlier drafts but have **no corresponding route
> or controller method** have been removed; items that were missing from earlier drafts
> but **do exist in the code** have been added.

---

## 1. User & Authentication Module
> Routes: `POST /login` · `POST /logout` · `GET /profile` · `PUT /profile` · `POST /change-password`
> Role: every authenticated employee (all roles)

| # | Use Case | Relationship |
|---|----------|-------------|
| 1.1 | Log In | Actor initiates |
| 1.2 | Log Out | Actor initiates |
| 1.3 | View My Profile | Actor initiates |
| 1.4 | Update My Profile | `<<extend>>` from View My Profile (optional) |
| 1.5 | Change Password | `<<extend>>` from View My Profile (optional) |

---

## 2. Customer Ordering Module
> Routes: `/customers` · `/orders` · `/payments`
> Role: admin, sales

| # | Use Case | Relationship |
|---|----------|-------------|
| 2.1 | Manage Customer Profile | Actor initiates |
| 2.2 | Create Customer Order | Actor initiates; `<<include>>` Manage Customer Profile (customer must exist) |
| 2.3 | Select Order Type (Wholesale / Retail) | `<<extend>>` from Create Customer Order (optional pricing tier) |
| 2.4 | View Order History | Actor initiates |
| 2.5 | Update Order Status | Actor initiates |
| 2.6 | Cancel Order | `<<extend>>` from Update Order Status (optional path) |
| 2.7 | Process Payment | Actor initiates |
| 2.8 | View Payment Management | Actor initiates |
| 2.9 | Delete Payment | `<<extend>>` from Process Payment (optional) |

---

## 3. Administration Module
> Routes: `/employees` · `/employees/{id}/account` · `/employees/{id}/account/toggle-status`
>         `/products` · `/categories` · `/reports/sales/generate`
>         `/reports/analytics` · `/reports/dashboard-summary`
> Role: admin — full access; purchasing — reports only
>
> **REMOVED from earlier drafts:**
> - ~~View Employee Login Logs~~ — no such endpoint or model in the codebase
> - ~~Assign Roles & Permissions~~ — `role` is a plain field on the employee record
>   (set during Create / Update Employee); there is no separate endpoint for it
> - ~~Manage Pricing~~ (standalone) — pricing is handled inside Manage Products & Pricing

| # | Use Case | Relationship |
|---|----------|-------------|
| 3.1 | Manage Employee Records | Actor initiates |
| 3.2 | Create Employee Login Account | `<<extend>>` from Manage Employee Records (optional sub-action) |
| 3.3 | Revoke Employee Login Account | `<<extend>>` from Manage Employee Records (optional sub-action) |
| 3.4 | Activate / Deactivate Employee Account | `<<extend>>` from Manage Employee Records (optional sub-action) |
| 3.5 | Manage Products & Pricing | Actor initiates |
| 3.6 | Manage Categories | `<<include>>` from Manage Products & Pricing (categories are always needed) |
| 3.7 | Generate Sales Report | Actor initiates (admin + purchasing) |
| 3.8 | View Analytics | Actor initiates (admin + purchasing) |
| 3.9 | View Dashboard Summary | Actor initiates (admin + purchasing) |

---

## 4. Inventory Module
> Routes: `/inventory` · `/inventory/{product}` · `/inventory/movements` · `/inventory/low-stock`
>         `/products` (product catalog — accessible to inventory role)
> Role: admin, inventory; purchasing has read access to inventory & catalog
>
> **REMOVED from earlier drafts:**
> - ~~Generate Inventory Alert~~ — `showLowStock()` returns data only; no alert generation
>   endpoint exists
> - ~~Update Inventory Status~~ — no separate status endpoint on inventory records
> - ~~Track Expiry Dates / Mark Expired Items~~ — no expiry columns or methods in
>   `InventoryController`
>
> **CORRECTED relationships:**
> - It is `Adjust Inventory` (the update action) that `<<include>>`s Record Stock Movement,
>   **not** Monitor Stock Levels
> - Monitor Stock Levels has no `<<extend>>` relationship to Manage Product Catalog

| # | Use Case | Relationship |
|---|----------|-------------|
| 4.1 | Manage Product Catalog | Actor initiates (ProductController; inventory role has access) |
| 4.2 | View Inventory List | Actor initiates |
| 4.3 | View Inventory Details | Actor initiates |
| 4.4 | Adjust Inventory | Actor initiates |
| 4.5 | Record Stock Movement | `<<include>>` from Adjust Inventory (always auto-created on adjustment) |
| 4.6 | View Stock Movements | Actor initiates |
| 4.7 | Monitor Low-Stock Items | Actor initiates |

---

## 5. Purchasing & Suppliers Manager
> Routes: `/suppliers` · `/purchase-orders` · `/purchase-orders/{id}`
>         `/reports/sales/generate` · `/reports/analytics` · `/reports/dashboard-summary`
> Role: admin, purchasing
>
> **REMOVED from earlier drafts:**
> - ~~Record Sales Transaction~~ — no such route or controller method exists
> - ~~Generate Receiving Report~~ — no such route or controller method exists
>
> **CORRECTED relationships:**
> - Manage Supplier Profile and Create Purchase Order are **independent** use cases;
>   there is no `<<include>>` between them (separate resources: `/suppliers` vs
>   `/purchase-orders`)
> - Record Product Receipt is an `<<extend>>` from Update Purchase Order Status
>   (only exercised when status transitions to `received` and `received_items[]` is sent)

| # | Use Case | Relationship |
|---|----------|-------------|
| 5.1 | Manage Supplier Profile | Actor initiates |
| 5.2 | Create Purchase Order | Actor initiates |
| 5.3 | View Purchase Orders | Actor initiates |
| 5.4 | Update Purchase Order Status | Actor initiates |
| 5.5 | Record Product Receipt | `<<extend>>` from Update Purchase Order Status (when status → received) |
| 5.6 | Cancel / Delete Purchase Order | Actor initiates |
| 5.7 | Generate Sales Report | Actor initiates (shared with Administration Module) |
| 5.8 | View Analytics | Actor initiates (shared with Administration Module) |
| 5.9 | View Dashboard Summary | Actor initiates (shared with Administration Module) |

---

## 6. Delivery Logistics
> Routes: `GET /orders/logistics` · `PATCH /orders/{order}/fulfillment-status`
>         `GET|POST /deliveries` · `GET|PUT|PATCH|DELETE /deliveries/{id}`
> Role: admin, delivery
>
> **REMOVED from earlier drafts:**
> - ~~Collect Payment on Delivery~~ — `DeliveryController` contains zero payment logic;
>   payments are handled exclusively by `PaymentController` under the `sales` role
>
> **CORRECTED relationships:**
> - Updating delivery status `<<include>>`s Update Fulfillment Status because
>   `DeliveryController::update()` always syncs `order.delivery_status` on every
>   status change (pending → in_transit → delivered / failed)
> - Process Delivery has been renamed to **Create Delivery Record** (maps to `store()`)

| # | Use Case | Relationship |
|---|----------|-------------|
| 6.1 | View Delivery Schedule | Actor initiates |
| 6.2 | Create Delivery Record | Actor initiates |
| 6.3 | View Delivery Details | Actor initiates |
| 6.4 | Update Delivery Status | Actor initiates |
| 6.5 | Update Fulfillment Status | `<<include>>` from Update Delivery Status (always syncs order) |
| 6.6 | Cancel Delivery | Actor initiates (blocked by controller if status is already `delivered`) |
