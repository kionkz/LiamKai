<template>
  <div class="products-container">
    <div class="actions-bar">
      <div class="selection-state">
        <strong>{{ selectedProduct ? selectedProduct.name : 'No product selected' }}</strong>
        <span>{{ selectedProduct ? 'Pricing actions are enabled.' : 'Select a product row to edit or delete it.' }}</span>
      </div>
      <div class="toolbar-actions">
        <button @click="openCreateForm" class="btn btn-primary">New Product</button>
        <button @click="openEditSelected" class="btn btn-secondary" :disabled="!selectedProduct">Edit</button>
        <button @click="openDeleteSelected" class="btn btn-danger" :disabled="!selectedProduct">Delete</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state">Loading products...</div>
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
    </div>

    <div v-else class="table-container">
      <div class="filter-section">
        <label>Filter Category:
          <SearchableSelect v-model="selectedCategory" :options="categoryOptions" placeholder="Filter Category" />
        </label>
        <label>Sort By:
          <select v-model="sortBy" data-searchable="off" @change="fetchProducts(1)">
            <option value="name">Name</option>
            <option value="category">Category</option>
            <option value="unit_of_measure">Unit</option>
            <option value="retail_price">Retail</option>
          </select>
        </label>
        <label>Order:
          <select v-model="sortDirection" data-searchable="off" @change="fetchProducts(1)">
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
          </select>
        </label>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th class="select-column"></th>
            <th>Name</th>
            <th>Category</th>
            <th>Unit</th>
            <th>Retail</th>
            <th>Discount</th>
            <th>Wholesale</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredProducts.length === 0">
            <td colspan="7" class="no-data">No products yet. Use the New Product button to add one.</td>
          </tr>
          <tr
            v-for="p in filteredProducts"
            :key="p.id"
            @click="selectProduct(p)"
            :class="{ 'selected-row': selectedProductId === p.id }"
          >
            <td class="select-column" @click.stop>
              <input type="checkbox" :checked="selectedProductId === p.id" @change="selectProduct(p)" />
            </td>
            <td>{{ p.name }}</td>
            <td>{{ p.category || p.product_category?.name || 'Uncategorized' }}</td>
            <td>{{ p.unit_of_measure }}</td>
            <td>{{ formatPrice(resolveRetailPrice(p)) }}</td>
            <td>{{ formatPercent(resolveDiscountPercent(p)) }}</td>
            <td>{{ formatPrice(resolveDiscountedPrice(p)) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="pagination" v-if="pagination.last_page > 1">
        <button class="btn btn-secondary" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">Previous</button>
        <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button class="btn btn-secondary" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">Next</button>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showForm" class="modal-overlay" @click="closeForm">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editing ? 'Edit Product' : 'Add Product' }}</h2>
          <button @click="closeForm" class="btn-close">&times;</button>
        </div>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name *</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-group">
            <label>Category *</label>
            <SearchableSelect v-model="form.category_id" :options="productCategoryOptions" placeholder="Select category" />
          </div>
          <div class="form-group">
            <label>Unit of Measure *</label>
            <div class="choice-group" role="radiogroup" aria-label="Unit of measure">
              <label class="choice-option" :class="{ active: form.unit_of_measure === 'kg' }">
                <input v-model="form.unit_of_measure" type="radio" name="product-unit-of-measure" value="kg" required />
                <span>kg</span>
              </label>
              <label class="choice-option" :class="{ active: form.unit_of_measure === 'Per pack' }">
                <input v-model="form.unit_of_measure" type="radio" name="product-unit-of-measure" value="Per pack" required />
                <span>Per pack</span>
              </label>
            </div>
          </div>
          <div class="form-group">
            <label>Retail Price *</label>
            <input v-model.number="form.retail_price" type="number" min="0" step="0.01" required @input="form.retail_price = Math.max(0, form.retail_price || 0)" />
          </div>
          <div class="form-group">
            <label>Discount (%)</label>
            <input v-model.number="form.discount_percent" type="number" min="0" max="100" step="0.01" @input="form.discount_percent = Math.max(0, form.discount_percent || 0)" />
          </div>
          <div class="pricing-preview">
            <span>Wholesale price</span>
            <strong>{{ formatPrice(discountedPreview) }}</strong>
          </div>
          <div class="modal-actions">
            <button type="button" @click="closeForm" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary">{{ saving ? 'Saving...' : (editing ? 'Update' : 'Create') }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click="showDeleteConfirm = false">
      <div class="modal-content small-modal" @click.stop>
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete "{{ selectedProduct?.name }}"?</p>
        <p class="warning">This action cannot be undone.</p>
        <div class="modal-actions">
          <button @click="showDeleteConfirm = false" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="btn btn-danger">
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../api';
import SearchableSelect from '../../components/SearchableSelect.vue';
import {
  calculateDiscountedPrice,
  normalizeDiscountPercent,
  resolveDiscountPercent,
  resolveDiscountedPrice,
  resolveRetailPrice,
} from '../../utils/pricing';

