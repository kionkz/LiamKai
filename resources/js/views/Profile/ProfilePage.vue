<template>
  <div class="profile-page">
    <div class="profile-header-card">
      <h2>My Profile</h2>
      <p class="subtitle">Manage your account details and password.</p>
    </div>

    <div class="profile-card">
      <div class="section-title-row">
        <h3>Account Details</h3>
        <span class="chip">Gmail only</span>
      </div>

      <div v-if="loading" class="state-msg">Loading profile...</div>
      <div v-else-if="error" class="state-msg error">{{ error }}</div>

      <form v-else @submit.prevent="saveProfile" class="profile-form">
        <div class="form-group">
          <label for="name">Full Name</label>
          <input id="name" v-model="form.name" type="text" required />
        </div>

        <div class="form-group">
          <label for="email">Gmail Address</label>
          <input id="email" v-model="form.email" type="email" placeholder="example@gmail.com" required />
        </div>

        <div class="form-group full-width">
          <label for="password">New Password (optional)</label>
          <input id="password" v-model="form.password" type="password" minlength="8" placeholder="Leave blank to keep current password" />
        </div>

        <p v-if="successMessage" class="state-msg success full-width">{{ successMessage }}</p>
        <p v-if="saveError" class="state-msg error full-width">{{ saveError }}</p>

        <div class="actions full-width">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Saving...' : 'Save Profile' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api';

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const saveError = ref('');
const successMessage = ref('');

const form = ref({
  name: '',
  email: '',
  password: '',
});

const loadProfile = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await api.get('/profile');
    if (response.data?.success) {
      form.value.name = response.data.data.name || '';
      form.value.email = response.data.data.email || '';
    } else {
      error.value = response.data?.message || 'Failed to load profile.';
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load profile.';
  } finally {
    loading.value = false;
  }
};

const saveProfile = async () => {
  saving.value = true;
  saveError.value = '';
  successMessage.value = '';

  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      password: form.value.password || null,
    };

    const response = await api.put('/profile', payload);

    if (response.data?.success) {
      const updatedUser = response.data.data;
      localStorage.setItem('user', JSON.stringify(updatedUser));

      if (response.data.token) {
        localStorage.setItem('authToken', response.data.token);
        api.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
      }

      form.value.password = '';
      successMessage.value = response.data.message || 'Profile updated successfully.';
    } else {
      saveError.value = response.data?.message || 'Unable to save profile.';
    }
  } catch (err) {
    saveError.value = err.response?.data?.message || 'Unable to save profile.';
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadProfile();
});
</script>

<style scoped>
.profile-page {
  width: 100%;
  min-height: calc(100vh - 170px);
}

.profile-header-card {
  background: #fff;
  border-radius: 12px;
  padding: 22px 24px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
  margin-bottom: 18px;
}

.profile-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

h2 {
  margin: 0;
  color: #0a1d37;
  font-size: 38px;
}

.subtitle {
  margin: 8px 0 0;
  color: #6b7280;
}

.section-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.section-title-row h3 {
  margin: 0;
  color: #0a1d37;
  font-size: 28px;
}

.chip {
  display: inline-block;
  padding: 6px 10px;
  border-radius: 999px;
  background: #fff3e0;
  color: #c26a1f;
  font-weight: 700;
  font-size: 12px;
}

.profile-form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group {
  display: grid;
  gap: 6px;
}

.full-width {
  grid-column: 1 / -1;
}

label {
  font-weight: 600;
  color: #374151;
}

input {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 12px 14px;
  font-size: 14px;
}

input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.12);
}

.actions {
  margin-top: 4px;
  display: flex;
  justify-content: flex-end;
}

.btn {
  padding: 12px 18px;
  border-radius: 8px;
  border: 0;
  cursor: pointer;
  font-weight: 600;
}

.btn-primary {
  background: #e57c2a;
  color: #fff;
}

.btn-primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.state-msg {
  font-size: 14px;
}

.state-msg.error {
  color: #c33;
}

.state-msg.success {
  color: #0f766e;
}

@media (max-width: 1024px) {
  h2 {
    font-size: 30px;
  }

  .section-title-row h3 {
    font-size: 24px;
  }
}

@media (max-width: 768px) {
  .profile-page {
    min-height: auto;
  }

  .profile-header-card,
  .profile-card {
    padding: 18px;
  }

  .profile-form {
    grid-template-columns: 1fr;
  }

  .actions {
    justify-content: stretch;
  }

  .btn-primary {
    width: 100%;
  }
}
</style>
