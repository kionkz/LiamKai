# LiamKai Fish Business - Database Relationships Diagram

## Entity Relationship Structure

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         MASTER ENTITIES                                  │
├─────────────────────────────────────────────────────────────────────────┤

    EMPLOYEES                CUSTOMERS            SUPPLIERS
    ┌────────┐              ┌────────┐           ┌────────┐
    │ id     │              │ id     │           │ id     │
    │ name   │              │ name   │           │ name   │
    │ email  │              │ type   │           │ email  │
    │ role   │              │ phone  │           │ phone  │
    │ status │              │ balance│           │ status │
    └────────┘              └────────┘           └────────┘
        ▲                        ▲                    ▲
        │                        │                    │
        │                        │ 1:Many            │
        │                        └─────┬─────────────┘
        │                              │
        │                          1:Many
        │                         (Orders)
        │
        │                    PRODUCT CATALOG
        │                    ┌──────────────┐
        │                    │   PRODUCTS   │
        │                    ├──────────────┤
        │                    │ id           │
        │                    │ name         │
        │                    │ category     │
        │                    │ unit_measure │
        │                    │ base_price   │
        │                    │ status       │
        │                    └──────────────┘
        │                    ▲    ▲    ▲
        │                    │    │    │
        │        1:1      1:Many 1:Many 1:Many
        │                │      │       │
        │                │      │       └──────────────────┐
        │                │      │                          │
        │   ┌────────────┴──┐  │  ┌──────────────────┐   │
        │   │  INVENTORY    │  │  │  PRICING         │   │
        │   ├───────────────┤  │  ├──────────────────┤   │
        │   │ id            │  │  │ id               │   │
        │   │ product_id    │  │  │ product_id       │   │
        │   │ quantity      │  │  │ retail_price     │   │
        │   │ reorder_point │  │  │ wholesale_price  │   │
        │   │ status        │  │  │ effective_date   │   │
        │   └───────────────┘  │  └──────────────────┘   │
        │                       │                          │
        │                   1:Many (StockMovements)        │
        │                       │                          │
        │                   ┌───┴──────────────┐          │
        │                   │ STOCK_MOVEMENTS  │          │
        │                   ├──────────────────┤          │
        │                   │ id               │          │
        │                   │ product_id       │          │
        │                   │ quantity         │          │
        │                   │ type (in/out)    │          │
        │                   │ reference        │          │
        │                   └──────────────────┘          │
        │                       ▲                          │
        │                       └──────────────┐          │
        │                                      └─(audit)──┘

│                        MANY:MANY                        │
│         PRODUCTS ◄──────────────────► SUPPLIERS        │
│         (product_supplier junction table)              │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                    SALES & ORDER MANAGEMENT                              │
├─────────────────────────────────────────────────────────────────────────┤

    CUSTOMERS (1)
        │
        │ 1:Many
        │
    ┌─────────────────────────────────────┐
    │          ORDERS                     │
    ├─────────────────────────────────────┤
    │ id                                  │
    │ customer_id                         │
    │ order_type (retail/wholesale)       │
    │ total_amount                        │
    │ payment_status                      │
    │ delivery_status                     │
    │ notes                               │
    └─────────────────────────────────────┘
        ▲              ▲              ▲
        │              │              │
    1:Many         1:Many         1:1
    (Items)    (Payments)    (Delivery)
        │              │              │
    ┌───┴──────┐  ┌────┴─────┐  ┌────┴──────┐
    │ ORDER    │  │ PAYMENTS │  │ DELIVERY  │
    │ ITEMS    │  ├──────────┤  ├───────────┤
    ├──────────┤  │ id       │  │ id        │
    │ id       │  │ amount   │  │ order_id  │
    │ order_id │  │ method   │  │ employee_id
    │ product_ │  │ status   │  │ status    │
    │ id       │  │ reference│  │ address   │
    │ quantity │  └──────────┘  └───────────┘
    │ price    │                      │
    │ subtotal │              1:Many  │
    └──────────┘            (Employees)
        │
        │ FK to Products
        v
    [PRODUCTS]