const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const error = ref('');
const showForm = ref(false);
const showDeleteConfirm = ref(false);
const saving = ref(false);
const deleting = ref(false);
const editing = ref(false);
const selectedProductId = ref(null);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

const buildEmptyForm = () => ({ name: '', category_id: '', unit_of_measure: '', retail_price: null, discount_percent: 0 });

const form = ref(buildEmptyForm());

const normalizeUnitOfMeasure = (value) => {
  if (value === 'by kg') return 'kg';
  if (value === 'per pack' || value === 'Per Pack') return 'Per pack';
  return value || '';
};

const selectedCategory = ref('');
const sortBy = ref('name');
const sortDirection = ref('asc');
const categoryOptions = computed(() => [{
  value: '',
  label: 'All Categories',
}, ...categories.value.map((category) => ({
  value: String(category.id),
  label: category.name,
}))]);
const productCategoryOptions = computed(() => categories.value.map((category) => ({
  value: String(category.id),
  label: category.name,
})));

const filteredProducts = computed(() => {
  if (!selectedCategory.value) return products.value;
  return products.value.filter((product) => String(product.category_id || '') === String(selectedCategory.value));
});

const selectedProduct = computed(() => products.value.find(p => p.id === selectedProductId.value) || null);
const discountedPreview = computed(() => calculateDiscountedPrice(form.value.retail_price, form.value.discount_percent));

const selectProduct = (p) => { selectedProductId.value = selectedProductId.value === p.id ? null : p.id; };

const formatPrice = (val) => val != null ? '\u20B1' + Number(val).toLocaleString() : '-';
const formatPercent = (val) => `${Number(val || 0).toFixed(2)}%`;

const fetchProducts = async (page = 1) => {
  loading.value = true; error.value = '';
  try {
    const res = await api.get('/products', {
      params: {
        page,
        per_page: pagination.value.per_page,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
      },
    });
    if (res.data.success) {
      products.value = res.data.data;
      pagination.value = res.data.pagination || pagination.value;
      if (selectedProductId.value && !products.value.some(p => p.id === selectedProductId.value)) {
        selectedProductId.value = null;
      }
    } else error.value = res.data.message || 'Failed to fetch products';
  } catch (e) { error.value = e.response?.data?.message || 'Failed to fetch products'; }
  loading.value = false;
};

