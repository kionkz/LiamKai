# API Testing Guide

## Option 1: Using Postman (Recommended - Visual & Easy)

### Step 1: Download & Install Postman
- Download from: https://www.postman.com/downloads/
- Install and open Postman

### Step 2: Import the Collection
1. Open Postman
2. Click **"File" → "Import"**
3. Select the file: `Fish_Wholesale_API.postman_collection.json`
4. Collection will appear in left sidebar

### Step 3: Set Base URL
1. In the collection, find the variable at the top
2. Confirm `base_url` is set to: `http://localhost:8000/api`
3. All requests will use this base URL automatically

### Step 4: Start Testing
1. Make sure Laravel server is running:
   ```bash
   php artisan serve
   ```
2. In Postman, expand "Health Check" folder
3. Click "Health Check" request
4. Click blue **"Send"** button
5. You should see response:
   ```json
   {
     "status": "ok",
     "message": "API is running",
     "timestamp": "2026-02-17T..."
   }
   ```

### Step 5: Test Complete Workflow

Follow this order to see business logic in action:

#### 1️⃣ Create a Product
- Go to **Products → Create Product**
- Click **Send**
- Response shows created product with ID (remember it!)

#### 2️⃣ Check Product Inventory
- Go to **Inventory → Get Product Inventory**
- Change the ID to match your product
- You'll see: `"quantity_on_hand": 0` (automatically initialized)

#### 3️⃣ Add Stock to Inventory
- Go to **Inventory → Adjust Inventory (Add Stock)**
- Change product ID
- Click **Send**
- Stock is now 100 kg

#### 4️⃣ Create a Customer
- Go to **Customers → Create Customer**
- Click **Send**
- Remember the customer ID

#### 5️⃣ Create an Order
- Go to **Orders → Create Order (AUTO-DEDUCTS INVENTORY)**
- Update the IDs in the request:
  - `"customer_id": 1` (use your customer's ID)
  - `"product_id": 1` (use your product's ID)
- Click **Send**
- ✅ Order created AND inventory automatically deducted!

#### 6️⃣ Verify Inventory Changed
- Go back to **Inventory → Get Product Inventory**
- You'll now see `"quantity_on_hand": 50` (100 - 50 sold)
- Check "stockMovements" array - it shows the deduction!

#### 7️⃣ Record a Payment
- Go to **Payments → Record Payment**
- Update `"order_id": 1` to match your order
- Click **Send**
- ✅ Payment recorded, order balance reduced!

#### 8️⃣ Create a Delivery
- Go to **Deliveries → Create Delivery**
- Update IDs
- Click **Send**

#### 9️⃣ Mark Delivery as Delivered
- Go to **Deliveries → Mark Delivery as Delivered**
- Click **Send**
- ✅ Order status automatically updated to "delivered"!

#### 🔟 Create a Purchase Order (Restocking)
- Go to **Purchase Orders → Create Purchase Order**
- Click **Send**

#### 1️⃣1️⃣ Receive Purchase Order (AUTO-ADDS TO INVENTORY)
- Go to **Purchase Orders → Mark as Received**
- Click **Send**
- ✅ Inventory automatically increased!

---

## Option 2: Using cURL (Terminal - Fast Testing)

### Start Laravel Server First
```bash
cd /Users/kionaluna/LiamKai-app
php artisan serve
```

### Test Health Check
```bash
curl http://localhost:8000/api/health
```

### Create a Product
```bash
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Salmon",
    "category": "Fish",
    "description": "Fresh Norwegian salmon",
    "unit": "kg"
  }'
```

### List All Products
```bash
curl http://localhost:8000/api/products
```

### Create a Customer
```bash
curl -X POST http://localhost:8000/api/customers \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mike Fresh Supplies",
    "email": "mike@freshsupplies.com",
    "phone": "+1-555-0123",
    "address": "789 Commercial Ave",
    "type": "wholesale",
    "credit_limit": 75000
  }'
```

### Add Stock to Inventory (First Add Some Products!)
```bash
# Get product ID first - replace {product_id}
curl -X PUT http://localhost:8000/api/inventory/1 \
  -H "Content-Type: application/json" \
  -d '{
    "adjustment_quantity": 150,
    "adjustment_reason": "Initial stock purchase"
  }'
```

### Create an Order (AUTO-DEDUCTS INVENTORY)
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "order_date": "2026-02-17",
    "notes": "Rush delivery",
    "items": [
      {
        "product_id": 1,
        "quantity": 50,
        "unit_price": 18.50
      }
    ]
  }'
```

### Record a Payment
```bash
curl -X POST http://localhost:8000/api/payments \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": 1,
    "amount": 925.00,
    "payment_method": "bank_transfer",
    "reference": "CHK-001"
  }'
```

### Get Order Details (See All Changes)
```bash
curl http://localhost:8000/api/orders/1
```

### Get Product Inventory (Verify Deduction)
```bash
curl http://localhost:8000/api/inventory/1
```

---

## Option 3: Using PHP Artisan Tinker (Advanced Interactive Testing)

```bash
php artisan tinker
```

Then in the Tinker shell:

```php
# Create a product
$product = App\Models\Product::create([
    'name' => 'Tuna',
    'category' => 'Fish',
    'unit' => 'kg'
]);

