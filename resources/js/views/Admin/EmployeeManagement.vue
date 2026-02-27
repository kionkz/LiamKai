<template>
  <div class="employees-container">
    <div class="header-section">
      <h1>Employee Management</h1>
      <button @click="showAddModal = true" class="btn btn-primary">+ Add Employee</button>
    </div>

    <div class="filters">
      <input v-model="searchQuery" type="text" placeholder="Search employees..." class="search-box" />
      <select v-model="roleFilter" class="filter-select">
        <option value="">All Roles</option>
        <option value="admin">Admin</option>
        <option value="manager">Manager</option>
        <option value="staff">Staff</option>
      </select>
    </div>

    <table class="employees-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Last Login</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="emp in filteredEmployees" :key="emp.id">
          <td class="name">{{ emp.name }}</td>
          <td>{{ emp.email }}</td>
          <td>
            <span class="role-badge" :class="emp.role">{{ emp.role.charAt(0).toUpperCase() + emp.role.slice(1) }}</span>
          </td>
          <td>
            <span class="status-badge" :class="emp.status">{{ emp.status === 'active' ? 'Active' : 'Inactive' }}</span>
          </td>
          <td>{{ formatLastLogin(emp.lastLogin) }}</td>
          <td class="actions">
            <button @click="editEmployee(emp)" class="btn-small">Edit</button>
            <button @click="viewLoginLogs(emp)" class="btn-small">Logs</button>
            <button @click="toggleStatus(emp)" :class="['btn-small', emp.status]">{{ emp.status === 'active' ? 'Deactivate' : 'Activate' }}</button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Add/Edit Employee Modal -->
    <div v-if="showAddModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ editingEmployee ? 'Edit Employee' : 'Add New Employee' }}</h2>
          <button @click="closeModal" class="close-btn">×</button>
        </div>

        <form @submit.prevent="saveEmployee" class="employee-form">
          <div class="form-group">
            <label>Full Name</label>
            <input v-model="formData.name" type="text" required />
          </div>

          <div class="form-group">
            <label>Email</label>
            <input v-model="formData.email" type="email" required />
          </div>

          <div class="form-group">
            <label>Role</label>
            <select v-model="formData.role" required>
              <option value="">Select Role</option>
              <option value="admin">Admin</option>
              <option value="manager">Manager</option>
              <option value="staff">Staff</option>
            </select>
          </div>

          <div v-if="!editingEmployee" class="form-group">
            <label>Password</label>
            <input v-model="formData.password" type="password" placeholder="Temporary password" required />
          </div>

          <div class="form-group">
            <label>
              <input v-model="formData.active" type="checkbox" />
              Active
            </label>
          </div>

          <div class="modal-footer">
            <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary">{{ editingEmployee ? 'Update' : 'Add' }} Employee</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Login Logs Modal -->
    <div v-if="showLogsModal" class="modal-overlay" @click.self="closeLogsModal">
      <div class="modal-content modal-large">
        <div class="modal-header">
          <h2>Login History - {{ selectedEmployee?.name }}</h2>
          <button @click="closeLogsModal" class="close-btn">×</button>
        </div>

        <div class="modal-body">
          <table class="logs-table">
            <thead>
              <tr>
                <th>Date & Time</th>
                <th>IP Address</th>
                <th>Device</th>
                <th>Location</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(log, idx) in loginLogs" :key="idx">
                <td>{{ formatDateTime(log.timestamp) }}</td>
                <td>{{ log.ip }}</td>
                <td>{{ log.device }}</td>
                <td>{{ log.location }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="modal-footer">
          <button @click="closeLogsModal" class="btn btn-secondary">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const searchQuery = ref('');
const roleFilter = ref('');
const showAddModal = ref(false);
const showLogsModal = ref(false);
const editingEmployee = ref(null);
const selectedEmployee = ref(null);

const employees = ref([
  { id: 1, name: 'Admin User', email: 'admin@company.com', role: 'admin', status: 'active', lastLogin: '2024-02-18T14:30:00' },
  { id: 2, name: 'John Manager', email: 'john@company.com', role: 'manager', status: 'active', lastLogin: '2024-02-18T10:15:00' },
  { id: 3, name: 'Jane Smith', email: 'jane@company.com', role: 'staff', status: 'active', lastLogin: '2024-02-17T09:45:00' },
  { id: 4, name: 'Mike Johnson', email: 'mike@company.com', role: 'staff', status: 'active', lastLogin: '2024-02-16T16:20:00' },
  { id: 5, name: 'Sarah Lee', email: 'sarah@company.com', role: 'manager', status: 'inactive', lastLogin: '2024-02-10T11:00:00' },
]);

const formData = ref({
  name: '',
  email: '',
  role: '',
  password: '',
  active: true,
});

const loginLogs = ref([
  { timestamp: '2024-02-18T14:30:00', ip: '192.168.1.100', device: 'Chrome on Windows', location: 'Manila, PH' },
  { timestamp: '2024-02-18T09:15:00', ip: '192.168.1.100', device: 'Safari on Mac', location: 'Manila, PH' },
  { timestamp: '2024-02-17T14:45:00', ip: '192.168.1.105', device: 'Chrome on Windows', location: 'Manila, PH' },
]);

const filteredEmployees = computed(() => {
  return employees.value.filter(emp => {
    const matchSearch = emp.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                       emp.email.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchRole = !roleFilter.value || emp.role === roleFilter.value;
    return matchSearch && matchRole;
  });
});

const formatLastLogin = (timestamp) => {
  const date = new Date(timestamp);
  const now = new Date();
  const diffMinutes = Math.floor((now - date) / 60000);
  
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  if (diffMinutes < 1440) return `${Math.floor(diffMinutes / 60)}h ago`;
  return date.toLocaleDateString();
};

const formatDateTime = (timestamp) => {
  return new Date(timestamp).toLocaleString();
};

const closeModal = () => {
  showAddModal.value = false;
  editingEmployee.value = null;
  resetForm();
};

const closeLogsModal = () => {
  showLogsModal.value = false;
  selectedEmployee.value = null;
};

const resetForm = () => {
  formData.value = {
    name: '',
    email: '',
    role: '',
    password: '',
    active: true,
  };
};

const editEmployee = (emp) => {
  editingEmployee.value = emp;
  formData.value = { ...emp, password: '' };
  showAddModal.value = true;
};

const saveEmployee = () => {
  console.log('Saving employee:', formData.value);
  alert('Employee saved successfully!');
  closeModal();
};

const toggleStatus = (emp) => {
  emp.status = emp.status === 'active' ? 'inactive' : 'active';
};

const viewLoginLogs = (emp) => {
  selectedEmployee.value = emp;
  showLogsModal.value = true;
};
</script>

<style scoped>
.employees-container {
  animation: fadeIn 0.3s ease-in;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
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

.filters {
  display: flex;
  gap: 15px;
  margin-bottom: 25px;
}

.search-box {
  flex: 1;
  max-width: 350px;
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.filter-select {
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  background: white;
}

.search-box:focus,
.filter-select:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.employees-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.employees-table thead {
  background-color: #f9f9f9;
}

.employees-table th {
  padding: 15px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.employees-table td {
  padding: 15px;
  border-bottom: 1px solid #e0e0e0;
}

.employees-table tbody tr:hover {
  background-color: #f9f9f9;
}

.name {
  font-weight: 600;
  color: #0a1d37;
}

.role-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.role-badge.admin {
  background-color: #f3e5f5;
  color: #7b1fa2;
}

.role-badge.manager {
  background-color: #e3f2fd;
  color: #1976d2;
}

.role-badge.staff {
  background-color: #e8f5e9;
  color: #388e3c;
}

.status-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.active {
  background-color: #e8f5e9;
  color: #388e3c;
}

.status-badge.inactive {
  background-color: #ffebee;
  color: #d32f2f;
}

.actions {
  display: flex;
  gap: 6px;
}

.btn-small {
  padding: 6px 10px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  transition: all 0.3s;
}

.btn-small:hover {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
}

.btn-small.active {
  background-color: #ff9800;
  color: white;
}

.btn-small.inactive {
  background-color: #f44336;
  color: white;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 8px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-content.modal-large {
  max-width: 700px;
}

.modal-header {
  padding: 20px;
  border-bottom: 1px solid #e0e0e0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  margin: 0;
  color: #0a1d37;
  font-size: 18px;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #999;
  cursor: pointer;
  transition: color 0.3s;
}

.close-btn:hover {
  color: #333;
}

.modal-body {
  padding: 20px;
}

.employee-form {
  padding: 20px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"],
.form-group select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  font-family: inherit;
}

.form-group input[type="text"]:focus,
.form-group input[type="email"]:focus,
.form-group input[type="password"]:focus,
.form-group select:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.form-group input[type="checkbox"] {
  margin-right: 8px;
  cursor: pointer;
}

.logs-table {
  width: 100%;
  border-collapse: collapse;
}

.logs-table th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.logs-table td {
  padding: 12px;
  border-bottom: 1px solid #e0e0e0;
}

.modal-footer {
  padding: 20px;
  border-top: 1px solid #e0e0e0;
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn-secondary {
  background-color: #f0f0f0;
  color: #333;
}

.btn-secondary:hover {
  background-color: #e0e0e0;
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
</style>
