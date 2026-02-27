<template>
  <div class="products-container">
    <div class="header-section">
      <div class="header-left">
        <button @click="$router.push('/')" class="btn btn-back">← Back to Dashboard</button>
        <h1>Products</h1>
      </div>
      <button @click="showForm = true" class="btn btn-primary">Add Products</button>
    </div>

    <div v-if="loading" class="loading-state">Loading products...</div>
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
    </div>

    <div v-else class="table-container">
      <div class="filter-section">
        <label>Filter Category: 
          <select v-model="selectedCategory">
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </label>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Category</th>
            <th>Unit</th>
            <th>Retail</th>
            <th>Wholesale</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredProducts.length === 0">
            <td colspan="6" class="no-data">No products yet. Use the Add Products button to add one.
              <div style="margin-top:8px;"><button @click="showForm = true" class="btn btn-primary">Add Products</button></div>
            </td>
          </tr>
          <tr v-for="p in filteredProducts" :key="p.id">
            <td>{{ p.name }}</td>
            <td>{{ p.category }}</td>
            <td>{{ p.unit_of_measure }}</td>
            <td>₱{{ (p.pricing && p.pricing[0]) ? Number(p.pricing[0].retail_price).toLocaleString() : '-' }}</td>
            <td>₱{{ (p.pricing && p.pricing[0]) ? Number(p.pricing[0].wholesale_price).toLocaleString() : '-' }}</td>
            <td>
              <button class="btn-small" @click="editProduct(p)">Edit</button>
              <button class="btn-small btn-danger" @click="deleteProduct(p)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showForm" class="modal-overlay" @click="closeForm">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editing ? 'Edit Product' : 'Add Product' }}</h2>
          <button @click="closeForm" class="btn-close">×</button>
        </div>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name *</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-group">
            <label>Category *</label>
            <input v-model="form.category" required />
          </div>
          <div class="form-group">
            <label>Unit of Measure *</label>
            <input v-model="form.unit_of_measure" required />
          </div>
          <div class="form-group">
            <label>Retail Price</label>
            <input v-model.number="form.retail_price" type="number" min="0" />
          </div>
          <div class="form-group">
            <label>Wholesale Price</label>
            <input v-model.number="form.wholesale_price" type="number" min="0" />
          </div>

          <div class="modal-actions">
            <button type="button" @click="closeForm" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary">{{ saving ? 'Saving...' : (editing ? 'Update' : 'Create') }}</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../api';

const products = ref([]);
const loading = ref(false);
const error = ref('');
const showForm = ref(false);
const saving = ref(false);
const editing = ref(false);

const form = ref({
  name: '',
  category: '',
  unit_of_measure: '',
  retail_price: null,
  wholesale_price: null,
});

// New: filter state and categories list
const selectedCategory = ref('All');
const categories = ['All', 'Tuna', 'Pompano', 'Seabass', 'Salmon', 'Squid', 'Shell'];

const filteredProducts = computed(() => {
  if (selectedCategory.value === 'All') return products.value;
  return products.value.filter(p => p.category === selectedCategory.value);
});

const fetch = async () => {
  loading.value = true; error.value = '';
  try {
    const res = await api.get('/products');
    if (res.data.success) products.value = res.data.data;
    else error.value = res.data.message || 'Failed to fetch products';
  } catch (e) { error.value = e.response?.data?.message || 'Failed to fetch products'; }
  loading.value = false;
};

const closeForm = () => { showForm.value = false; editing.value = false; form.value = { name: '', category: '', unit_of_measure: '', retail_price: null, wholesale_price: null }; };

const editProduct = (p) => { editing.value = true; showForm.value = true; form.value = { name: p.name, category: p.category, unit_of_measure: p.unit_of_measure, retail_price: p.pricing?.[0]?.retail_price ?? null, wholesale_price: p.pricing?.[0]?.wholesale_price ?? null }; form.value.id = p.id; };

const save = async () => {
  saving.value = true;
  const payload = { name: form.value.name, category: form.value.category, unit_of_measure: form.value.unit_of_measure, retail_price: form.value.retail_price, wholesale_price: form.value.wholesale_price };
  try {
    let res;
    if (editing.value) res = await api.put(`/products/${form.value.id}`, payload);
    else res = await api.post('/products', payload);
    if (res.data.success) { await fetch(); closeForm(); }
    else alert(res.data.message || 'Failed');
  } catch (e) { alert(e.response?.data?.message || 'Failed'); }
  saving.value = false;
};

const deleteProduct = async (p) => {
  if (!confirm('Delete this product?')) return;
  try { const res = await api.delete(`/products/${p.id}`); if (res.data.success) await fetch(); else alert(res.data.message || 'Failed'); } catch (e) { alert(e.response?.data?.message || 'Failed'); }
};

// Keep products empty on initial load; we'll not fetch automatically so
// the list stays empty until items are added manually.
// onMounted(fetch);
</script>

<style scoped>
/* reuse simple styles from customers view for brevity */
.products-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
.header-section { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.data-table { width:100%; border-collapse:collapse; }
.data-table th, .data-table td { padding:12px; border-bottom:1px solid #eee; }
.modal-overlay { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.6); }
.modal-content { background:white; padding:20px; border-radius:8px; width:480px; }
.form-group { margin-bottom:12px; }
.modal-actions { display:flex; justify-content:flex-end; gap:8px; }
.btn { padding:8px 14px; border-radius:6px; }
.btn-primary { background:#e57c2a; color:white; }
.btn-secondary { background:#6c757d; color:white; }
.btn-danger { background:#dc3545; color:white; }
.btn-small { padding:6px 10px; }
</style>
