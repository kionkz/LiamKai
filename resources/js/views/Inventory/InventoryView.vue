<template>
  <div class="inventory-container">
    <div class="header-section">
      <div class="header-left">
        <h1>Inventory Management</h1>
      </div>
      <div class="header-controls">
        <input v-model="searchQuery" type="text" placeholder="Search products..." class="search-box" />
        <button @click="showAddProductModal = true" class="btn btn-primary">+ New Product</button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <p>Loading inventory...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="fetchProducts" class="btn btn-secondary">Retry</button>
    </div>

    <!-- Inventory Content -->
    <div v-else>
      <div class="filters">
        <button
          v-for="category in categories"
          :key="category"
          @click="selectedCategory = category"
          :class="['filter-btn', { active: selectedCategory === category }]"
        >
          {{ category }}
        </button>
      </div>

      <div class="table-container">
        <div v-if="filteredProducts.length === 0" class="no-data">
          <p>No products in this category.</p>
        </div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>SKU</th>
              <th>Current Stock</th>
              <th>Reorder Level</th>
              <th>Retail Price</th>
              <th>Wholesale Price</th>
              <th>Stock Health</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in filteredProducts" :key="product.id">
              <td>{{ product.name }}</td>
              <td>{{ product.sku }}</td>
              <td>{{ product.quantity }}</td>
              <td>{{ product.reorder_level }}</td>
              <td>₱{{ Number(product.retail_price || 0).toLocaleString() }}</td>
              <td>{{ product.wholesale_price ? '₱' + Number(product.wholesale_price).toLocaleString() : '-' }}</td>
              <td>
                <span v-if="product.quantity <= product.reorder_level" class="status low">Low</span>
                <span v-else-if="product.quantity > product.reorder_level * 2" class="status high">Adequate</span>
                <span v-else class="status normal">Normal</span>
              </td>
              <td class="actions-cell">
                <button @click="openStockUpdateModal(product)" class="btn-small btn-update">Update Stock</button>
                <button @click="viewProductDetails(product.id)" class="btn-small">View Details</button>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="pagination" v-if="pagination.last_page > 1">
          <button class="btn btn-secondary" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">Previous</button>
          <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="btn btn-secondary" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">Next</button>
        </div>
      </div>
    </div>

    <!-- Stock Update Modal -->
    <div v-if="showStockModal" class="modal-overlay" @click.self="closeStockModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Update Stock - {{ selectedProduct?.name }}</h2>
          <button @click="closeStockModal" class="close-btn">×</button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>Current Stock:</label>
            <p class="current-stock">{{ selectedProduct?.quantity }} units</p>
          </div>

          <div class="form-group">
            <label>Adjustment Amount</label>
            <div class="adjustment-controls">
              <button @click="adjustmentAmount--" class="btn-qty">−</button>
              <input v-model.number="adjustmentAmount" type="number" />
              <button @click="adjustmentAmount++" class="btn-qty">+</button>
            </div>
          </div>

          <div class="form-group">
            <label>Reason</label>
            <select v-model="adjustmentReason">
              <option value="restock">Restock/Receiving</option>
              <option value="damage">Damage/Defect Adjustment</option>
              <option value="loss">Loss/Theft Adjustment</option>
              <option value="inventory_count">Inventory Count Adjustment</option>
            </select>
          </div>

          <div class="form-group">
            <label>Notes</label>
            <textarea v-model="adjustmentNotes" rows="3" placeholder="Optional notes..."></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeStockModal" class="btn btn-secondary">Cancel</button>
          <button @click="submitStockUpdate" :disabled="updating" class="btn btn-primary">
            {{ updating ? 'Updating...' : 'Update Stock' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Product Details Modal -->
    <div v-if="showDetailsModal" class="modal-overlay" @click.self="closeDetailsModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Product Details - {{ productDetails?.product?.name || productDetails?.product?.name }}</h2>
          <button @click="closeDetailsModal" class="close-btn">×</button>
        </div>

        <div class="modal-body">
          <div v-if="productDetails">
            <h3>Summary</h3>
            <p><strong>SKU:</strong> {{ productDetails.product.sku }}</p>
            <p><strong>Current Stock:</strong> {{ productDetails.quantity_on_hand ?? productDetails.quantity }}</p>
            <p><strong>Reorder Level:</strong> {{ productDetails.reorder_point ?? productDetails.reorder_level }}</p>
            <p><strong>Retail Price:</strong> ₱{{ productDetails.product.pricing?.[0]?.retail_price ?? productDetails.product.base_price ?? 0 }}</p>
            <p><strong>Wholesale Price:</strong> {{ productDetails.product.pricing?.[0]?.wholesale_price ? '₱' + productDetails.product.pricing[0].wholesale_price : '-' }}</p>

            <h3 style="margin-top:12px">Product Info</h3>
            <p v-if="productDetails.product.description">{{ productDetails.product.description }}</p>
            <p v-else style="color:#666">No additional product details.</p>

            <h3 style="margin-top:12px">Movement History</h3>
            <div v-if="(productDetails.stockMovements || productDetails.stock_movements || []).length === 0">
              <p>No movement history available.</p>
            </div>
            <table v-else class="data-table">
              <thead>
                <tr><th>Date</th><th>Type</th><th>Qty</th><th>Reason</th></tr>
              </thead>
              <tbody>
                <tr v-for="m in (productDetails.stockMovements || productDetails.stock_movements || [])" :key="m.id">
                  <td>{{ new Date(m.created_at).toLocaleString() }}</td>
                  <td>{{ m.movement_type || m.type }}</td>
                  <td>{{ m.quantity }}</td>
                  <td>{{ m.reason || m.notes || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeDetailsModal" class="btn btn-secondary">Close</button>
        </div>
      </div>
    </div>

    <!-- Add Product Modal -->
    <div v-if="showAddProductModal" class="modal-overlay" @click.self="closeAddProductModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Add New Product</h2>
          <button @click="closeAddProductModal" class="close-btn">×</button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>Product Name <span class="required">*</span></label>
            <input 
              v-model="newProductForm.name" 
              type="text" 
              placeholder="e.g., Tuna - Steak (single)" 
              class="form-input"
              required 
            />
          </div>

          <div class="form-group">
            <label>Category <span class="required">*</span></label>
            <select v-model="newProductForm.category" class="form-input" required>
              <option value="">-- Select Category --</option>
              <option v-for="cat in ['Tuna', 'Pompano', 'Seabass', 'Salmon', 'Squid', 'Shell']" :key="cat" :value="cat">
                {{ cat }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea
              v-model="newProductForm.description"
              rows="3"
              placeholder="Optional description"
              class="form-input"
            ></textarea>
          </div>

          <div class="form-group">
            <label>Unit of Measure <span class="required">*</span></label>
            <select v-model="newProductForm.unit_of_measure" class="form-input" required>
              <option value="">-- Select Unit --</option>
              <option value="per pack">Per Pack</option>
              <option value="by kg">By KG</option>
            </select>
          </div>

          <div class="form-group">
            <label>Retail Price (₱)</label>
            <input 
              v-model.number="newProductForm.retail_price" 
              type="number" 
              min="0" 
              step="0.01"
              placeholder="e.g., 250.00"
              class="form-input"
            />
          </div>

          <div class="form-group">
            <label>Wholesale Price (₱)</label>
            <input 
              v-model.number="newProductForm.wholesale_price" 
              type="number" 
              min="0" 
              step="0.01"
              placeholder="e.g., 180.00"
              class="form-input"
            />
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeAddProductModal" class="btn btn-secondary">Cancel</button>
          <button @click="createProduct" :disabled="savingProduct" class="btn btn-primary">
            {{ savingProduct ? 'Creating...' : 'Create Product' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api';

const router = useRouter();
const searchQuery = ref('');
const selectedCategory = ref('All');
const loading = ref(false);
const error = ref('');
const updating = ref(false);

const categories = ['All', 'Tuna', 'Pompano', 'Seabass', 'Salmon', 'Squid', 'Shell'];

const products = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

const showDetailsModal = ref(false);
const productDetails = ref(null);

const filteredProducts = computed(() => {
  return products.value.filter(p => {
    const matchCategory = selectedCategory.value === 'All' || p.category === selectedCategory.value;
    const matchSearch = p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                        p.sku.toLowerCase().includes(searchQuery.value.toLowerCase());
    return matchCategory && matchSearch;
  });
});

const showStockModal = ref(false);
const selectedProduct = ref(null);
const adjustmentAmount = ref(0);
const adjustmentReason = ref('restock');
const adjustmentNotes = ref('');

const showAddProductModal = ref(false);
const savingProduct = ref(false);
const newProductForm = ref({
  name: '',
  category: '',
  description: '',
  unit_of_measure: '',
  retail_price: null,
  wholesale_price: null,
});

const fetchProducts = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    // Use inventory API which includes stock movements and product relation
    const response = await api.get('/inventory', {
      params: { page, per_page: pagination.value.per_page }
    });
    if (response.data.success) {
      pagination.value = response.data.pagination || pagination.value;
      // response.data.data is a list of inventory records with 'product' and 'stockMovements'
      products.value = response.data.data.map(inv => {
        const p = inv.product || {};
        const pricing = p.pricing || p.Pricing || [];

        // determine last movement date from stockMovements (if any)
        const movements = inv.stockMovements || inv.stock_movements || [];
        const lastMovement = movements.length ? movements.reduce((a, b) => (new Date(a.created_at) > new Date(b.created_at) ? a : b)) : null;

        // compute active if there was a movement within last 2 months
        let active = false;
        if (lastMovement && lastMovement.created_at) {
          const twoMonthsAgo = new Date();
          twoMonthsAgo.setMonth(twoMonthsAgo.getMonth() - 2);
          active = new Date(lastMovement.created_at) >= twoMonthsAgo;
        }

        return {
          id: p.id,
          name: p.name,
          category: p.category,
          sku: p.sku,
          quantity: inv.quantity_on_hand ?? inv.quantity ?? 0,
          reorder_level: inv.reorder_point ?? inv.reorder_level ?? 0,
          retail_price: (Array.isArray(pricing) && pricing[0]) ? pricing[0].retail_price : p.base_price ?? 0,
          wholesale_price: (Array.isArray(pricing) && pricing[0]) ? pricing[0].wholesale_price : null,
          price: (Array.isArray(pricing) && pricing[0]) ? pricing[0].retail_price : p.base_price ?? 0,
          last_movement_at: lastMovement?.created_at ?? null,
          active,
          rawInventory: inv,
        };
      });
    } else {
      error.value = response.data.message || 'Failed to load products';
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load products';
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  fetchProducts(page);
};

const openStockUpdateModal = (product) => {
  selectedProduct.value = product;
  adjustmentAmount.value = 0;
  adjustmentReason.value = 'restock';
  adjustmentNotes.value = '';
  showStockModal.value = true;
};

const closeStockModal = () => {
  showStockModal.value = false;
  selectedProduct.value = null;
};

const submitStockUpdate = async () => {
  updating.value = true;
  try {
    const updateData = {};

    if (adjustmentAmount.value !== 0) {
      updateData.adjustment_quantity = adjustmentAmount.value;
      updateData.adjustment_reason = adjustmentReason.value;
    }

    // optional note field - controller will ignore unknown fields
    if (adjustmentNotes.value) {
      updateData.adjustment_note = adjustmentNotes.value;
    }

    const response = await api.put(`/inventory/${selectedProduct.value.id}`, updateData);
    if (response.data.success) {
      await fetchProducts(pagination.value.current_page);
      closeStockModal();
    } else {
      alert(response.data.message || 'Failed to update stock');
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to update stock');
  } finally {
    updating.value = false;
  }
};

const getStockBarWidth = (product) => {
  // Calculate percentage based on current stock vs reorder level
  // If stock is 0, show 0%
  // If stock equals reorder level, show ~33%
  // If stock is 3x reorder level, show 100%
  if (product.quantity === 0) return 0;
  const maxOptimalStock = product.reorder_level * 3;
  return Math.min((product.quantity / maxOptimalStock) * 100, 100);
};

const getStockStatus = (product) => {
  if (product.quantity <= product.reorder_level) return 'low';
  if (product.quantity > product.reorder_level * 2) return 'high';
  return 'normal';
};

const viewProductDetails = async (id) => {
  try {
    loading.value = true;
    const res = await api.get(`/inventory/${id}`);
    if (res.data.success) {
      // InventoryController returns inventory record with product and stockMovements
      productDetails.value = res.data.data;
      showDetailsModal.value = true;
    } else {
      alert(res.data.message || 'Failed to load product details');
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to load product details');
  } finally {
    loading.value = false;
  }
};

const closeDetailsModal = () => {
  showDetailsModal.value = false;
  productDetails.value = null;
};

const closeAddProductModal = () => {
  showAddProductModal.value = false;
  newProductForm.value = {
    name: '',
    category: '',
    description: '',
    unit_of_measure: '',
    retail_price: null,
    wholesale_price: null,
  };
};

const createProduct = async () => {
  if (!newProductForm.value.name || !newProductForm.value.category || !newProductForm.value.unit_of_measure) {
    alert('Please fill in all required fields: Name, Category, and Unit of Measure');
    return;
  }

  savingProduct.value = true;
  try {
    const payload = {
      name: newProductForm.value.name,
      category: newProductForm.value.category,
      description: newProductForm.value.description || null,
      unit_of_measure: newProductForm.value.unit_of_measure,
      retail_price: newProductForm.value.retail_price || 0,
      wholesale_price: newProductForm.value.wholesale_price || 0,
    };

    const res = await api.post('/products', payload);
    if (res.data.success) {
      await fetchProducts(pagination.value.current_page);
      closeAddProductModal();
    } else {
      alert(res.data.message || 'Failed to create product');
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to create product');
  } finally {
    savingProduct.value = false;
  }
};

onMounted(() => {
  fetchProducts(1);
});
</script>

<style scoped>
.inventory-container {
  max-width: 1400px;
  margin: 0 auto;
  animation: fadeIn 0.3s ease-in;
  padding: 20px 0;
}

.header-section {
  margin-bottom: 32px;
  padding: 24px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.header-section h1 {
  margin: 0 0 20px 0;
  color: #0a1d37;
  font-size: 28px;
  font-weight: 700;
}

.header-controls {
  display: flex;
  gap: 20px;
  align-items: center;
  flex-wrap: wrap;
}

.search-box {
  flex: 1;
  max-width: 350px;
  padding: 12px 16px;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s ease;
  background-color: #fafbfc;
}

.search-box:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
  background-color: white;
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s ease;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-secondary {
  background-color: #f8f9fa;
  color: #495057;
  border: 2px solid #e9ecef;
}

.btn-secondary:hover {
  background-color: #e9ecef;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
  padding: 20px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.filter-btn {
  padding: 10px 20px;
  border: 2px solid #e9ecef;
  background: white;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.filter-btn:hover {
  border-color: #e57c2a;
  color: #e57c2a;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.1);
}

.filter-btn.active {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.3);
}

.table-container {
  overflow-x: auto;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 12px;
  background: #fff;
  border-top: 1px solid #eef1f5;
}

.page-info {
  font-size: 13px;
  color: #5f6b7a;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.data-table thead {
  background-color: #f9f9f9;
}

.data-table th {
  padding: 14px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #666;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.data-table td {
  padding: 14px;
  border-bottom: 1px solid #e0e0e0;
}

.data-table tbody tr:hover {
  background-color: #f9f9f9;
}

.actions-cell {
  display: flex;
  gap: 8px;
}

.status {
  display: inline-block;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status.low {
  background-color: #fff3e0;
  color: #f57c00;
}

.status.normal {
  background-color: #e8f5e9;
  color: #388e3c;
}

.status.high {
  background-color: #e3f2fd;
  color: #1976d2;
}

.card-actions {
  display: flex;
  gap: 12px;
}

.btn-small {
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-update {
  background-color: #e57c2a;
  color: white;
}

.btn-update:hover {
  background-color: #d46a1a;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.3);
}

.btn-small {
  background-color: #f8f9fa;
  color: #495057;
  border: 1px solid #e9ecef;
}

.btn-small:hover {
  background-color: #e9ecef;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(2px);
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  animation: modalFadeIn 0.3s ease-out;
}

.modal-header {
  position: sticky;
  top: 0;
  z-index: 3;
  background: white;
  padding: 24px 24px 20px 24px;
  border-bottom: 1px solid #f0f0f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  margin: 0;
  color: #0a1d37;
  font-size: 20px;
  font-weight: 600;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #999;
  cursor: pointer;
  transition: all 0.3s ease;
  padding: 4px;
  border-radius: 6px;
}

.close-btn:hover {
  color: #333;
  background-color: #f8f9fa;
}

.modal-body {
  padding: 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

.required {
  color: #d32f2f;
  font-weight: 700;
}

.form-input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  background-color: #fafbfc;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
  background-color: white;
}

.form-input::placeholder {
  color: #999;
}

.form-hint {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: #666;
  font-weight: 400;
}

.current-stock {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #e57c2a;
}

.adjustment-controls {
  display: flex;
  gap: 12px;
  align-items: center;
}

.btn-qty {
  width: 44px;
  height: 44px;
  border: 2px solid #e9ecef;
  background: #fafbfc;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  font-size: 18px;
}

.btn-qty:hover {
  background: #e57c2a;
  color: white;
  border-color: #e57c2a;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.2);
}

.adjustment-controls input {
  flex: 1;
  padding: 12px 16px;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  font-size: 14px;
  text-align: center;
  transition: all 0.3s ease;
  background-color: #fafbfc;
}

.adjustment-controls input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
  background-color: white;
}

.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  background-color: #fafbfc;
}

.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
  background-color: white;
}

.modal-footer {
  padding: 20px 24px 24px 24px;
  border-top: 1px solid #f0f0f0;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover {
  background-color: #d46a1a;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(229, 124, 42, 0.3);
}

.loading-state, .error-state, .no-data {
  text-align: center;
  padding: 60px 40px;
  color: #666;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  margin: 20px 0;
}

.error-state {
  color: #d32f2f;
  background-color: #fef2f2;
  border-left: 4px solid #d32f2f;
}

.no-data {
  grid-column: 1 / -1;
}

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

@keyframes modalFadeIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@media (max-width: 768px) {
  .inventory-container {
    padding: 10px 0;
  }

  .header-section {
    padding: 20px;
  }

  .header-section h1 {
    font-size: 24px;
  }

  .header-controls {
    flex-direction: column;
    align-items: stretch;
    gap: 16px;
  }

  .search-box {
    max-width: none;
  }

  .filters {
    padding: 16px;
  }

  .inventory-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }

  .inventory-card {
    padding: 20px;
  }

  .modal-content {
    width: 95%;
    margin: 20px;
  }

  .modal-header,
  .modal-body,
  .modal-footer {
    padding-left: 20px;
    padding-right: 20px;
  }
}
</style>
