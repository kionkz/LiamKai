<template>
  <div class="categories-container">
    <div class="actions-bar">
      <div class="selection-state">
        <strong>{{ selectedCategory ? selectedCategory.name : 'No category selected' }}</strong>
        <span>{{ selectedCategory ? 'Category actions are enabled.' : 'Select a category row to edit or delete it.' }}</span>
      </div>
      <div class="toolbar-actions">
        <button class="btn btn-primary" @click="openCreateModal">New Category</button>
        <button class="btn btn-secondary" :disabled="!selectedCategory" @click="openEditModal">Edit</button>
        <button class="btn btn-danger" :disabled="!selectedCategory" @click="openDeleteModal">Delete</button>
      </div>
    </div>

    <div class="filters-bar">
      <input v-model="searchQuery" type="text" placeholder="Search category name or description..." @keyup.enter="applyFilter" />
      <button class="btn btn-secondary" @click="applyFilter">Search</button>
      <button class="btn btn-secondary btn-ghost" :disabled="!searchQuery" @click="clearFilter">Clear</button>
    </div>

    <p v-if="successMessage" class="state-message success">{{ successMessage }}</p>
    <p v-if="errorMessage" class="state-message error">{{ errorMessage }}</p>

    <div class="table-container">
      <div v-if="loading" class="table-state">Loading categories...</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th class="select-column"></th>
            <th>Category Name</th>
            <th>Description</th>
            <th>Products</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredCategories.length === 0">
            <td colspan="4" class="no-data">No categories found.</td>
          </tr>
          <tr
            v-for="category in filteredCategories"
            :key="category.id"
            :class="{ 'selected-row': selectedCategoryId === category.id }"
            @click="selectCategory(category)"
          >
            <td class="select-column" @click.stop>
              <input type="checkbox" :checked="selectedCategoryId === category.id" @change="selectCategory(category)" />
            </td>
            <td>{{ category.name }}</td>
            <td>{{ category.description || 'No description' }}</td>
            <td>{{ category.products_count ?? 0 }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showForm" class="modal-overlay" @click="closeForm">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingCategory ? 'Edit Category' : 'Add Category' }}</h2>
          <button class="btn-close" @click="closeForm">&times;</button>
        </div>
        <form @submit.prevent="saveCategory">
          <div class="form-group">
            <label>Category Name *</label>
            <input v-model="form.name" type="text" required maxlength="100" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="4" maxlength="1000"></textarea>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" @click="closeForm">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving...' : (editingCategory ? 'Update' : 'Create') }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showDeleteConfirm" class="modal-overlay" @click="showDeleteConfirm = false">
      <div class="modal-content small-modal" @click.stop>
        <h3>Delete Category</h3>
        <p>Delete {{ selectedCategory?.name }}?</p>
        <p class="warning">Categories assigned to products cannot be deleted.</p>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">Cancel</button>
          <button class="btn btn-danger" :disabled="deleting" @click="deleteCategory">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api';

const categories = ref([]);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const searchQuery = ref('');
const selectedCategoryId = ref(null);
const showForm = ref(false);
const showDeleteConfirm = ref(false);
const editingCategory = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const form = ref({
  id: null,
  name: '',
  description: '',
});

const selectedCategory = computed(() => categories.value.find((category) => category.id === selectedCategoryId.value) || null);

const filteredCategories = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase();
  if (!keyword) {
    return categories.value;
  }

  return categories.value.filter((category) => {
    const haystack = `${category.name} ${category.description || ''}`.toLowerCase();
    return haystack.includes(keyword);
  });
});

const fetchCategories = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const response = await api.get('/categories', { params: { per_page: 250 } });
    if (response.data?.success) {
      categories.value = response.data.data || [];
      if (selectedCategoryId.value && !categories.value.some((category) => category.id === selectedCategoryId.value)) {
        selectedCategoryId.value = null;
      }
      return;
    }

    errorMessage.value = response.data?.message || 'Failed to load categories.';
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to load categories.';
  } finally {
    loading.value = false;
  }
};

const applyFilter = () => {
  searchQuery.value = searchQuery.value.trim();
};

const clearFilter = () => {
  searchQuery.value = '';
};

