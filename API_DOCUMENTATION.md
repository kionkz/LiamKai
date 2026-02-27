# Fish Wholesale System - API Documentation

## Base URL
```
http://localhost:8000/api
```

## Response Format
All endpoints return JSON responses with the following structure:

**Success Response:**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { /* response data */ }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message (if available)"
}
```

---

## PRODUCTS API

### List All Products
- **Endpoint:** `GET /api/products`
- **Description:** Retrieve paginated list of all products
- **Query Parameters:**
  - `page` (optional): Page number (default: 1)
  - `per_page` (optional): Items per page (default: 15)
- **Response:** 200 OK
```json
{
  "success": true,
  "message": "Products retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Red Snapper",
      "category": "Fish",
      "description": "Fresh red snapper",
      "unit": "kg",
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ],
  "pagination": { /* pagination info */ }
}
```

### Create Product
- **Endpoint:** `POST /api/products`
- **Description:** Create a new product (auto-creates inventory record)
- **Request Body:**
```json
{
  "name": "Red Snapper",
  "category": "Fish",
  "description": "Fresh high-quality red snapper",
  "unit": "kg"
}
```
- **Required Fields:** name, category, unit
- **Response:** 201 Created

### Get Product Details
- **Endpoint:** `GET /api/products/{id}`
- **Description:** Retrieve specific product with relationships
- **Response:** 200 OK

### Update Product
- **Endpoint:** `PUT/PATCH /api/products/{id}`
- **Description:** Update product details
- **Response:** 200 OK

### Delete Product
- **Endpoint:** `DELETE /api/products/{id}`
- **Description:** Delete product
- **Response:** 200 OK

---

## CUSTOMERS API

### List All Customers
- **Endpoint:** `GET /api/customers`
- **Description:** Retrieve paginated list of customers
- **Response:** 200 OK

### Create Customer
- **Endpoint:** `POST /api/customers`
- **Description:** Register a new customer (retail or wholesale)
- **Request Body:**
```json
{
  "name": "John's Fresh Market",
  "email": "john@market.com",
  "phone": "+1-555-0100",
  "address": "123 Market Street",
  "type": "wholesale",
  "credit_limit": 50000
}
```
- **Required Fields:** name, email, phone, address, type (retail/wholesale), credit_limit
- **Response:** 201 Created

### Get Customer Details
- **Endpoint:** `GET /api/customers/{id}`
- **Description:** Retrieve customer with order history and metrics
- **Response Includes:**
  - Total orders
  - Total spent
  - Pending balance
  - Credit used
- **Response:** 200 OK

### Update Customer
- **Endpoint:** `PUT/PATCH /api/customers/{id}`
- **Request Body:** Any fields to update
- **Response:** 200 OK

### Delete Customer
- **Endpoint:** `DELETE /api/customers/{id}`
- **Conditions:** Cannot delete if customer has orders
- **Response:** 200 OK or 422 (if has orders)

---

## INVENTORY API

### List All Inventory
- **Endpoint:** `GET /api/inventory`
- **Description:** View stock levels for all products
- **Response:** 200 OK

### Get Product Inventory
- **Endpoint:** `GET /api/inventory/{product_id}`
- **Description:** View detailed inventory for specific product
- **Response Includes:**
  - Current stock level
  - Reorder point
  - Stock movements history
- **Response:** 200 OK

### Update Inventory
- **Endpoint:** `PUT/PATCH /api/inventory/{product_id}`
- **Description:** Adjust stock or update reorder point
- **Request Body:**
```json
{
  "reorder_point": 50,
  "adjustment_quantity": 100,
  "adjustment_reason": "Beginning inventory count"
}
```
- **Response:** 200 OK

### Get Low Stock Items
- **Endpoint:** `GET /api/inventory/low-stock`
- **Description:** List all items below reorder point
- **Response:** 200 OK

---

## ORDERS API (Core Business Logic)

### List All Orders
- **Endpoint:** `GET /api/orders`
- **Description:** Retrieve paginated list of all orders
- **Response:** 200 OK

### Create Order (CRITICAL BUSINESS LOGIC)
- **Endpoint:** `POST /api/orders`
- **Description:** Create customer order and automatically deduct inventory
- **Request Body:**
```json
{
  "customer_id": 1,
  "order_date": "2024-01-20",
  "notes": "Delivery to back entrance",
  "items": [
    {
      "product_id": 1,
      "quantity": 50,
      "unit_price": 15.50
    },
    {
      "product_id": 2,
      "quantity": 30,
      "unit_price": 22.00
    }
  ]
}
```
- **Key Behavior:**
  - ✅ Validates customer exists
  - ✅ Validates all products exist
  - ✅ Checks inventory availability
  - ✅ Deducts inventory for each item
  - ✅ Records stock movements
  - ✅ Creates order with total_amount and balance_due
  - ✅ Uses database transaction (all-or-nothing)
- **Response:** 201 Created or 500 (insufficient stock)

### Get Order Details
- **Endpoint:** `GET /api/orders/{id}`
- **Description:** View order with items, payments, and delivery
- **Response:** 200 OK

### Update Order
- **Endpoint:** `PUT/PATCH /api/orders/{id}`
- **Description:** Update order date, notes, or status
- **Request Body:**
```json
{
  "order_date": "2024-01-21",
  "status": "confirmed",
  "notes": "Updated delivery notes"
}
```
- **Status Options:** pending, confirmed, shipped, delivered, cancelled
- **Response:** 200 OK

### Cancel Order (Restores Inventory)
- **Endpoint:** `DELETE /api/orders/{id}`
- **Description:** Cancel order and restore inventory
- **Conditions:** Cannot cancel delivered or already-cancelled orders
- **Key Behavior:**
  - ✅ Restores inventory for all items
  - ✅ Records intake stock movements
  - ✅ Sets order status to cancelled
- **Response:** 200 OK or 422 (if order delivered)

---

## PAYMENTS API (Core Business Logic)

### List All Payments
- **Endpoint:** `GET /api/payments`
- **Description:** View all payment records
- **Response:** 200 OK

### Record Payment (CRITICAL BUSINESS LOGIC)
- **Endpoint:** `POST /api/payments`
- **Description:** Record payment for an order (supports installments)
- **Request Body:**
```json
{
  "order_id": 5,
  "amount": 2500.00,
  "payment_method": "bank_transfer",
  "reference": "TXN-12345",
  "payment_date": "2024-01-20"
}
```
- **Payment Methods:** cash, check, bank_transfer, credit
- **Key Behavior:**
  - ✅ Validates order exists
  - ✅ Validates payment doesn't exceed balance
  - ✅ Deducts from order.balance_due
  - ✅ Auto-marks order as confirmed when fully paid
  - ✅ Supports partial/installment payments
- **Response:** 201 Created or 422 (amount > balance)

### Get Payment Details
- **Endpoint:** `GET /api/payments/{id}`
- **Description:** View specific payment record
- **Response:** 200 OK

### Delete Payment (Restores Balance)
- **Endpoint:** `DELETE /api/payments/{id}`
- **Description:** Remove payment and restore order balance
- **Key Behavior:**
  - ✅ Adds amount back to order.balance_due
  - ✅ Resets order status to pending
- **Response:** 200 OK

---

## PURCHASE ORDERS API (Supplier Restocking)

### List All Purchase Orders
- **Endpoint:** `GET /api/purchase-orders`
- **Description:** View all supplier restocking orders
- **Response:** 200 OK

### Create Purchase Order (CRITICAL BUSINESS LOGIC)
- **Endpoint:** `POST /api/purchase-orders`
- **Description:** Create restocking order from supplier
- **Request Body:**
```json
{
  "supplier_id": 1,
  "order_date": "2024-01-20",
  "expected_delivery_date": "2024-01-27",
  "notes": "Rush order - deliver by weekend",
  "items": [
    {
      "product_id": 1,
      "quantity": 200,
      "unit_cost": 8.50
    },
    {
      "product_id": 2,
      "quantity": 150,
      "unit_cost": 12.00
    }
  ]
}
```
- **Key Behavior:**
  - ✅ Validates supplier exists
  - ✅ Validates all products exist
  - ✅ Calculates total_amount
  - ✅ Sets status to 'pending'
- **Response:** 201 Created

### Get Purchase Order Details
- **Endpoint:** `GET /api/purchase-orders/{id}`
- **Description:** View PO with items and supplier
- **Response:** 200 OK

### Update Purchase Order Status (Add Stock When Received)
- **Endpoint:** `PUT/PATCH /api/purchase-orders/{id}`
- **Description:** Update PO status (especially important: mark as received)
- **Request Body:**
```json
{
  "status": "received",
  "expected_delivery_date": "2024-01-27"
}
```
- **Status Options:** pending, confirmed, received, cancelled
- **Key Behavior When Received:**
  - ✅ Adds all items to inventory
  - ✅ Updates last_restock_date
  - ✅ Records stock movement (stock_in) for each item
- **Response:** 200 OK

### Cancel Purchase Order
- **Endpoint:** `DELETE /api/purchase-orders/{id}`
- **Conditions:** Cannot cancel if already received
- **Response:** 200 OK or 422 (if received)

---

## DELIVERIES API

### List All Deliveries
- **Endpoint:** `GET /api/deliveries`
- **Description:** View all order deliveries
- **Response:** 200 OK

### Create Delivery
- **Endpoint:** `POST /api/deliveries`
- **Description:** Create delivery record for an order
- **Request Body:**
```json
{
  "order_id": 5,
  "assigned_employee_id": 3,
  "delivery_date": "2024-01-22",
  "notes": "Call before delivery"
}
```
- **Required Fields:** order_id, assigned_employee_id, delivery_date
- **Key Behavior:**
  - ✅ Validates order hasn't already been delivered
  - ✅ Updates order status to 'shipped'
- **Response:** 201 Created

### Get Delivery Details
- **Endpoint:** `GET /api/deliveries/{id}`
- **Description:** View delivery with customer and employee
- **Response:** 200 OK

### Update Delivery Status
- **Endpoint:** `PUT/PATCH /api/deliveries/{id}`
- **Description:** Update delivery progress
- **Request Body:**
```json
{
  "status": "delivered",
  "actual_delivery_date": "2024-01-22",
  "notes": "Delivered successfully"
}
```
- **Status Options:** pending, in_transit, delivered, failed
- **Key Behavior:**
  - ✅ When "delivered": marks order as delivered
  - ✅ When "failed": reverts order to pending
- **Response:** 200 OK

### Cancel Delivery
- **Endpoint:** `DELETE /api/deliveries/{id}`
- **Conditions:** Cannot cancel completed deliveries
- **Response:** 200 OK or 422

---

## SUPPLIERS API

### List All Suppliers
- **Endpoint:** `GET /api/suppliers`
- **Description:** View all vendors/suppliers
- **Response:** 200 OK

### Create Supplier
- **Endpoint:** `POST /api/suppliers`
- **Description:** Register new supplier
- **Request Body:**
```json
{
  "name": "Ocean Fresh Imports",
  "contact_person": "Maria Santos",
  "email": "maria@oceanfresh.com",
  "phone": "+1-555-0200",
  "address": "456 Harbor Way",
  "city": "Portland"
}
```
- **Response:** 201 Created

### Get Supplier Details
- **Endpoint:** `GET /api/suppliers/{id}`
- **Description:** View supplier with products and order history
- **Response Includes:**
  - Products supplied
  - Pending orders count
  - Total value supplied
- **Response:** 200 OK

### Update Supplier
- **Endpoint:** `PUT/PATCH /api/suppliers/{id}`
- **Response:** 200 OK

### Delete Supplier
- **Endpoint:** `DELETE /api/suppliers/{id}`
- **Conditions:** Cannot delete if has active purchase orders
- **Response:** 200 OK or 422

---

## HEALTH CHECK

### API Health Status
- **Endpoint:** `GET /api/health`
- **Description:** Simple endpoint to verify API is running
- **Response:** 200 OK
```json
{
  "status": "ok",
  "message": "API is running",
  "timestamp": "2024-01-20T15:30:00Z"
}
```

---

## Error Handling

### Common HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success (read/update operations) |
| 201 | Created (successful POST) |
| 404 | Not Found |
| 405 | Method Not Allowed |
| 422 | Validation Failed or Business Logic Error |
| 500 | Server Error |

### Validation Errors
When validation fails, response includes error details:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required"],
    "credit_limit": ["The credit limit must be numeric"]
  }
}
```