const fetchCategories = async () => {
  try {
    const res = await api.get('/categories', { params: { per_page: 250 } });
    if (res.data?.success) {
      categories.value = res.data.data || [];
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to fetch categories';
  }
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  fetchProducts(page);
};

const closeForm = () => { showForm.value = false; editing.value = false; form.value = buildEmptyForm(); };

const openCreateForm = () => {
  editing.value = false;
  form.value = buildEmptyForm();
  showForm.value = true;
};

const openEditSelected = () => {
  if (!selectedProduct.value) return;
  const p = selectedProduct.value;
  editing.value = true;
  showForm.value = true;
  form.value = {
    name: p.name,
    category_id: String(p.category_id || categories.value.find((category) => category.name === p.category)?.id || ''),
    unit_of_measure: normalizeUnitOfMeasure(p.unit_of_measure),
    retail_price: resolveRetailPrice(p),
    discount_percent: resolveDiscountPercent(p),
  };
  form.value.id = p.id;
};

const save = async () => {
  if (!form.value.name || !form.value.category_id || !form.value.unit_of_measure) {
    alert('Please fill in all required fields: Name, Category, and Unit of Measure');
    return;
  }

  saving.value = true;
  const payload = {
    name: form.value.name,
    category_id: Number(form.value.category_id),
    unit_of_measure: normalizeUnitOfMeasure(form.value.unit_of_measure),
    retail_price: Number(form.value.retail_price ?? 0),
    discount_percent: normalizeDiscountPercent(form.value.discount_percent),
  };
  try {
    let res;
    if (editing.value) res = await api.put('/products/' + form.value.id, payload);
    else res = await api.post('/products', payload);
    if (res.data.success) { await fetchProducts(pagination.value.current_page); closeForm(); }
    else alert(res.data.message || 'Failed');
  } catch (e) { alert(e.response?.data?.message || 'Failed'); }
  saving.value = false;
};

const openDeleteSelected = () => {
  if (!selectedProduct.value) return;
  showDeleteConfirm.value = true;
};

const confirmDelete = async () => {
  if (!selectedProduct.value) return;
  deleting.value = true;
  try {
    const res = await api.delete('/products/' + selectedProduct.value.id);
    if (res.data.success) {
      showDeleteConfirm.value = false;
      selectedProductId.value = null;
      const targetPage = products.value.length === 1 && pagination.value.current_page > 1
        ? pagination.value.current_page - 1 : pagination.value.current_page;
      await fetchProducts(targetPage);
    } else alert(res.data.message || 'Failed');
  } catch (e) { alert(e.response?.data?.message || 'Failed'); }
  deleting.value = false;
};

onMounted(async () => {
  await fetchCategories();
  await fetchProducts(1);
});
</script>

<style scoped>
.products-container { max-width: 1400px; margin: 0 auto; padding: 20px 0; animation: fadeIn 0.3s ease-in; }
.header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.header-section h1 { margin: 0; color: #0a1d37; font-size: 28px; font-weight: 700; }
.page-summary { margin: 8px 0 0; color: #607089; font-size: 14px; }
.actions-bar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; padding: 14px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.selection-state { display: flex; flex-direction: column; gap: 4px; }
.selection-state strong { color: #102746; font-size: 15px; }
.selection-state span { color: #607089; font-size: 13px; }
.toolbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.filter-section { padding: 14px 16px; border-bottom: 1px solid #eef1f4; }
.table-container { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background-color: #f9fafb; }
.data-table th { padding: 14px 16px; text-align: left; font-weight: 600; color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e0e0e0; }
.data-table td { padding: 14px 16px; border-bottom: 1px solid #eef1f4; color: #333; }
.data-table tbody tr { cursor: pointer; transition: background-color 0.15s ease; }
.data-table tbody tr:hover { background-color: #f9fafb; }
.selected-row { background-color: #fff7ed !important; border-left: 3px solid #e57c2a; }
.select-column { width: 52px; text-align: center; }
.select-column input { width: 16px; height: 16px; cursor: pointer; }
.no-data { text-align: center; color: #999; padding: 40px 16px; }
.pagination { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding: 14px 18px; border-top: 1px solid #edf0f4; }
.page-info { font-size: 13px; color: #4a5565; }
.btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s ease; }
.btn-primary { background-color: #e57c2a; color: white; }
.btn-primary:hover { background-color: #d16a22; }
.btn-secondary { background-color: #6c757d; color: white; }
.btn-secondary:hover { background-color: #5a6268; }
.btn-danger { background-color: #dc3545; color: white; }
.btn-danger:hover { background-color: #c82333; }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }
.modal-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); z-index: 1000; }
.modal-content { background: white; padding: 24px; border-radius: 12px; width: 480px; max-width: 90%; max-height: 90vh; overflow-y: auto; }
.small-modal { max-width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e0e0e0; }
.modal-header h2 { margin: 0; color: #0a1d37; font-size: 18px; }
.btn-close { background: none; border: none; font-size: 24px; color: #999; cursor: pointer; }
.btn-close:hover { color: #333; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #333; font-size: 14px; }
.form-group input,
.form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; font-family: inherit; background: #fff; }
.form-group input:focus,
.form-group select:focus { outline: none; border-color: #e57c2a; box-shadow: 0 0 0 3px rgba(229,124,42,0.1); }
.choice-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.choice-option { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 2px solid #e9ecef; border-radius: 10px; cursor: pointer; transition: all 0.2s ease; background: #fff; }
.choice-option.active { border-color: #e57c2a; background: #fff7ed; box-shadow: 0 0 0 3px rgba(229,124,42,0.08); }
.choice-option input { width: 16px; height: 16px; margin: 0; accent-color: #e57c2a; }
.choice-option span { font-weight: 600; color: #102746; }
.pricing-preview { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-radius: 10px; background: #fff7ed; color: #9a3412; font-weight: 600; margin-bottom: 8px; }
.pricing-preview strong { font-size: 18px; color: #7c2d12; }
.warning { color: #dc3545; font-size: 13px; margin-top: 4px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