const selectCategory = (category) => {
  selectedCategoryId.value = selectedCategoryId.value === category.id ? null : category.id;
};

const openCreateModal = () => {
  editingCategory.value = false;
  form.value = { id: null, name: '', description: '' };
  showForm.value = true;
};

const openEditModal = () => {
  if (!selectedCategory.value) {
    return;
  }

  editingCategory.value = true;
  form.value = {
    id: selectedCategory.value.id,
    name: selectedCategory.value.name,
    description: selectedCategory.value.description || '',
  };
  showForm.value = true;
};

const closeForm = () => {
  showForm.value = false;
  editingCategory.value = false;
  form.value = { id: null, name: '', description: '' };
};

const saveCategory = async () => {
  saving.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const payload = {
      name: form.value.name.trim(),
      description: form.value.description?.trim() || null,
    };

    const response = editingCategory.value
      ? await api.put(`/categories/${form.value.id}`, payload)
      : await api.post('/categories', payload);

    if (response.data?.success) {
      successMessage.value = response.data.message || 'Category saved successfully.';
      await fetchCategories();
      closeForm();
      return;
    }

    errorMessage.value = response.data?.message || 'Failed to save category.';
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to save category.';
  } finally {
    saving.value = false;
  }
};

const openDeleteModal = () => {
  if (!selectedCategory.value) {
    return;
  }

  showDeleteConfirm.value = true;
};

const deleteCategory = async () => {
  if (!selectedCategory.value) {
    return;
  }

  deleting.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const response = await api.delete(`/categories/${selectedCategory.value.id}`);
    if (response.data?.success) {
      successMessage.value = response.data.message || 'Category deleted successfully.';
      selectedCategoryId.value = null;
      showDeleteConfirm.value = false;
      await fetchCategories();
      return;
    }

    errorMessage.value = response.data?.message || 'Failed to delete category.';
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to delete category.';
  } finally {
    deleting.value = false;
  }
};

onMounted(() => {
  fetchCategories();
});
</script>

<style scoped>
.categories-container { max-width: 1400px; margin: 0 auto; padding: 20px 0; }
.actions-bar, .filters-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; padding: 14px; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.selection-state { display: flex; flex-direction: column; gap: 4px; }
.selection-state strong { color: #102746; font-size: 15px; }
.selection-state span { color: #607089; font-size: 13px; }
.toolbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.filters-bar input { flex: 1; min-width: 240px; padding: 10px 12px; border: 2px solid #e9ecef; border-radius: 8px; }
.table-container { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 14px 16px; text-align: left; font-weight: 600; color: #666; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #e0e0e0; background: #f9fafb; }
.data-table td { padding: 14px 16px; border-bottom: 1px solid #eef1f4; }
.data-table tbody tr:hover { background: #f9fafb; cursor: pointer; }
.selected-row { background: #fff7ed !important; }
.select-column { width: 52px; text-align: center; }
.select-column input { width: 16px; height: 16px; }
.table-state, .no-data { padding: 32px 16px; text-align: center; color: #667085; }
.state-message { margin-bottom: 14px; padding: 12px 14px; border-radius: 10px; font-size: 14px; }
.state-message.success { background: #ecfdf3; color: #027a48; border: 1px solid #abefc6; }
.state-message.error { background: #fff1f2; color: #b42318; border: 1px solid #fecdca; }
.btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-primary { background: #e57c2a; color: #fff; }
.btn-secondary { background: #6c757d; color: #fff; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-ghost { background: #f1f3f5; color: #25303d; }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }
.modal-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); z-index: 1000; }
.modal-content { background: white; padding: 24px; border-radius: 12px; width: 480px; max-width: 90%; max-height: 90vh; overflow-y: auto; }
.small-modal { max-width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e0e0e0; }
.modal-header h2 { margin: 0; color: #0a1d37; font-size: 18px; }
.btn-close { background: none; border: none; font-size: 24px; color: #999; cursor: pointer; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #333; font-size: 14px; }
.form-group input, .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; font-family: inherit; }
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: #e57c2a; box-shadow: 0 0 0 3px rgba(229,124,42,0.1); }
.warning { color: #dc3545; font-size: 13px; margin-top: 4px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
</style>