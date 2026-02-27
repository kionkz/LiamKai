<template>
  <div class="create-po-container">
    <div class="form-wrapper">
      <div class="form-header">
        <button @click="goBack" class="btn-back">
          ← Back to Purchasing
        </button>
        <div class="header-content">
          <h1>Create Purchase Order</h1>
          <p class="subtitle">Fill in the details below to create a new PO</p>
        </div>
      </div>

      <form @submit.prevent="submitPO" class="po-form">
        <!-- Supplier Section -->
        <div class="form-section">
          <div class="section-icon">🏢</div>
          <div class="section-content">
            <h2>Supplier Information</h2>
            <div class="form-group">
              <label>Select Supplier *</label>
              <select v-model="formData.supplier_id" required class="form-select">
                <option value="">-- Choose a supplier --</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }} ({{ supplier.contact_person }})
                </option>
              </select>
              <p class="field-help">Select the supplier you're ordering from</p>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Expected Pickup Date *</label>
                <input v-model="formData.expected_delivery_date" type="date" required class="form-input" />
                <p class="field-help">When you'll pick up the order from the supplier</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Products Section -->
        <div class="form-section">
          <div class="section-icon">📦</div>
          <div class="section-content">
            <div class="section-header">
              <div>
                <h2>Products</h2>
                <p class="section-subtitle">Add the products you're ordering</p>
              </div>
              <button type="button" @click="addProductLine" class="btn-add">
                <span>+</span> Add Product
              </button>
            </div>

            <div v-if="formData.items.length === 0" class="empty-items">
              <div class="empty-icon">📋</div>
              <p>No products added yet</p>
              <p class="empty-hint">Click "Add Product" to start building your order</p>
            </div>

            <table v-else class="line-items-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Quantity</th>
                  <th>Unit Cost</th>
                  <th>Subtotal</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in formData.items" :key="idx" class="item-row">
                  <td>
                    <select v-model="item.product_id" required class="form-select">
                      <option value="">Select Product</option>
                      <option v-for="product in products" :key="product.id" :value="product.id">
                        {{ product.name }}
                      </option>
                    </select>
                  </td>
                  <td>
                    <input v-model.number="item.quantity" type="number" min="1" required class="form-input number" />
                  </td>
                  <td>
                    <input v-model.number="item.unit_cost" type="number" min="0" step="0.01" placeholder="₱0.00" class="form-input number" />
                  </td>
                  <td class="subtotal-cell">
                    <div class="subtotal">₱{{ (item.quantity * item.unit_cost).toFixed(2) }}</div>
                  </td>
                  <td>
                    <button type="button" @click="removeProduct(idx)" class="btn-remove">
                      <span>−</span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Order Details Section -->
        <div class="form-section">
          <div class="section-icon">📝</div>
          <div class="section-content">
            <h2>Order Details</h2>
            <div class="form-group">
              <label>Notes / Special Instructions</label>
              <textarea 
                v-model="formData.notes" 
                rows="3" 
                placeholder="Any special requests, delivery instructions, or notes..."
                class="form-textarea"
              ></textarea>
              <p class="field-help">Optional: Add any special instructions for this order</p>
            </div>
          </div>
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
          <div class="summary-card">
            <div class="summary-row">
              <span class="summary-label">Subtotal:</span>
              <span class="summary-value">₱{{ subtotal.toFixed(2) }}</span>
            </div>
            <div class="summary-row border-top">
              <span class="summary-label-total">Total PO Amount:</span>
              <span class="summary-value-total">₱{{ subtotal.toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions-sticky">
          <button type="button" @click="goBack" class="btn btn-secondary">Cancel</button>
          <button type="submit" class="btn btn-primary btn-large" :disabled="loading">
            {{ loading ? 'Creating...' : 'Create Purchase Order' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Success/Error Messages -->
    <div v-if="successMessage" class="alert alert-success">
      <div>{{ successMessage }}</div>
      <button @click="successMessage = ''" class="close-alert">×</button>
    </div>
    <div v-if="errorMessage" class="alert alert-error">
      <div>{{ errorMessage }}</div>
      <button @click="errorMessage = ''" class="close-alert">×</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();

// State
const loading = ref(false);
const suppliers = ref([]);
const products = ref([]);
const successMessage = ref('');
const errorMessage = ref('');

const formData = ref({
  supplier_id: '',
  expected_delivery_date: '',
  notes: '',
  items: [],
  order_date: new Date().toISOString().split('T')[0],
});

// Computed Properties
const subtotal = computed(() => {
  return formData.value.items.reduce((sum, item) => {
    return sum + (parseFloat(item.quantity) * parseFloat(item.unit_cost) || 0);
  }, 0);
});

// Methods
const goBack = () => {
  router.push('/purchasing');
};

const fetchSuppliers = async () => {
  try {
    const response = await axios.get('/api/suppliers');
    if (response.data.success) {
      suppliers.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching suppliers:', error);
    errorMessage.value = 'Failed to load suppliers';
  }
};

const fetchProducts = async () => {
  try {
    const response = await axios.get('/api/products');
    if (response.data.success) {
      products.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching products:', error);
    errorMessage.value = 'Failed to load products';
  }
};

const addProductLine = () => {
  formData.value.items.push({ 
    product_id: '', 
    quantity: 1, 
    unit_cost: 0 
  });
};

const removeProduct = (idx) => {
  formData.value.items.splice(idx, 1);
};

const submitPO = async () => {
  // Validation
  if (!formData.value.supplier_id) {
    errorMessage.value = 'Please select a supplier';
    return;
  }

  if (!formData.value.expected_delivery_date) {
    errorMessage.value = 'Please select an expected delivery date';
    return;
  }

  if (formData.value.items.length === 0) {
    errorMessage.value = 'Please add at least one product';
    return;
  }

  // Check all items have product and cost
  for (const item of formData.value.items) {
    if (!item.product_id || item.quantity < 1 || item.unit_cost <= 0) {
      errorMessage.value = 'Please fill all product fields';
      return;
    }
  }

  try {
    loading.value = true;
    const payload = {
      supplier_id: parseInt(formData.value.supplier_id),
      order_date: formData.value.order_date,
      expected_delivery_date: formData.value.expected_delivery_date,
      notes: formData.value.notes || null,
      items: formData.value.items.map(item => ({
        product_id: parseInt(item.product_id),
        quantity: parseInt(item.quantity),
        unit_cost: parseFloat(item.unit_cost)
      }))
    };

    const response = await axios.post('/api/purchase-orders', payload);
    
    if (response.data.success) {
      successMessage.value = 'Purchase order created successfully!';
      setTimeout(() => {
        router.push('/purchasing');
      }, 1500);
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to create purchase order';
    console.error('Error creating PO:', error);
  } finally {
    loading.value = false;
  }
};

// Lifecycle
onMounted(() => {
  fetchSuppliers();
  fetchProducts();
});
</script>

<style scoped>
.create-po-container {
  animation: fadeIn 0.3s ease-in;
  max-width: 900px;
  margin: 0 auto;
}

.form-wrapper {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

/* Form Header */
.form-header {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 30px;
  background: linear-gradient(135deg, #0a1d37 0%, #1a3d5c 100%);
  color: white;
}

.header-content {
  flex: 1;
}

.form-header h1 {
  margin: 0;
  font-size: 28px;
  font-weight: 700;
}

.subtitle {
  margin: 5px 0 0 0;
  font-size: 14px;
  opacity: 0.9;
  font-weight: 400;
}

.btn-back {
  padding: 8px 16px;
  background-color: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
  color: white;
  text-decoration: none;
  white-space: nowrap;
}

.btn-back:hover {
  background-color: rgba(255, 255, 255, 0.3);
  border-color: rgba(255, 255, 255, 0.5);
}

/* Form Content */
.po-form {
  padding: 30px;
}

.form-section {
  margin-bottom: 35px;
  display: flex;
  gap: 20px;
}

.form-section:last-of-type {
  margin-bottom: 20px;
}

.section-icon {
  font-size: 32px;
  min-width: 50px;
  text-align: center;
  line-height: 1;
  opacity: 0.8;
}

.section-content {
  flex: 1;
}

.section-content h2 {
  margin-top: 0;
  margin-bottom: 5px;
  color: #0a1d37;
  font-size: 18px;
  font-weight: 700;
}

.section-subtitle {
  margin: 0;
  color: #666;
  font-size: 13px;
  font-weight: 400;
  margin-bottom: 15px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  gap: 20px;
}

.section-header > div {
  flex: 1;
}

/* Form Groups */
.form-group {
  margin-bottom: 20px;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #0a1d37;
  font-size: 14px;
}

.form-select,
.form-input,
.form-textarea {
  width: 100%;
  padding: 11px 12px;
  border: 1.5px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s;
  background-color: #fafafa;
}

.form-select:focus,
.form-input:focus,
.form-textarea:focus {
  outline: none;
  background-color: white;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.form-input.number {
  text-align: right;
}

.field-help {
  margin: 6px 0 0 0;
  font-size: 12px;
  color: #999;
  font-style: italic;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

.form-textarea {
  resize: vertical;
  min-height: 100px;
  font-family: inherit;
}

/* Empty States */
.empty-items {
  padding: 50px 30px;
  text-align: center;
  background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
  border-radius: 8px;
  border: 2px dashed #ddd;
  margin-bottom: 20px;
  color: #666;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 15px;
  opacity: 0.6;
}

.empty-items p {
  margin: 0;
}

.empty-items p:first-of-type {
  font-weight: 600;
  font-size: 16px;
  color: #0a1d37;
}

.empty-hint {
  font-size: 13px;
  color: #999;
  margin-top: 8px;
}

/* Table */
.line-items-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 0;
  background: white;
}

.line-items-table thead {
  background-color: #f5f5f5;
}

.line-items-table th {
  padding: 14px 12px;
  text-align: left;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  color: #666;
  border-bottom: 2px solid #e0e0e0;
}

.line-items-table td {
  padding: 14px 12px;
  border-bottom: 1px solid #e0e0e0;
  vertical-align: middle;
}

.item-row:hover {
  background-color: #fafafa;
}

.subtotal-cell {
  text-align: right;
}

.subtotal {
  color: #0a1d37;
  font-weight: 700;
  font-size: 15px;
}

/* Buttons */
.btn-add {
  padding: 10px 18px;
  background-color: #e57c2a;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
}

.btn-add span {
  font-size: 18px;
  font-weight: 400;
  line-height: 1;
}

.btn-add:hover {
  background-color: #d46a1a;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.3);
}

.btn-remove {
  padding: 6px 10px;
  background-color: #ffebee;
  color: #d32f2f;
  border: 1px solid #ffcdd2;
  border-radius: 4px;
  cursor: pointer;
  font-size: 18px;
  transition: all 0.3s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  line-height: 1;
}

.btn-remove:hover {
  background-color: #ffcdd2;
  border-color: #ff9800;
  color: #c62828;
}

/* Summary */
.summary-section {
  margin: 30px 0;
  padding: 20px 0;
  border-top: 2px solid #e0e0e0;
  border-bottom: 2px solid #e0e0e0;
}

.summary-card {
  background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
  padding: 20px 25px;
  border-radius: 8px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 12px 0;
  font-size: 14px;
}

.summary-row.border-top {
  border-top: 2px solid #ddd;
  padding-top: 15px;
  margin-top: 12px;
}

.summary-label {
  font-weight: 500;
  color: #666;
}

.summary-value {
  font-weight: 600;
  color: #0a1d37;
}

.summary-label-total {
  font-weight: 700;
  color: #0a1d37;
  font-size: 15px;
}

.summary-value-total {
  font-weight: 700;
  color: #e57c2a;
  font-size: 18px;
}

/* Form Actions */
.form-actions-sticky {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 20px;
  border-top: 1px solid #e0e0e0;
}

.btn {
  padding: 11px 24px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
  font-size: 14px;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #d46a1a;
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(229, 124, 42, 0.3);
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.btn-large {
  padding: 12px 32px;
  font-size: 15px;
}

.btn-secondary {
  background-color: #f0f0f0;
  color: #0a1d37;
  border: 1.5px solid #ddd;
}

.btn-secondary:hover {
  background-color: #e0e0e0;
  border-color: #999;
}

/* Alerts */
.alert {
  padding: 15px 20px;
  border-radius: 6px;
  margin-top: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  animation: slideIn 0.3s ease-out;
  fixed: true;
  bottom: 20px;
  right: 20px;
  max-width: 450px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.alert-success {
  background-color: #e8f5e9;
  color: #388e3c;
  border: 1px solid #c8e6c9;
}

.alert-error {
  background-color: #ffebee;
  color: #d32f2f;
  border: 1px solid #ffcdd2;
}

.close-alert {
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
  color: inherit;
  margin-left: 10px;
  opacity: 0.7;
}

.close-alert:hover {
  opacity: 1;
}

/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* Responsive */
@media (max-width: 768px) {
  .form-wrapper {
    border-radius: 0;
  }

  .form-header {
    flex-direction: column;
    align-items: flex-start;
    padding: 20px;
  }

  .form-section {
    flex-direction: column;
    gap: 10px;
  }

  .section-icon {
    font-size: 28px;
    min-width: 40px;
  }

  .po-form {
    padding: 20px;
  }

  .section-header {
    flex-direction: column;
  }

  .form-actions-sticky {
    flex-direction: column;
  }

  .btn {
    width: 100%;
  }

  .alert {
    position: static;
    max-width: 100%;
    flex-direction: column;
    align-items: flex-start;
  }

  .close-alert {
    align-self: flex-end;
  }
}
</style>