---

## Testing the API

### Using cURL
```bash
# Create a product
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Salmon",
    "category": "Fish",
    "unit": "kg"
  }'

# Get all products
curl http://localhost:8000/api/products

# Create an order
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "order_date": "2024-01-20",
    "items": [
      {"product_id": 1, "quantity": 50, "unit_price": 15.50}
    ]
  }'
```

### Using Postman
1. Import the API documentation
2. Set base URL to `http://localhost:8000/api`
3. Test each endpoint with provided examples
4. Verify inventory deduction by checking inventory before/after orders

---

## Business Logic Workflows

### Complete Order Workflow
1. **Create Order** (POST /api/orders)
   - Inventory is automatically deducted
   - Stock movements are recorded
   - balance_due = total_amount

2. **Record Payment** (POST /api/payments)
   - Payment is recorded
   - balance_due is reduced
   - When balance_due <= 0, order status → "confirmed"

3. **Create Delivery** (POST /api/deliveries)
   - Assigns order to employee
   - Order status → "shipped"

4. **Mark Delivered** (PATCH /api/deliveries/{id})
   - Delivery status → "delivered"
   - Order status → "delivered"

### Inventory Management Workflow
1. **Products Created** → Auto-create inventory with 0 quantity
2. **Purchase Order Received** → Add items to inventory
3. **Customer Order Created** → Deduct items from inventory
4. **Order Cancelled** → Restore items to inventory
5. **Manual Adjustment** → Adjust quantity + record reason

---

## Required Fields Summary

### Products
- `name` (string, unique)
- `category` (string)
- `unit` (string: kg, pcs, dozen, etc.)

### Customers
- `name` (string)
- `email` (string, unique)
- `phone` (string)
- `address` (string)
- `type` (retail or wholesale)
- `credit_limit` (numeric)

### Orders
- `customer_id` (exists in customers)
- `order_date` (date)
- `items[]` array with:
  - `product_id` (exists in products)
  - `quantity` (numeric > 0)
  - `unit_price` (numeric)

### Payments
- `order_id` (exists in orders)
- `amount` (numeric, <= balance_due)
- `payment_method` (cash, check, bank_transfer, credit)

### Purchase Orders
- `supplier_id` (exists in suppliers)
- `order_date` (date)
- `expected_delivery_date` (date, after order_date)
- `items[]` array with:
  - `product_id` (exists in products)
  - `quantity` (numeric > 0)
  - `unit_cost` (numeric)

### Deliveries
- `order_id` (exists in orders)
- `assigned_employee_id` (exists in employees)
- `delivery_date` (date)

### Suppliers
- `name` (string, unique)
- `contact_person` (string)
- `email` (string, unique)
- `phone` (string)
- `address` (string)
- `city` (string)
