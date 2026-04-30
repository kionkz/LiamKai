<template>
  <div class="inventory-container">
    <div class="header-section">
      <div class="header-controls">
        <input v-model="searchQuery" type="text" placeholder="Search products..." class="search-box" />
        <button v-if="canWriteProducts" @click="showAddProductModal = true" class="btn btn-primary">New Product</button>
      </div>
    </div>

    <div class="actions-bar">
      <div class="selection-state">
        <strong>{{ selectedProduct ? selectedProduct.name : 'No product selected' }}</strong>
        <span>{{ selectedProduct ? 'Inventory and pricing actions are enabled.' : 'Select a row to enable inventory actions.' }}</span>
      </div>
      <div class="toolbar-actions">
        <button v-if="canAdjustInventory" @click="openStockUpdateSelected" class="btn btn-secondary" :disabled="!selectedProduct">Update Stock</button>
        <button @click="openDetailsSelected" class="btn btn-secondary" :disabled="!selectedProduct">Product Profile</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><p>Loading inventory...</p></div>
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="fetchProducts" class="btn btn-secondary">Retry</button>
    </div>

    <div v-else>
      <div class="filters">
        <div class="filter-group">
          <label for="category-filter">Category</label>
          <select id="category-filter" v-model="selectedCategoryId" class="category-select">
            <option v-for="category in categoryFilters" :key="category.id" :value="category.id">{{ category.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="inventory-sort-by">Sort By</label>
          <select id="inventory-sort-by" v-model="sortBy" class="category-select" data-searchable="off" @change="fetchProducts(1)">
            <option value="product_name">Product</option>
            <option value="sku">SKU</option>
            <option value="quantity">Current Stock</option>
            <option value="reorder_point">Reorder Level</option>
            <option value="retail_price">Retail Price</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="inventory-sort-direction">Order</label>
          <select id="inventory-sort-direction" v-model="sortDirection" class="category-select" data-searchable="off" @change="fetchProducts(1)">
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
          </select>
        </div>
      </div>

      <div class="table-container">
        <div v-if="filteredProducts.length === 0" class="no-data"><p>No products in this category.</p></div>
        <table v-else class="data-table">
          <thead>
            <tr>
                <th class="select-column"></th>
              <th>SKU</th>
              <th>Product</th>
              <th>Current Stock</th>
              <th>Reorder Level</th>
              <th>Retail Price</th>
              <th>Discount Amt</th>
              <th>Wholesale Price</th>
              <th>Stock Health</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="product in filteredProducts"
              :key="product.id"
              @click="selectProduct(product)"
              :class="{ 'selected-row': selectedProductId === product.id }"
            >
              <td class="select-column" @click.stop>
                <input type="checkbox" :checked="selectedProductId === product.id" @change="selectProduct(product)" />
              </td>
              <td class="sku-cell">{{ formatSku(product.sku) }}</td>
              <td class="name-cell">{{ product.name }}</td>
              <td>{{ product.quantity }}</td>
              <td>{{ product.reorder_level }}</td>
              <td>{{ formatCurrency(product.retail_price) }}</td>
              <td>{{ formatCurrency(product.discount_amount) }}</td>
              <td>{{ formatCurrency(product.discounted_price) }}</td>
              <td>
                <span class="status" :class="stockHealth(product.quantity).className">
                  {{ stockHealth(product.quantity).label }}
                </span>
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
      <div class="modal-content stock-modal" @click.stop>
        <div class="modal-header">
          <h2>Update Stock - {{ stockTarget?.name }}</h2>
          <button @click="closeStockModal" class="btn-close">&times;</button>
        </div>
        <div class="modal-body">
          <div class="stock-summary-grid">
            <div>
              <span>SKU</span>
              <strong>{{ formatSku(stockTarget?.sku) }}</strong>
            </div>
            <div>
              <span>Current Total Stock</span>
              <strong>{{ formatNumber(stockTarget?.quantity) }} {{ stockTarget?.unit_of_measure || 'units' }}</strong>
            </div>
            <div>
              <span>Selected Batch Available</span>
              <strong>{{ selectedBatch ? formatNumber(selectedBatch.available_quantity) : '—' }}</strong>
            </div>
          </div>

          <div class="profile-grid">
            <div class="form-group">
              <label>Action Type</label>
              <select v-model="stockActionType" class="form-input" data-searchable="off">
                <option value="stock_out">Stock Out / Deduct</option>
                <option value="manual_adjustment">Manual Adjustment</option>
              </select>
            </div>
            <div class="form-group">
              <label>Reason</label>
              <SearchableSelect v-model="adjustmentReason" :options="adjustmentReasonOptions" placeholder="Select reason" />
            </div>
          </div>

          <div class="form-group">
            <label>Quantity to Deduct</label>
            <div class="adjustment-controls">
              <button @click="adjustmentAmount = Math.max(0, adjustmentAmount - 1)" class="btn-qty" type="button">-</button>
              <input v-model.number="adjustmentAmount" type="number" min="0" :max="selectedBatch?.available_quantity || undefined" step="0.01" @input="adjustmentAmount = Math.max(0, adjustmentAmount || 0)" />
              <button @click="adjustmentAmount++" class="btn-qty" type="button">+</button>
            </div>
          </div>

          <div class="batch-panel">
            <div class="batch-panel-header">
              <div>
                <h3>Receipt Batches</h3>
                <p>Select the exact received batch to deduct from.</p>
              </div>
              <input v-model="batchExpirationFilter" class="form-input batch-filter" type="date" />
            </div>
            <div v-if="batchLoading" class="no-data">Loading batches...</div>
            <div v-else-if="filteredBatches.length === 0" class="no-data">No available receipt batches found for this product.</div>
            <table v-else class="data-table batch-table">
              <thead>
                <tr>
                  <th class="select-column"></th>
                  <th>Available Quantity</th>
                  <th>Expiration Date</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="batch in pagedBatches"
                  :key="batch.id"
                  :class="{ 'selected-row': selectedBatchId === batch.id, 'expired-row': batch.expired }"
                  @click="selectedBatchId = batch.id"
                >
                  <td class="select-column">
                    <input type="radio" name="stock-batch" :checked="selectedBatchId === batch.id" @change="selectedBatchId = batch.id" />
                  </td>
                  <td>{{ formatNumber(batch.available_quantity) }}</td>
                  <td>
                    {{ formatDate(batch.expiration_date) }}
                    <span v-if="batch.expired" class="status low">Expired</span>
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="pagination compact" v-if="batchLastPage > 1">
              <button class="btn btn-secondary" type="button" @click="batchPage = Math.max(1, batchPage - 1)" :disabled="batchPage === 1">Previous</button>
              <span class="page-info">Page {{ batchPage }} of {{ batchLastPage }}</span>
              <button class="btn btn-secondary" type="button" @click="batchPage = Math.min(batchLastPage, batchPage + 1)" :disabled="batchPage === batchLastPage">Next</button>
            </div>
          </div>

          <div class="form-group">
            <label>Notes</label>
            <textarea v-model="adjustmentNotes" rows="3" placeholder="Optional notes..."></textarea>
          </div>
          <p v-if="stockValidationMessage" class="inline-error">{{ stockValidationMessage }}</p>
        </div>
        <div class="modal-footer">
          <button @click="closeStockModal" class="btn btn-secondary">Cancel</button>
          <button @click="submitStockUpdate" :disabled="updating || !isStockUpdateValid" class="btn btn-primary">
            {{ updating ? 'Updating...' : 'Update Stock' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Product Profile Modal -->
    <div v-if="showDetailsModal" class="modal-overlay" @click.self="closeDetailsModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Product Profile - {{ profileProduct.name || 'Product' }}</h2>
          <button @click="closeDetailsModal" class="btn-close">&times;</button>
        </div>
        <div class="modal-body" v-if="productDetails">
          <div class="profile-grid">
            <div class="form-group">
              <label>SKU</label>
              <input :value="formatSku(profileForm.sku)" type="text" readonly class="readonly-input" />
              <small class="field-hint">SKU is system-generated and updates automatically when product details change.</small>
            </div>
            <div class="form-group">
              <label>Product Name</label>
              <input v-model="profileForm.name" type="text" :readonly="!canWriteProductProfile" :class="{ 'readonly-input': !canWriteProductProfile }" />
            </div>
            <div class="form-group">
              <label>Category</label>
              <SearchableSelect v-model="profileForm.category_id" :options="inventoryCategoryOptions" placeholder="Select category" :disabled="!canWriteProductProfile" />
            </div>
            <div class="form-group">
              <label>Unit of Measure</label>
              <div class="choice-group" role="radiogroup" aria-label="Unit of measure">
                <label class="choice-option" :class="{ active: profileForm.unit_of_measure === 'kg' }">
                  <input v-model="profileForm.unit_of_measure" type="radio" name="profile-unit-of-measure" value="kg" :disabled="!canWriteProductProfile" />
                  <span>kg</span>
                </label>
                <label class="choice-option" :class="{ active: profileForm.unit_of_measure === 'Per pack' }">
                  <input v-model="profileForm.unit_of_measure" type="radio" name="profile-unit-of-measure" value="Per pack" :disabled="!canWriteProductProfile" />
                  <span>Per pack</span>
                </label>
              </div>
            </div>
            <div class="form-group">
              <label>Reorder Level</label>
              <input v-model.number="profileForm.reorder_point" type="number" min="0" step="0.01" :readonly="!canWriteProductProfile" :class="{ 'readonly-input': !canWriteProductProfile }" @input="profileForm.reorder_point = Math.max(0, profileForm.reorder_point || 0)" />
            </div>
            <div class="form-group">
              <label>Retail Price</label>
              <input v-model.number="profileForm.retail_price" type="number" min="0" step="0.01" :readonly="!canWriteProductProfile" :class="{ 'readonly-input': !canWriteProductProfile }" @input="profileForm.retail_price = Math.max(0, profileForm.retail_price || 0)" />
            </div>
            <div class="form-group">
              <label>Discount Amount</label>
              <input v-model.number="profileForm.discount_amount" type="number" min="0" step="0.01" :max="profileForm.retail_price" :readonly="!canWriteProductProfile" :class="{ 'readonly-input': !canWriteProductProfile }" @input="profileForm.discount_amount = Math.max(0, profileForm.discount_amount || 0)" />
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea v-model="profileForm.description" rows="3" :readonly="!canWriteProductProfile" :class="{ 'readonly-input': !canWriteProductProfile }"></textarea>
          </div>

          <div class="pricing-preview">
            <span>Wholesale price (Retail − Discount)</span>
            <strong>{{ formatCurrency(profileDiscountedPrice) }}</strong>
          </div>

          <h3 style="margin-top:12px">Stock Batches</h3>
          <div v-if="profileBatches.length === 0">
            <p>No dated stock batches available yet.</p>
          </div>
          <table v-else class="data-table">
            <thead><tr><th>Date Added</th><th>Expiration Date</th><th>Original Qty</th><th>Available Qty</th><th>Reference</th></tr></thead>
            <tbody>
              <tr v-for="batch in profileBatches" :key="batch.id" :class="{ 'expired-row': batch.expired }">
                <td>{{ formatDateTime(batch.date_added || batch.created_at) }}</td>
                <td>{{ formatDate(batch.expiration_date) }}</td>
                <td>{{ formatNumber(batch.original_quantity ?? batch.quantity) }}</td>
                <td>{{ formatNumber(batch.available_quantity ?? batch.remaining_quantity ?? batch.quantity) }}</td>
                <td>{{ batch.reference || '-' }}</td>
              </tr>
            </tbody>
          </table>

          <h3 style="margin-top:12px">Pricing Log</h3>
          <div v-if="pricingLogs.length === 0">
            <p>No pricing changes recorded yet.</p>
          </div>
          <table v-else class="data-table">
            <thead><tr><th>Date</th><th>Changed By</th><th>Retail</th><th>Wholesale</th><th>Previous Retail</th><th>Previous Wholesale</th></tr></thead>
            <tbody>
              <tr v-for="entry in pricingLogs" :key="entry.id">
                <td>{{ formatDateTime(entry.changed_at || entry.created_at) }}</td>
                <td>{{ entry.changed_by_user?.name || entry.changed_by_name || '-' }}</td>
                <td>{{ formatCurrency(entry.new_retail_price) }}</td>
                <td>{{ formatCurrency(resolveLogWholesale(entry, 'new')) }}</td>
                <td>{{ formatCurrency(entry.old_retail_price) }}</td>
                <td>{{ formatCurrency(resolveLogWholesale(entry, 'old')) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button v-if="canWriteProductProfile" @click="saveProductProfile" :disabled="savingProfile" class="btn btn-primary">
            {{ savingProfile ? 'Saving...' : 'Save Profile' }}
          </button>
          <button @click="closeDetailsModal" class="btn btn-secondary">Close</button>
        </div>
      </div>
    </div>

    <!-- Add Product Modal -->
    <div v-if="showAddProductModal" class="modal-overlay" @click.self="closeAddProductModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Add New Product</h2>
          <button @click="closeAddProductModal" class="btn-close">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Product Name <span class="required">*</span></label>
            <input v-model="newProductForm.name" type="text" placeholder="e.g., Tuna - Steak (single)" class="form-input" required />
          </div>
          <div class="form-group">
            <label>Category <span class="required">*</span></label>
            <SearchableSelect v-model="newProductForm.category_id" :options="inventoryCategoryOptions" placeholder="-- Select Category --" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="newProductForm.description" rows="3" placeholder="Optional description" class="form-input"></textarea>
          </div>
          <div class="form-group">
            <label>Unit of Measure <span class="required">*</span></label>
            <div class="choice-group" role="radiogroup" aria-label="Unit of measure">
              <label class="choice-option" :class="{ active: newProductForm.unit_of_measure === 'kg' }">
                <input v-model="newProductForm.unit_of_measure" type="radio" name="inventory-unit-of-measure" value="kg" required />
                <span>kg</span>
              </label>
              <label class="choice-option" :class="{ active: newProductForm.unit_of_measure === 'Per pack' }">
                <input v-model="newProductForm.unit_of_measure" type="radio" name="inventory-unit-of-measure" value="Per pack" required />
                <span>Per pack</span>
              </label>
            </div>
          </div>
          <div class="form-group">
            <label>Retail Price</label>
            <input v-model.number="newProductForm.retail_price" type="number" min="0" step="0.01" placeholder="e.g., 250.00" class="form-input" @input="newProductForm.retail_price = Math.max(0, newProductForm.retail_price || 0)" />
          </div>
          <div class="form-group">
            <label>Discount Amount</label>
            <input v-model.number="newProductForm.discount_amount" type="number" min="0" step="0.01" :max="newProductForm.retail_price || undefined" placeholder="e.g., 50.00" class="form-input" @input="newProductForm.discount_amount = Math.max(0, newProductForm.discount_amount || 0)" />
          </div>
          <div class="pricing-preview">
            <span>Wholesale price</span>
            <strong>{{ formatCurrency(newProductDiscountedPrice) }}</strong>
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
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api';
import SearchableSelect from '../../components/SearchableSelect.vue';
import { useAuthStore } from '../../stores/authStore';
import { formatPeso } from '../../utils/currency';
import { calculateDiscountedPriceFromAmount, discountAmountToPercent, normalizeDiscountPercent, resolveDiscountAmount, resolveDiscountedPrice, resolveRetailPrice } from '../../utils/pricing';

const route = useRoute();
const authStore = useAuthStore();
const canWriteProducts = computed(() => authStore.can('products.write'));
const canAdjustInventory = computed(() => authStore.can('inventory.adjust'));
const canWriteProductProfile = computed(() => authStore.can('inventory.profile.write'));
const searchQuery = ref('');
const selectedCategoryId = ref('all');
const sortBy = ref('product_name');
const sortDirection = ref('asc');
const loading = ref(false);
const error = ref('');
const updating = ref(false);
const selectedProductId = ref(null);

const products = ref([]);
const categories = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

const showDetailsModal = ref(false);
const productDetails = ref(null);
const savingProfile = ref(false);
const profileForm = ref({ sku: '', name: '', category_id: '', description: '', unit_of_measure: '', reorder_point: 0, retail_price: 0, discount_amount: 0 });
const showStockModal = ref(false);
const stockTarget = ref(null);
const stockActionType = ref('stock_out');
const adjustmentAmount = ref(0);
const adjustmentReason = ref('damage');
const adjustmentNotes = ref('');
const selectedBatchId = ref(null);
const batchExpirationFilter = ref('');
const batchPage = ref(1);
const batchPerPage = 5;
const batchLoading = ref(false);
const availableBatches = ref([]);

const adjustmentReasonOptions = [
  { value: 'damage', label: 'Damage/Defect' },
  { value: 'theft', label: 'Theft/Loss' },
];

const categoryFilters = computed(() => [{ id: 'all', name: 'All' }, ...categories.value]);

const inventoryCategoryOptions = computed(() => categories.value.map((category) => ({ value: String(category.id), label: category.name })));

const showAddProductModal = ref(false);
const savingProduct = ref(false);
const newProductForm = ref({ name: '', category_id: '', description: '', unit_of_measure: '', retail_price: null, discount_amount: 0 });

const normalizeUnitOfMeasure = (value) => {
  if (value === 'by kg') return 'kg';
  if (value === 'per pack' || value === 'Per Pack') return 'Per pack';
  return value || '';
};

const formatCurrency = (val) => val != null ? formatPeso(val) : '-';
const formatNumber = (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatSku = (value) => value || '—';
const formatDate = (value) => value ? new Date(value).toLocaleDateString() : '-';
const formatDateTime = (value) => value ? new Date(value).toLocaleString() : '-';
const resolveLogWholesale = (entry, key = 'new') => {
  const discounted = Number(key === 'new' ? entry?.new_discounted_price : entry?.old_discounted_price);
  if (Number.isFinite(discounted) && discounted > 0) {
    return discounted;
  }

  const retail = Number(key === 'new' ? entry?.new_retail_price : entry?.old_retail_price);
  const discountPercent = normalizeDiscountPercent(key === 'new' ? entry?.new_discount_percent : entry?.old_discount_percent);

  if (!Number.isFinite(retail) || retail <= 0) {
    return 0;
  }

  return Math.max(0, Math.round(retail * (1 - discountPercent / 100) * 100) / 100);
};
const stockHealth = (quantity) => {
  const current = Number(quantity || 0);
  if (current <= 10) return { label: 'Low', className: 'low' };
  if (current < 30) return { label: 'Adequate', className: 'normal' };
  return { label: 'High', className: 'high' };
};
const newProductDiscountedPrice = computed(() => calculateDiscountedPriceFromAmount(newProductForm.value.retail_price, newProductForm.value.discount_amount));
const profileDiscountedPrice = computed(() => calculateDiscountedPriceFromAmount(profileForm.value.retail_price, profileForm.value.discount_amount));
const profileProduct = computed(() => productDetails.value?.product || selectedProduct.value || {});
const pricingLogs = computed(() => profileProduct.value?.pricingLogs || profileProduct.value?.pricing_logs || []);
const profileBatches = computed(() => {
  const apiBatches = productDetails.value?.available_batches || [];
  if (apiBatches.length) return apiBatches;

  const movements = productDetails.value?.stockMovements || productDetails.value?.stock_movements || [];

  return movements
    .filter((movement) => (movement.type === 'stock_in' || movement.movement_type === 'purchase_receipt') && movement.expiration_date)
    .sort((a, b) => new Date(a.expiration_date).getTime() - new Date(b.expiration_date).getTime());
});

const filteredBatches = computed(() => {
  return availableBatches.value.filter((batch) => !batchExpirationFilter.value || batch.expiration_date === batchExpirationFilter.value);
});

const batchLastPage = computed(() => Math.max(1, Math.ceil(filteredBatches.value.length / batchPerPage)));

const pagedBatches = computed(() => {
  const start = (batchPage.value - 1) * batchPerPage;
  return filteredBatches.value.slice(start, start + batchPerPage);
});

const selectedBatch = computed(() => availableBatches.value.find((batch) => batch.id === selectedBatchId.value) || null);

const stockValidationMessage = computed(() => {
  if (!selectedBatch.value) return 'Select a receipt batch before updating stock.';
  if (!adjustmentReason.value) return 'Select a reason.';
  if (!Number(adjustmentAmount.value) || Number(adjustmentAmount.value) <= 0) return 'Quantity must be greater than zero.';
  if (Number(adjustmentAmount.value) > Number(selectedBatch.value.available_quantity || 0)) return 'Quantity cannot exceed selected batch availability.';
  return '';
});

const isStockUpdateValid = computed(() => stockValidationMessage.value === '');

const filteredProducts = computed(() => {
  return products.value.filter((p) => {
    const matchCategory = selectedCategoryId.value === 'all' || String(p.category_id || '') === String(selectedCategoryId.value);
    const matchSearch = p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                        String(p.sku || '').toLowerCase().includes(searchQuery.value.toLowerCase());
    return matchCategory && matchSearch;
  });
});

const fetchCategories = async () => {
  try {
    const response = await api.get('/categories', { params: { per_page: 250 } });
    if (response.data?.success) {
      categories.value = response.data.data || [];
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load categories';
  }
};

const selectedProduct = computed(() => products.value.find(p => p.id === selectedProductId.value) || null);

const selectProduct = (product) => { selectedProductId.value = selectedProductId.value === product.id ? null : product.id; };

const fetchProducts = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/inventory', {
      params: {
        page,
        per_page: pagination.value.per_page,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
      },
    });
    if (response.data.success) {
      pagination.value = response.data.pagination || pagination.value;
      products.value = response.data.data.map(inv => {
        const p = inv.product || {};
        const pricing = p.pricing || p.Pricing || [];
        return {
          id: p.id, name: p.name, category: p.category || p.product_category?.name, category_id: p.category_id, sku: p.sku,
          unit_of_measure: normalizeUnitOfMeasure(p.unit_of_measure),
          quantity: inv.quantity_on_hand ?? inv.quantity ?? 0,
          reorder_level: inv.reorder_point ?? inv.reorder_level ?? 0,
          retail_price: resolveRetailPrice(p),
          discount_amount: resolveDiscountAmount(p),
          discounted_price: resolveDiscountedPrice(p),
          rawInventory: inv,
        };
      });
      if (selectedProductId.value && !products.value.some(p => p.id === selectedProductId.value)) {
        selectedProductId.value = null;
      }
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

const openStockUpdateSelected = () => {
  if (!canAdjustInventory.value) return;
  if (!selectedProduct.value) return;
  stockTarget.value = selectedProduct.value;
  stockActionType.value = 'stock_out';
  adjustmentAmount.value = 0;
  adjustmentReason.value = 'damage';
  adjustmentNotes.value = '';
  selectedBatchId.value = null;
  batchExpirationFilter.value = '';
  batchPage.value = 1;
  fetchProductBatches(selectedProduct.value.id);
  showStockModal.value = true;
};

const closeStockModal = () => { showStockModal.value = false; stockTarget.value = null; availableBatches.value = []; };

const fetchProductBatches = async (productId) => {
  batchLoading.value = true;
  try {
    const response = await api.get('/inventory/' + productId);
    if (response.data.success) {
      availableBatches.value = response.data.data.available_batches || [];
      if (availableBatches.value.length) {
        selectedBatchId.value = availableBatches.value[0].id;
      }
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to load stock batches');
  } finally {
    batchLoading.value = false;
  }
};

const submitStockUpdate = async () => {
  if (!canAdjustInventory.value) {
    alert('Access denied. You do not have permission to perform this action.');
    return;
  }
  if (!isStockUpdateValid.value) return;
  updating.value = true;
  try {
    const updateData = {
      action_type: stockActionType.value,
      batch_id: selectedBatchId.value,
      adjustment_quantity: adjustmentAmount.value,
      adjustment_reason: adjustmentReason.value,
    };
    if (adjustmentNotes.value) { updateData.adjustment_note = adjustmentNotes.value; }
    const response = await api.put('/inventory/' + stockTarget.value.id, updateData);
    if (response.data.success) { await fetchProducts(pagination.value.current_page); closeStockModal(); }
    else { alert(response.data.message || 'Failed to update stock'); }
  } catch (err) { alert(err.response?.data?.message || 'Failed to update stock'); }
  finally { updating.value = false; }
};

const openDetailsSelected = async () => {
  if (!selectedProduct.value) return;
  try {
    loading.value = true;
    const res = await api.get('/inventory/' + selectedProduct.value.id);
    if (res.data.success) {
      productDetails.value = res.data.data;
      const product = res.data.data.product || {};
      profileForm.value = {
        sku: product.sku || '',
        name: product.name || '',
        category_id: String(product.category_id || ''),
        description: product.description || '',
        unit_of_measure: normalizeUnitOfMeasure(product.unit_of_measure),
        reorder_point: Number(res.data.data.reorder_point ?? res.data.data.reorder_level ?? 0),
        retail_price: resolveRetailPrice(product),
        discount_amount: resolveDiscountAmount(product),
      };
      showDetailsModal.value = true;
    }
    else { alert(res.data.message || 'Failed to load product details'); }
  } catch (e) { alert(e.response?.data?.message || 'Failed to load product details'); }
  finally { loading.value = false; }
};

const closeDetailsModal = () => { showDetailsModal.value = false; productDetails.value = null; };

const saveProductProfile = async () => {
  if (!canWriteProductProfile.value) {
    alert('Access denied. You do not have permission to perform this action.');
    return;
  }
  if (!productDetails.value?.product?.id) return;
  savingProfile.value = true;
  try {
    const productId = productDetails.value.product.id;
    await api.put(`/products/${productId}`, {
      name: profileForm.value.name,
      category_id: Number(profileForm.value.category_id),
      description: profileForm.value.description || null,
      unit_of_measure: normalizeUnitOfMeasure(profileForm.value.unit_of_measure),
      retail_price: Number(profileForm.value.retail_price || 0),
      discount_percent: discountAmountToPercent(profileForm.value.retail_price, profileForm.value.discount_amount),
    });

    await api.put(`/inventory/${productId}`, {
      reorder_point: Number(profileForm.value.reorder_point || 0),
    });

    await fetchProducts(pagination.value.current_page);
    await openDetailsSelected();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save product profile');
  } finally {
    savingProfile.value = false;
  }
};

const closeAddProductModal = () => {
  showAddProductModal.value = false;
  newProductForm.value = { name: '', category_id: '', description: '', unit_of_measure: '', retail_price: null, discount_amount: 0 };
};

const createProduct = async () => {
  if (!canWriteProducts.value) {
    alert('Access denied. You do not have permission to perform this action.');
    return;
  }
  if (!newProductForm.value.name || !newProductForm.value.category_id || !newProductForm.value.unit_of_measure) {
    alert('Please fill in all required fields: Name, Category, and Unit of Measure');
    return;
  }
  savingProduct.value = true;
  try {
    const payload = {
      name: newProductForm.value.name, category_id: Number(newProductForm.value.category_id),
      description: newProductForm.value.description || null, unit_of_measure: normalizeUnitOfMeasure(newProductForm.value.unit_of_measure),
      retail_price: newProductForm.value.retail_price || 0, discount_percent: discountAmountToPercent(newProductForm.value.retail_price, newProductForm.value.discount_amount),
    };
    const res = await api.post('/products', payload);
    if (res.data.success) { await fetchProducts(pagination.value.current_page); closeAddProductModal(); }
    else { alert(res.data.message || 'Failed to create product'); }
  } catch (e) { alert(e.response?.data?.message || 'Failed to create product'); }
  finally { savingProduct.value = false; }
};

onMounted(async () => {
  await fetchCategories();
  await fetchProducts(1);

  if (route.query.newProduct === '1' && canWriteProducts.value) {
    showAddProductModal.value = true;
  }
});

watch(batchExpirationFilter, () => {
  batchPage.value = 1;
});
</script>

<style scoped>
.inventory-container { max-width: 1400px; margin: 0 auto; animation: fadeIn 0.3s ease-in; padding: 20px 0; }
.header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding: 24px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.header-section h1 { margin: 0; color: #0a1d37; font-size: 28px; font-weight: 700; }
.page-summary { margin: 8px 0 0; color: #607089; font-size: 14px; }
.header-controls { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
.search-box { flex: 1; max-width: 350px; padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; font-family: inherit; background-color: #fafbfc; transition: all 0.3s; }
.search-box:focus { outline: none; border-color: #e57c2a; box-shadow: 0 0 0 3px rgba(229,124,42,0.1); background: white; }
.actions-bar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; padding: 14px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.selection-state { display: flex; flex-direction: column; gap: 4px; }
.selection-state strong { color: #102746; font-size: 15px; }
.selection-state span { color: #607089; font-size: 13px; }
.toolbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.filters { display: flex; gap: 16px; align-items: center; margin-bottom: 24px; flex-wrap: wrap; padding: 16px 20px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.filter-group { display: flex; align-items: center; gap: 10px; }
.filter-group label { font-weight: 600; color: #666; font-size: 14px; white-space: nowrap; }
.category-select { padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; font-family: inherit; background: #fafbfc; min-width: 180px; cursor: pointer; }
.category-select:focus { outline: none; border-color: #e57c2a; box-shadow: 0 0 0 3px rgba(229,124,42,0.1); background: white; }
.table-container { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background-color: #f9fafb; }
.data-table th { padding: 14px 16px; text-align: left; font-weight: 600; color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e0e0e0; }
.data-table td { padding: 14px 16px; border-bottom: 1px solid #eef1f4; color: #333; }
.data-table tbody tr { cursor: pointer; transition: background-color 0.15s; }
.data-table tbody tr:hover { background-color: #f9fafb; }
.selected-row { background-color: #fff7ed !important; border-left: 3px solid #e57c2a; }
.select-column { width: 52px; text-align: center; }
.select-column input { width: 16px; height: 16px; cursor: pointer; }
.name-cell { font-weight: 600; color: #0a1d37; }
.no-data { text-align: center; color: #999; padding: 40px 16px; }
.pagination { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding: 14px 18px; border-top: 1px solid #edf0f4; }
.page-info { font-size: 13px; color: #4a5565; }
.status { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
.status.low { background-color: #fee2e2; color: #b91c1c; }
.status.normal { background-color: #e8f5e9; color: #388e3c; }
.status.high { background-color: #e3f2fd; color: #1976d2; }
.btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s; }
.btn-primary { background-color: #e57c2a; color: white; }
.btn-primary:hover { background-color: #d16a22; }
.btn-secondary { background-color: #6c757d; color: white; }
.btn-secondary:hover { background-color: #5a6268; }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: white; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
.profile-modal { max-width: 960px; width: min(96vw, 960px); }
.stock-modal { max-width: 1080px; width: min(96vw, 1080px); }
.modal-header { position: sticky; top: 0; z-index: 3; background: white; padding: 20px 24px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { margin: 0; color: #0a1d37; font-size: 18px; }
.btn-close { background: none; border: none; font-size: 24px; color: #999; cursor: pointer; }
.btn-close:hover { color: #333; }
.modal-body { padding: 24px; }
.modal-footer { padding: 20px 24px; border-top: 1px solid #f0f0f0; display: flex; gap: 12px; justify-content: flex-end; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px; }
.required { color: #d32f2f; font-weight: 700; }
.form-input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; font-family: inherit; background: #fafbfc; box-sizing: border-box; }
.form-input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #e57c2a; box-shadow: 0 0 0 3px rgba(229,124,42,0.1); background: white; }
.choice-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.choice-option { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 2px solid #e9ecef; border-radius: 10px; cursor: pointer; background: #fafbfc; transition: all 0.2s ease; }
.choice-option.active { border-color: #e57c2a; background: #fff7ed; box-shadow: 0 0 0 3px rgba(229,124,42,0.08); }
.choice-option input { width: 16px; height: 16px; margin: 0; accent-color: #e57c2a; }
.choice-option span { font-weight: 600; color: #102746; }
.profile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.readonly-input { background-color: #f5f7fa; color: #526173; cursor: not-allowed; }
.field-hint { display: inline-block; margin-top: 6px; color: #607089; font-size: 12px; }
.pricing-preview { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-radius: 10px; background: #fff7ed; color: #9a3412; font-weight: 600; margin-bottom: 12px; }
.pricing-preview strong { font-size: 18px; color: #7c2d12; }
.stock-summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
.stock-summary-grid > div { border: 1px solid #e4e9f0; border-radius: 10px; padding: 14px 16px; background: #f8fafc; }
.stock-summary-grid span { display: block; color: #607089; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 6px; }
.stock-summary-grid strong { color: #102746; font-size: 17px; }
.batch-panel { border: 1px solid #e4e9f0; border-radius: 12px; padding: 16px; margin: 16px 0; background: #fbfcfe; }
.batch-panel-header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-end; margin-bottom: 12px; }
.batch-panel-header h3 { margin: 0 0 4px; color: #102746; font-size: 16px; }
.batch-panel-header p { margin: 0; color: #607089; font-size: 13px; }
.batch-filter { max-width: 220px; }
.batch-table td { vertical-align: middle; }
.expired-row { background: #f3f4f6 !important; color: #7b8794; }
.pagination.compact { padding: 12px 0 0; border-top: 0; justify-content: center; }
.inline-error { margin: 8px 0 0; color: #b91c1c; font-weight: 600; font-size: 13px; }
.current-stock { margin: 0; font-size: 16px; font-weight: 600; color: #e57c2a; }
.adjustment-controls { display: flex; gap: 12px; align-items: center; }
.btn-qty { width: 44px; height: 44px; border: 2px solid #e9ecef; background: #fafbfc; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 18px; transition: all 0.3s; }
.btn-qty:hover { background: #e57c2a; color: white; border-color: #e57c2a; }
.adjustment-controls input { flex: 1; padding: 10px 14px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; text-align: center; background: #fafbfc; }
.adjustment-controls input:focus { outline: none; border-color: #e57c2a; box-shadow: 0 0 0 3px rgba(229,124,42,0.1); background: white; }
.loading-state, .error-state { text-align: center; padding: 60px 40px; color: #666; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin: 20px 0; }
.error-state { color: #d32f2f; background-color: #fef2f2; border-left: 4px solid #d32f2f; }
@media (max-width: 768px) {
  .profile-grid, .stock-summary-grid { grid-template-columns: 1fr; }
  .batch-panel-header { align-items: stretch; flex-direction: column; }
  .batch-filter { max-width: none; }
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
