<template>
  <div class="create-order-container">
    <div class="header-section">
      <div class="header-left">
        <button @click="$router.push('/orders')" class="btn btn-back">← Back to Orders</button>
        <h1>Create New Order</h1>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <p>Loading form data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="loadFormData" class="btn btn-secondary">Retry</button>
    </div>

    <!-- Form -->
    <div v-else class="card">
      <form @submit.prevent="submitOrder">
        <div class="form-section">
          <h3>Customer Information</h3>
          <div class="form-group">
            <label>Customer *</label>
            <select v-model="form.customer_id" required>
              <option value="">Select a customer...</option>
              <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                {{ customer.name }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>Order Type *</label>
            <div class="radio-group">
              <label><input v-model="form.type" type="radio" value="retail" /> Retail</label>
              <label><input v-model="form.type" type="radio" value="wholesale" /> Wholesale</label>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3>Products</h3>
          <div v-for="(item, idx) in form.items" :key="idx" class="product-item">
            <select v-model="form.items[idx].product_id" required @change="updateProductPrice(idx)">
              <option value="">Select product...</option>
              <option v-for="product in products" :key="product.id" :value="product.id">
                {{ product.name }} (R: ₱{{ product.pricing?.[0]?.retail_price || product.base_price }} | W: ₱{{ product.pricing?.[0]?.wholesale_price || product.base_price }})
              </option>
            </select>
            <input v-model.number="form.items[idx].quantity" type="number" placeholder="Qty (kg)" min="0.01" step="0.01" required />
            <input v-model.number="form.items[idx].unit_price" type="number" placeholder="Unit Price" min="0" step="0.01" required />
            <p class="product-subtotal">₱{{ (form.items[idx].quantity * form.items[idx].unit_price).toFixed(2) }}</p>
            <button type="button" @click="removeItem(idx)" class="btn-delete">✕</button>
          </div>
          <button type="button" @click="addProduct" class="btn btn-secondary">+ Add Product</button>
        </div>

        <div class="form-section">
          <h3>Order Summary</h3>
          <div class="summary">
            <div class="summary-row">
              <span>Subtotal:</span>
              <span>₱{{ calculateSubtotal().toFixed(2) }}</span>
            </div>
            <div class="summary-row" v-if="form.type === 'wholesale'">
              <span>Wholesale Discount (10%):</span>
              <span>-₱{{ (calculateSubtotal() * 0.1).toFixed(2) }}</span>
            </div>
            <div class="summary-row total">
              <span>Total:</span>
              <span>₱{{ calculateTotal().toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Notes</label>
          <textarea v-model="form.notes" placeholder="Additional notes..."></textarea>
        </div>

        <div class="form-actions">
          <router-link to="/orders" class="btn btn-secondary">Cancel</router-link>
          <button type="submit" :disabled="submitting" class="btn btn-primary">
            {{ submitting ? 'Creating Order...' : 'Create Order' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api';

const router = useRouter();

const form = ref({
  customer_id: '',
  type: 'retail',
  items: [{ product_id: '', quantity: 1, unit_price: 0 }],
  notes: ''
});

const customers = ref([]);
const products = ref([]);
const loading = ref(false);
const error = ref('');
const submitting = ref(false);

const loadFormData = async () => {
  loading.value = true;
  error.value = '';
  try {
    const [customersResponse, productsResponse] = await Promise.all([
      api.get('/customers'),
      api.get('/products')
    ]);

    if (customersResponse.data.success) {
      customers.value = customersResponse.data.data;
    }
    if (productsResponse.data.success) {
      products.value = productsResponse.data.data;
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load form data';
  } finally {
    loading.value = false;
  }
};

const addProduct = () => {
  form.value.items.push({ product_id: '', quantity: 1, unit_price: 0 });
};

const removeItem = (idx) => {
  form.value.items.splice(idx, 1);
};

const updateProductPrice = (idx) => {
  const productId = form.value.items[idx].product_id;
  const product = products.value.find(p => p.id == productId);
  if (product) {
    // Use pricing data based on order type
    const pricing = product.pricing?.[0]; // Get active pricing
    if (pricing) {
      if (form.value.type === 'wholesale') {
        form.value.items[idx].unit_price = pricing.wholesale_price || product.base_price;
      } else {
        form.value.items[idx].unit_price = pricing.retail_price || product.base_price;
      }
    } else {
      form.value.items[idx].unit_price = product.base_price;
    }
  }
};

const updateAllPrices = () => {
  form.value.items.forEach((item, idx) => {
    if (item.product_id) {
      updateProductPrice(idx);
    }
  });
};

watch(() => form.value.type, () => {
  updateAllPrices();
});

const calculateSubtotal = () => {
  return form.value.items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
};

const calculateTotal = () => {
  const subtotal = calculateSubtotal();
  if (form.value.type === 'wholesale') {
    return subtotal * 0.9; // 10% discount
  }
  return subtotal;
};

const submitOrder = async () => {
  submitting.value = true;
  try {
    const orderData = {
      ...form.value,
      total_amount: calculateTotal(),
      items: form.value.items.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity,
        unit_price: item.unit_price,
        total: item.quantity * item.unit_price
      }))
    };

    const response = await api.post('/orders', orderData);
    if (response.data.success) {
      router.push('/orders');
    } else {
      alert(response.data.message || 'Failed to create order');
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to create order');
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  loadFormData();
});
</script>

<style scoped>
.create-order-container {
  max-width: 800px;
  margin: 0 auto;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 30px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.card h1 {
  margin: 0 0 30px 0;
  color: #0a1d37;
}

.form-section {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e0e0e0;
}

.form-section h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
  font-size: 16px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  font-family: inherit;
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #e57c2a;
  background-color: #fef9f5;
}

.radio-group {
  display: flex;
  gap: 20px;
}

.radio-group label {
  display: flex;
  align-items: center;
  margin: 0;
}

.radio-group input[type="radio"] {
  width: auto;
  margin-right: 8px;
}

.product-item {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr auto;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
  padding: 10px;
  background-color: #f9f9f9;
  border-radius: 6px;
}

.product-item select,
.product-item input {
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 13px;
}

.product-subtotal {
  margin: 0;
  font-weight: 600;
  color: #e57c2a;
  text-align: right;
}

.btn-delete {
  background: #fee;
  color: #c33;
  border: none;
  border-radius: 4px;
  width: 30px;
  height: 30px;
  cursor: pointer;
  font-weight: bold;
  transition: all 0.3s;
}

.btn-delete:hover {
  background: #fdd;
  color: #a22;
}

.summary {
  background-color: #f9f9f9;
  padding: 15px;
  border-radius: 6px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 10px;
  font-size: 14px;
}

.summary-row.total {
  border-top: 2px solid #ddd;
  padding-top: 10px;
  margin-top: 10px;
  font-weight: 700;
  font-size: 16px;
  color: #e57c2a;
}

.form-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 30px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover {
  background-color: #d46a1a;
}

.btn-secondary {
  background-color: #f0f0f0;
  color: #333;
  border: 1px solid #ddd;
}

.btn-secondary:hover {
  background-color: #e0e0e0;
}
</style>