└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                    PURCHASING & RESTOCKING                               │
├─────────────────────────────────────────────────────────────────────────┤

    SUPPLIERS (1)
        │
        │ 1:Many
        │
    ┌──────────────────────────────────────────┐
    │        PURCHASE_ORDERS                   │
    ├──────────────────────────────────────────┤
    │ id                                       │
    │ supplier_id                              │
    │ order_number (unique)                    │
    │ total_amount                             │
    │ ordered_quantity                         │
    │ received_quantity                        │
    │ status (pending/received/etc)            │
    │ expected_delivery_date                   │
    │ payment_status                           │
    └──────────────────────────────────────────┘
        ▲              ▲
        │              │
    1:Many         1:Many
    (Items)    (Payments)
        │              │
    ┌───┴───────────┐  │
    │ PURCHASE_ORDER│  │
    │ ITEMS         │  │
    ├───────────────┤  │
    │ id            │  │
    │ purchase_order│  │
    │ _id           │  │
    │ product_id    │  │
    │ quantity      │  │
    │ received_qty  │  │
    │ price         │  │
    │ subtotal      │  │
    └───────────────┘  │
        │               │
        │ FK to Products
        │
    ┌───┴──────────────────────┐
    │      PAYMENTS            │
    ├───────────────────────────┤
    │ id                        │
    │ purchase_order_id         │
    │ amount                    │
    │ payment_method            │
    │ status                    │
    └───────────────────────────┘
        (Can also reference Orders for
         customer payment tracking)

    Stock Movement Generated on Receipt:
    PurchaseOrderItem.received_quantity 
         ↓
    StockMovement (stock_in)
         ↓
    Inventory (quantity updated)

└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                    REPORTING & ANALYTICS                                 │
├─────────────────────────────────────────────────────────────────────────┤

    SALES_REPORTS
    ├─────────────────────────────────
    │ id
    │ order_id (FK to Orders)
    │ total_sales
    │ quantity_sold
    │ sale_date
    │ customer_type
    │ total_paid
    │ outstanding
    └─────────────────────────────────

    Generated from: ORDER → PAYMENT data
    Used for: Daily/Monthly reports

└─────────────────────────────────────────────────────────────────────────┘
```

## SQL Query Examples

### Example 1: Get Total Stock of a Product
```sql
SELECT 
    p.id, 
    p.name, 
    i.quantity, 
    i.status
FROM products p
LEFT JOIN inventory i ON p.id = i.product_id
WHERE p.id = 1;
```

### Example 2: Get All Orders for a Customer
```sql
SELECT 
    o.id,
    o.order_type,
    o.total_amount,
    o.payment_status,
    o.delivery_status
FROM orders o
WHERE o.customer_id = 5
ORDER BY o.created_at DESC;
```

### Example 3: Get Current Price for a Product
```sql
SELECT 
    p.name,
    pr.retail_price,
    pr.wholesale_price,
    pr.effective_date
FROM products p
JOIN pricing pr ON p.id = pr.product_id
WHERE p.id = 1 
AND pr.effective_date <= CURDATE()
AND (pr.end_date IS NULL OR pr.end_date > CURDATE())
AND pr.status = 'active'
LIMIT 1;
```

### Example 4: Track Inventory Movement
```sql
SELECT 
    sm.created_at,
    sm.type,
    sm.quantity,
    p.name,
    sm.reference,
    sm.notes
FROM stock_movements sm
JOIN products p ON sm.product_id = p.id
WHERE sm.product_id = 1
ORDER BY sm.created_at DESC;
```

### Example 5: Get Wholesale Orders Pending Delivery
```sql
SELECT 
    o.id,
    c.name as customer_name,
    o.total_amount,
    o.outstanding_balance,
    COUNT(oi.id) as item_count
FROM orders o
JOIN customers c ON o.customer_id = c.id
LEFT JOIN order_items oi ON o.id = oi.order_id
WHERE o.order_type = 'wholesale'
AND o.delivery_status = 'pending'
GROUP BY o.id
ORDER BY o.created_at ASC;
```

## Notes

- All foreign keys use `onDelete('cascade')` or `onDelete('restrict')` appropriately
- Decimal fields use `decimal(10, 2)` for currency precision
- Timestamps included on all tables for audit trails
- Status enums prevent invalid values at database level
- Unique constraints prevent duplicates where needed
- Indexes on frequently queried columns for performance
