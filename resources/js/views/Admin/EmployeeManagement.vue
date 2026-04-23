<template>
  <div class="employees-container">
    <div class="actions-bar">
      <div class="selection-state">
        <strong>{{ selectedEmployee ? selectedEmployee.name : 'No employee selected' }}</strong>
        <span>{{ selectedEmployee ? 'Toolbar actions are enabled for the selected record.' : 'Select a row to edit or change status.' }}</span>
      </div>
      <div class="toolbar-actions">
        <button @click="openCreateModal" class="btn btn-primary">New Employee</button>
        <button @click="openEditSelected" class="btn btn-secondary" :disabled="!selectedEmployee">Edit</button>
        <button @click="openCreateAccountModal" class="btn btn-secondary" :disabled="!selectedEmployee">Manage Account</button>
        <button @click="toggleSelectedStatus" class="btn btn-secondary" :disabled="!selectedEmployee">
          {{ selectedEmployee?.status === 'active' ? 'Deactivate' : 'Activate' }}
        </button>
      </div>
    </div>

    <div class="filters">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search employees by name, email, role, or phone"
        class="search-box"
        @keyup.enter="applyFilters"
      />
      <select v-model="roleFilter" class="filter-select" data-searchable="off" @change="applyFilters">
        <option value="">All Roles</option>
        <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
      </select>
      <select v-model="statusFilter" class="filter-select" data-searchable="off" @change="applyFilters">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <select v-model="sortBy" class="filter-select" data-searchable="off" @change="applyFilters">
        <option value="name">Name</option>
        <option value="email">Email</option>
        <option value="role">Role</option>
        <option value="phone">Phone</option>
        <option value="status">Status</option>
      </select>
      <select v-model="sortDirection" class="filter-select" data-searchable="off" @change="applyFilters">
        <option value="asc">Ascending</option>
        <option value="desc">Descending</option>
      </select>
      <button class="btn btn-secondary" @click="applyFilters">Search</button>
      <button class="btn btn-secondary btn-ghost" @click="clearFilters" :disabled="!hasFilters">Clear</button>
    </div>

    <p v-if="loading" class="state-message">Loading employees...</p>
    <p v-if="errorMessage" class="state-message error">{{ errorMessage }}</p>
    <p v-if="successMessage" class="state-message success">{{ successMessage }}</p>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th class="select-column"></th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Account</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="employees.length === 0">
            <td colspan="7" class="no-data">No employees found.</td>
          </tr>
          <tr
            v-for="emp in employees"
            :key="emp.id"
            @click="toggleSelection(emp.id)"
            :class="{ 'selected-row': selectedEmployeeId === emp.id }"
          >
            <td class="select-column" @click.stop>
              <input type="checkbox" :checked="selectedEmployeeId === emp.id" @change="toggleSelection(emp.id)" />
            </td>
            <td class="name-cell">{{ emp.name }}</td>
            <td>{{ emp.email }}</td>
            <td>
              <span class="role-badge" :class="emp.role">{{ capitalize(emp.role) }}</span>
            </td>
            <td>{{ emp.phone || '-' }}</td>
            <td>
              <span class="status-badge" :class="emp.status">{{ emp.status === 'active' ? 'Active' : 'Inactive' }}</span>
            </td>
            <td>
              <span v-if="emp.user" class="account-badge" :class="emp.user.account_status">
                {{ emp.user.account_status === 'active' ? '✓ Active' : '✗ Inactive' }}
              </span>
              <span v-else class="account-badge none">No Account</span>
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

    <div v-if="showAddModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingEmployee ? 'Edit Employee' : 'New Employee' }}</h2>
          <button @click="closeModal" class="btn-close">&times;</button>
        </div>

        <form @submit.prevent="saveEmployee" class="modal-form">
          <div class="form-group">
            <label>Full Name *</label>
            <input v-model="formData.name" type="text" required />
          </div>
          <div class="form-group">
            <label>Email *</label>
            <input v-model="formData.email" type="email" required />
          </div>
          <div class="form-group">
            <label>Role *</label>
            <select v-model="formData.role" required data-searchable="off">
              <option value="">Select Role</option>
              <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input v-model="formData.phone" type="text" inputmode="numeric" maxlength="11" @input="normalizeEmployeePhone" />
          </div>
          <div class="form-group">
            <label>Address</label>
            <input v-model="formData.address" type="text" />
          </div>
          <div class="form-group inline-check">
            <label><input v-model="formData.active" type="checkbox" /> Active</label>
          </div>
          <div class="modal-actions">
            <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button type="submit" :disabled="saving" class="btn btn-primary">
              {{ saving ? 'Saving...' : (editingEmployee ? 'Update Employee' : 'Create Employee') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Account Management Modal -->
    <div v-if="showAccountModal" class="modal-overlay" @click.self="showAccountModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Account — {{ selectedEmployee?.name }}</h2>
          <button @click="showAccountModal = false" class="btn-close">&times;</button>
        </div>

        <!-- Employee has no account yet -->
        <div v-if="!selectedEmployee?.user" class="account-section">
          <p class="account-info">This employee does not have a system account yet. Create one below.</p>
          <form @submit.prevent="createAccount" class="modal-form">
            <div class="form-group">
              <label>Username *</label>
              <input v-model="accountForm.username" type="text" required autocomplete="off" />
            </div>
            <div class="form-group">
              <label>Password *</label>
              <input v-model="accountForm.password" type="password" required autocomplete="new-password" />
            </div>
            <div class="form-group">
              <label>Confirm Password *</label>
              <input v-model="accountForm.password_confirmation" type="password" required autocomplete="new-password" />
            </div>
            <p v-if="accountError" class="state-message error" style="margin-top:8px;">{{ accountError }}</p>
            <div class="modal-actions">
              <button type="button" @click="showAccountModal = false" class="btn btn-secondary">Cancel</button>
              <button type="submit" :disabled="accountSaving" class="btn btn-primary">
                {{ accountSaving ? 'Creating...' : 'Create Account' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Employee already has an account -->
        <div v-else class="account-section">
          <div class="account-detail-row">
            <span class="account-detail-label">Username</span>
            <span class="account-detail-value">{{ selectedEmployee.user.username }}</span>
          </div>
          <div class="account-detail-row">
            <span class="account-detail-label">Role</span>
            <span class="account-detail-value">{{ capitalize(selectedEmployee.user.role) }}</span>
          </div>
          <div class="account-detail-row">
            <span class="account-detail-label">Status</span>
            <span class="account-badge" :class="selectedEmployee.user.account_status">
              {{ selectedEmployee.user.account_status === 'active' ? '✓ Active' : '✗ Inactive' }}
            </span>
          </div>
          <p v-if="accountError" class="state-message error" style="margin-top:8px;">{{ accountError }}</p>
          <form v-if="showResetCredentialsForm" @submit.prevent="resetAccountCredentials" class="modal-form reset-credentials-form">
            <div class="form-group">
              <label>New Username *</label>
              <input v-model="resetAccountForm.username" type="text" required autocomplete="off" />
            </div>
            <div class="form-group">
              <label>New Temporary Password *</label>
              <input v-model="resetAccountForm.password" type="password" required minlength="8" autocomplete="new-password" />
            </div>
            <div class="form-group">
              <label>Confirm New Password *</label>
              <input v-model="resetAccountForm.password_confirmation" type="password" required minlength="8" autocomplete="new-password" />
            </div>
            <div class="modal-actions compact-actions">
              <button type="button" @click="showResetCredentialsForm = false" class="btn btn-secondary">Cancel Reset</button>
              <button type="submit" :disabled="accountSaving" class="btn btn-primary">
                {{ accountSaving ? 'Resetting...' : 'Send New Credentials' }}
              </button>
            </div>
          </form>
          <div class="modal-actions">
            <button @click="showAccountModal = false" class="btn btn-secondary">Close</button>
            <button @click="openResetCredentialsForm" :disabled="accountSaving" class="btn btn-secondary">
              Reset Credentials
            </button>
            <button @click="toggleAccountStatus" :disabled="accountSaving" class="btn btn-secondary">
              {{ accountSaving ? 'Updating...' : (selectedEmployee.user.account_status === 'active' ? 'Deactivate Account' : 'Activate Account') }}
            </button>
            <button @click="revokeAccount" :disabled="accountSaving" class="btn btn-danger">
              {{ accountSaving ? 'Revoking...' : 'Revoke Account' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api';

const searchQuery = ref('');
const roleFilter = ref('');
const statusFilter = ref('');
const sortBy = ref('name');
const sortDirection = ref('asc');
const showAddModal = ref(false);
const showAccountModal = ref(false);
const editingEmployee = ref(null);
const selectedEmployeeId = ref(null);
const loading = ref(false);
const saving = ref(false);
const accountSaving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const accountError = ref('');

const accountForm = ref({ username: '', password: '', password_confirmation: '' });
const resetAccountForm = ref({ username: '', password: '', password_confirmation: '' });
const showResetCredentialsForm = ref(false);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const employees = ref([]);

const roleOptions = [
  { value: 'admin', label: 'Admin' },
  { value: 'sales', label: 'Sales' },
  { value: 'delivery', label: 'Delivery' },
  { value: 'inventory', label: 'Inventory' },
  { value: 'purchasing', label: 'Purchasing' },
];

const formData = ref({
  name: '',
  email: '',
  role: '',
  phone: '',
  address: '',
  active: true,
});

const selectedEmployee = computed(() => employees.value.find((emp) => emp.id === selectedEmployeeId.value) || null);
const hasFilters = computed(() => !!(searchQuery.value || roleFilter.value || statusFilter.value));

const capitalize = (str) => str ? str.charAt(0).toUpperCase() + str.slice(1) : '';

const clearMessages = () => {
  errorMessage.value = '';
  successMessage.value = '';
};

const resetForm = () => {
  formData.value = {
    name: '',
    email: '',
    role: '',
    phone: '',
    address: '',
    active: true,
  };
};

const normalizeEmployeePhone = () => {
  formData.value.phone = String(formData.value.phone || '').replace(/\D/g, '').slice(0, 11);
};

const toggleSelection = (employeeId) => {
  selectedEmployeeId.value = selectedEmployeeId.value === employeeId ? null : employeeId;
};

const openCreateModal = () => {
  editingEmployee.value = null;
  resetForm();
  showAddModal.value = true;
};

const closeModal = () => {
  showAddModal.value = false;
  editingEmployee.value = null;
  resetForm();
};

const loadEmployees = async (page = 1) => {
  loading.value = true;
  clearMessages();

  try {
    const response = await api.get('/employees', {
      params: {
        page,
        per_page: pagination.value.per_page,
        search: searchQuery.value.trim(),
        role: roleFilter.value,
        status: statusFilter.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
      },
    });

    if (!response.data?.success) {
      errorMessage.value = response.data?.message || 'Failed to load employees';
      return;
    }

    employees.value = response.data.data || [];
    pagination.value = response.data.pagination || pagination.value;

    if (selectedEmployeeId.value && !employees.value.some((employee) => employee.id === selectedEmployeeId.value)) {
      selectedEmployeeId.value = null;
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to load employees';
  } finally {
    loading.value = false;
  }
};

const applyFilters = () => {
  selectedEmployeeId.value = null;
  loadEmployees(1);
};

const clearFilters = () => {
  searchQuery.value = '';
  sortBy.value = 'name';
  sortDirection.value = 'asc';
  roleFilter.value = '';
  statusFilter.value = '';
  applyFilters();
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  loadEmployees(page);
};

const openEditSelected = () => {
  if (!selectedEmployee.value) return;

  editingEmployee.value = selectedEmployee.value;
  formData.value = {
    name: selectedEmployee.value.name,
    email: selectedEmployee.value.email,
    role: selectedEmployee.value.role,
    phone: selectedEmployee.value.phone || '',
    address: selectedEmployee.value.address || '',
    active: selectedEmployee.value.status === 'active',
  };
  showAddModal.value = true;
};

const saveEmployee = async () => {
  saving.value = true;
  clearMessages();

  const payload = {
    name: formData.value.name,
    email: formData.value.email,
    role: formData.value.role,
    phone: formData.value.phone || null,
    address: formData.value.address || null,
    status: formData.value.active ? 'active' : 'inactive',
  };

  try {
    const response = editingEmployee.value
      ? await api.put(`/employees/${editingEmployee.value.id}`, payload)
      : await api.post('/employees', payload);

    if (!response.data?.success) {
      errorMessage.value = response.data?.message || 'Failed to save employee';
      return;
    }

    successMessage.value = editingEmployee.value ? 'Employee updated successfully.' : 'Employee created successfully.';
    closeModal();
    await loadEmployees(pagination.value.current_page);
  } catch (error) {
    const validationErrors = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(' ')
      : null;
    errorMessage.value = validationErrors || error.response?.data?.message || 'Failed to save employee';
  } finally {
    saving.value = false;
  }
};

const toggleSelectedStatus = async () => {
  if (!selectedEmployee.value) return;

  clearMessages();
  const nextStatus = selectedEmployee.value.status === 'active' ? 'inactive' : 'active';

  try {
    const response = await api.put(`/employees/${selectedEmployee.value.id}`, { status: nextStatus });
    if (!response.data?.success) {
      errorMessage.value = response.data?.message || 'Failed to update employee status';
      return;
    }

    successMessage.value = `Employee ${nextStatus === 'active' ? 'activated' : 'deactivated'} successfully.`;
    await loadEmployees(pagination.value.current_page);
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to update employee status';
  }
};

const openCreateAccountModal = () => {
  if (!selectedEmployee.value) return;
  accountError.value = '';
  accountForm.value = { username: '', password: '', password_confirmation: '' };
  resetAccountForm.value = { username: selectedEmployee.value.user?.username || '', password: '', password_confirmation: '' };
  showResetCredentialsForm.value = false;
  showAccountModal.value = true;
};

const createAccount = async () => {
  accountError.value = '';
  if (accountForm.value.password !== accountForm.value.password_confirmation) {
    accountError.value = 'Passwords do not match.';
    return;
  }
  accountSaving.value = true;
  try {
    const response = await api.post(`/employees/${selectedEmployee.value.id}/account`, accountForm.value);
    if (!response.data?.success) {
      accountError.value = response.data?.message || 'Failed to create account.';
      return;
    }
    successMessage.value = response.data.message;
    // If email failed, the message already contains the warning — highlight it
    if (response.data.email_sent === false) {
      errorMessage.value = `⚠ Email could not be sent to ${selectedEmployee.value.email}. Please share the credentials manually.`;
    }
    showAccountModal.value = false;
    await loadEmployees(pagination.value.current_page);
  } catch (error) {
    const errs = error.response?.data?.errors;
    accountError.value = errs ? Object.values(errs).flat().join(' ') : (error.response?.data?.message || 'Failed to create account.');
  } finally {
    accountSaving.value = false;
  }
};

const openResetCredentialsForm = () => {
  if (!selectedEmployee.value?.user) return;
  accountError.value = '';
  resetAccountForm.value = {
    username: selectedEmployee.value.user.username || '',
    password: '',
    password_confirmation: '',
  };
  showResetCredentialsForm.value = true;
};

const resetAccountCredentials = async () => {
  if (!selectedEmployee.value?.user) return;
  accountError.value = '';

  if (resetAccountForm.value.password !== resetAccountForm.value.password_confirmation) {
    accountError.value = 'Passwords do not match.';
    return;
  }

  accountSaving.value = true;
  try {
    const response = await api.post(`/employees/${selectedEmployee.value.id}/account/reset-credentials`, resetAccountForm.value);
    if (!response.data?.success) {
      accountError.value = response.data?.message || 'Failed to reset credentials.';
      return;
    }

    successMessage.value = response.data.message;
    if (response.data.email_sent === false) {
      errorMessage.value = `⚠ Email could not be sent to ${selectedEmployee.value.email}. Please share the new credentials manually.`;
    }
    showResetCredentialsForm.value = false;
    showAccountModal.value = false;
    await loadEmployees(pagination.value.current_page);
  } catch (error) {
    const errs = error.response?.data?.errors;
    accountError.value = errs ? Object.values(errs).flat().join(' ') : (error.response?.data?.message || 'Failed to reset credentials.');
  } finally {
    accountSaving.value = false;
  }
};

const revokeAccount = async () => {
  if (!selectedEmployee.value || !confirm(`Revoke account for ${selectedEmployee.value.name}? They will no longer be able to log in.`)) return;
  accountSaving.value = true;
  accountError.value = '';
  try {
    const response = await api.delete(`/employees/${selectedEmployee.value.id}/account`);
    if (!response.data?.success) { accountError.value = response.data?.message || 'Failed to revoke account.'; return; }
    successMessage.value = response.data.message;
    showAccountModal.value = false;
    await loadEmployees(pagination.value.current_page);
  } catch (error) {
    accountError.value = error.response?.data?.message || 'Failed to revoke account.';
  } finally {
    accountSaving.value = false;
  }
};

const toggleAccountStatus = async () => {
  if (!selectedEmployee.value) return;
  accountSaving.value = true;
  accountError.value = '';
  try {
    const response = await api.patch(`/employees/${selectedEmployee.value.id}/account/toggle-status`);
    if (!response.data?.success) { accountError.value = response.data?.message || 'Failed to update account status.'; return; }
    successMessage.value = response.data.message;
    showAccountModal.value = false;
    await loadEmployees(pagination.value.current_page);
  } catch (error) {
    accountError.value = error.response?.data?.message || 'Failed to update account status.';
  } finally {
    accountSaving.value = false;
  }
};

onMounted(() => {
  loadEmployees(1);
});
</script>

<style scoped>
.employees-container {
  max-width: 1400px;
  margin: 0 auto;
  animation: fadeIn 0.3s ease-in;
  padding: 20px 0;
}

.header-section {
  margin-bottom: 16px;
}

.header-section h1 {
  margin: 0;
  color: #102746;
  font-size: 30px;
  font-weight: 700;
}

.page-summary {
  margin: 8px 0 0;
  color: #607089;
  font-size: 14px;
}

.actions-bar,
.filters,
.table-container {
  background: #ffffff;
  border: 1px solid #e7ebf2;
  border-radius: 14px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 16px 18px;
  margin-bottom: 16px;
}

.selection-state {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.selection-state strong {
  color: #102746;
  font-size: 15px;
}

.selection-state span {
  color: #607089;
  font-size: 13px;
}

.toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 12px;
}

.search-box,
.filter-select,
.form-group input,
.form-group select {
  width: 100%;
  padding: 11px 14px;
  border: 1px solid #d7deea;
  border-radius: 10px;
  font-size: 14px;
  font-family: inherit;
  background: #fbfcfe;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-box {
  flex: 1 1 320px;
}

.filter-select {
  flex: 0 0 180px;
}

.search-box:focus,
.filter-select:focus,
.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #d97706;
  box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.12);
  background: #ffffff;
}

.state-message {
  margin: 0 0 12px;
  padding: 12px 14px;
  border-radius: 10px;
  font-size: 14px;
}

.state-message.error {
  background: #fef2f2;
  color: #b91c1c;
}

.state-message.success {
  background: #f0fdf4;
  color: #166534;
}

.table-container {
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table thead {
  background: #f8fafc;
}

.data-table th,
.data-table td {
  padding: 14px 16px;
  text-align: left;
  border-bottom: 1px solid #edf2f7;
}

.data-table th {
  color: #516072;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.data-table tbody tr {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.data-table tbody tr:hover {
  background: #fafcff;
}

.selected-row {
  background: #fff7ed !important;
}

.select-column {
  width: 52px;
  text-align: center;
}

.select-column input {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.name-cell {
  font-weight: 600;
  color: #102746;
}

.role-badge,
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.role-badge.admin { background: #eef2ff; color: #4338ca; }
.role-badge.sales { background: #eff6ff; color: #1d4ed8; }
.role-badge.delivery { background: #fff7ed; color: #c2410c; }
.role-badge.inventory { background: #ecfeff; color: #0f766e; }
.role-badge.purchasing { background: #fdf2f8; color: #be185d; }

.status-badge.active { background: #ecfdf3; color: #166534; }
.status-badge.inactive { background: #fef2f2; color: #b91c1c; }

.account-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}
.account-badge.active   { background: #ecfdf3; color: #166534; }
.account-badge.inactive { background: #fef2f2; color: #b91c1c; }
.account-badge.none     { background: #f1f5f9; color: #64748b; }

.account-section {
  padding: 4px 0 8px;
}

.account-info {
  color: #64748b;
  font-size: 14px;
  margin-bottom: 16px;
}

.account-detail-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid #edf2f7;
}

.account-detail-label {
  font-weight: 600;
  color: #334155;
  font-size: 14px;
  min-width: 90px;
}

.account-detail-value {
  color: #102746;
  font-size: 14px;
}

.no-data {
  text-align: center;
  color: #64748b;
  padding: 40px 16px;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 18px;
}

.page-info {
  font-size: 13px;
  color: #64748b;
}

.btn {
  padding: 10px 16px;
  border: 1px solid transparent;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.btn-primary {
  background: #d97706;
  color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
  background: #b45309;
}

.btn-secondary {
  background: #ffffff;
  color: #1f2937;
  border-color: #d7deea;
}

.btn-secondary:hover:not(:disabled) {
  background: #f8fafc;
}

.btn-danger {
  background: #dc2626;
  color: #ffffff;
}

.btn-danger:hover:not(:disabled) {
  background: #b91c1c;
}

.btn-ghost {
  background: #f8fafc;
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-content {
  background: #ffffff;
  border-radius: 16px;
  width: min(560px, 100%);
  max-height: 90vh;
  overflow-y: auto;
  padding: 24px;
}

.small-modal {
  width: min(420px, 100%);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #edf2f7;
}

.modal-header h2 {
  margin: 0;
  color: #102746;
  font-size: 20px;
}

.btn-close {
  background: none;
  border: none;
  font-size: 24px;
  color: #64748b;
  cursor: pointer;
}

.modal-form .form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #334155;
  font-size: 14px;
}

.inline-check label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.warning {
  color: #b91c1c;
  font-size: 13px;
  margin-top: 6px;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 22px;
  padding-top: 16px;
  border-top: 1px solid #edf2f7;
}

@media (max-width: 900px) {
  .actions-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .toolbar-actions {
    width: 100%;
  }
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