# View inventory (auto-created)
$product->inventory

# Create a customer
$customer = App\Models\Customer::create([
    'name' => 'Test Market',
    'email' => 'test@market.com',
    'phone' => '+1-555-9999',
    'address' => '123 Test St',
    'type' => 'retail',
    'credit_limit' => 10000
]);

# Add inventory
$product->inventory->update(['quantity_on_hand' => 500]);

# Create an order
$order = App\Models\Order::create([
    'customer_id' => $customer->id,
    'order_date' => now(),
    'total_amount' => 1000,
    'balance_due' => 1000,
    'status' => 'pending'
]);

# View the order
$order->load('customer', 'orderItems')
```

---

## Key Things to Test

### ✅ Inventory Management
1. Create a product → verify inventory = 0
2. Adjust inventory → add stock
3. Create order → verify stock deducted
4. Check stock movements → verify record created
5. Cancel order → verify stock restored

### ✅ Order Workflow
1. Create order → auto-deducts inventory
2. Record payment → balance_due decreases
3. Full payment → order status auto-updates to "confirmed"
4. Create delivery → order status → "shipped"
5. Mark delivered → order status → "delivered"

### ✅ Payment System
1. Record payment → balance_due updated
2. Payment > balance → should error out
3. Multiple payments → each reduces balance
4. Delete payment → balance restored

### ✅ Purchase Orders
1. Create PO → status = pending
2. Mark received → inventory auto-added
3. Stock movements created with reference

### ✅ Error Handling
1. Create order with non-existent customer → 404
2. Create order with insufficient stock → 500 with message
3. Create order with invalid items → validation error
4. Delete customer with orders → 422 error

---

## Troubleshooting

### "Connection refused" or "Could not resolve host"
- Make sure Laravel server is running: `php artisan serve`
- Check URL is exactly: `http://localhost:8000/api`

### "404 Not Found"
- Verify controllers exist in `app/Http/Controllers/Api/`
- Verify routes are defined in `routes/api.php`
- Restart Laravel: Stop (`Ctrl+C`) and run `php artisan serve` again

### "Validation failed" error
- Check all required fields are provided
- Email must be unique (if creating second customer, use different email)
- Product/customer IDs must exist

### "Insufficient stock" when creating order
- First add inventory: Use "Adjust Inventory (Add Stock)" endpoint
- Or check current stock with "Get Product Inventory"

### Database errors
- Migrations might not have run: `php artisan migrate`
- Check MySQL is running on your system

---

## Performance Testing (Optional)

### Load Test with ApacheBench
```bash
# Test products listing (100 requests, 10 concurrent)
ab -n 100 -c 10 http://localhost:8000/api/products

# Test order creation (10 requests, 2 concurrent)
ab -n 10 -c 2 -p order.json -T application/json http://localhost:8000/api/orders
```

### Memory Usage
```bash
# While server is running, check memory in another terminal
ps aux | grep "php artisan serve"
```

---

## Next Steps After Testing

Once you've verified everything works:

1. **Write Unit Tests** (optional but recommended)
   ```bash
   php artisan make:test OrderControllerTest
   ```

2. **Build Vue Frontend** to consume these APIs
   - Create components for products, customers, orders, etc.
   - Make axios/fetch calls to these endpoints
   - Display responses in the UI

3. **Add Authentication** (optional)
   - Laravel Sanctum for API tokens
   - Guard middleware on protected routes
   - User login before API access

---

## Useful SQL Queries for Testing

```sql
-- View all products
SELECT * FROM products;

-- View all customers
SELECT * FROM customers;

-- View all orders with totals
SELECT o.id, c.name, o.total_amount, o.balance_due, o.status 
FROM orders o 
JOIN customers c ON o.customer_id = c.id;

-- View inventory levels
SELECT p.name, i.quantity_on_hand, i.reorder_point 
FROM inventory i 
JOIN products p ON i.product_id = p.id;

-- View stock movements (audit trail)
SELECT * FROM stock_movements ORDER BY created_at DESC;

-- View all payments
SELECT * FROM payments ORDER BY created_at DESC;

-- Check low stock items
SELECT p.name, i.quantity_on_hand, i.reorder_point 
FROM inventory i 
JOIN products p ON i.product_id = p.id 
WHERE i.quantity_on_hand <= i.reorder_point;
```

Access MySQL from terminal:
```bash
# Login to MySQL
mysql -u root -p

# Use the database
USE liam_kai_fish;

# Run queries above
```

---

## Summary

✅ **Postman** = Best for visual testing and learning
✅ **cURL** = Best for scripting and CI/CD
✅ **Tinker** = Best for interactive debugging
✅ **Direct SQL** = Best for verifying data integrity

Start with **Option 1 (Postman)** - it's the easiest to learn the API!
