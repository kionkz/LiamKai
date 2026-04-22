<template>
  <div class="change-password-wrapper">
    <div class="change-password-card">
      <div class="card-header">
        <div class="lock-icon">🔐</div>
        <h1>Set Your New Password</h1>
        <p>Your account was created with a temporary password. Please set a new password to continue.</p>
      </div>

      <form @submit.prevent="submit" class="form">
        <div class="form-group">
          <label for="password">New Password</label>
          <div class="password-field">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              id="password"
              placeholder="At least 8 characters"
              minlength="8"
              required
              autocomplete="new-password"
            />
            <button type="button" class="toggle-btn" @click="showPassword = !showPassword">
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
        </div>

        <div class="form-group">
          <label for="password_confirmation">Confirm New Password</label>
          <div class="password-field">
            <input
              v-model="form.password_confirmation"
              :type="showConfirm ? 'text' : 'password'"
              id="password_confirmation"
              placeholder="Repeat your new password"
              minlength="8"
              required
              autocomplete="new-password"
            />
            <button type="button" class="toggle-btn" @click="showConfirm = !showConfirm">
              {{ showConfirm ? 'Hide' : 'Show' }}
            </button>
          </div>
        </div>

        <div v-if="error" class="alert alert-error">{{ error }}</div>
        <div v-if="success" class="alert alert-success">{{ success }}</div>

        <button type="submit" class="btn-submit" :disabled="saving">
          {{ saving ? 'Saving...' : 'Set New Password' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api';
import { useAuthStore } from '../../stores/authStore';

const router = useRouter();
const authStore = useAuthStore();

const form = ref({ password: '', password_confirmation: '' });
const saving = ref(false);
const error = ref('');
const success = ref('');
const showPassword = ref(false);
const showConfirm = ref(false);

const submit = async () => {
  error.value = '';
  success.value = '';

  if (form.value.password !== form.value.password_confirmation) {
    error.value = 'Passwords do not match.';
    return;
  }

  saving.value = true;
  try {
    const response = await api.post('/change-password', form.value);
    if (response.data.success) {
      // Update stored user and token with the fresh values returned
      authStore.applyPasswordChange(response.data.user, response.data.token);
      success.value = 'Password set successfully! Redirecting...';
      setTimeout(() => router.push(authStore.homeRoute), 1200);
    } else {
      error.value = response.data.message || 'Failed to change password.';
    }
  } catch (err) {
    const errs = err.response?.data?.errors;
    error.value = errs
      ? Object.values(errs).flat().join(' ')
      : (err.response?.data?.message || 'Failed to change password.');
  } finally {
    saving.value = false;
  }
};
</script>

<style scoped>
.change-password-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0a1d37 0%, #1a3a52 100%);
  padding: 20px;
}

.change-password-card {
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  padding: 44px 40px;
  width: 100%;
  max-width: 420px;
}

.card-header {
  text-align: center;
  margin-bottom: 32px;
}

.lock-icon {
  font-size: 40px;
  margin-bottom: 12px;
}

.card-header h1 {
  margin: 0 0 10px;
  color: #102746;
  font-size: 22px;
  font-weight: 700;
}

.card-header p {
  margin: 0;
  color: #64748b;
  font-size: 14px;
  line-height: 1.5;
}

.form-group {
  margin-bottom: 18px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  font-size: 13px;
  color: #334155;
}

.password-field {
  position: relative;
}

.password-field input {
  width: 100%;
  padding: 11px 14px;
  padding-right: 64px;
  border: 1px solid #d7deea;
  border-radius: 10px;
  font-size: 14px;
  font-family: inherit;
  background: #fbfcfe;
  box-sizing: border-box;
  transition: border-color 0.2s;
}

.password-field input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.12);
}

.toggle-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  color: #e57c2a;
}

.alert {
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 13px;
  margin-bottom: 16px;
}

.alert-error {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.alert-success {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.btn-submit {
  width: 100%;
  padding: 13px;
  background: #e57c2a;
  color: #ffffff;
  border: none;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-submit:hover:not(:disabled) {
  background: #c96a1c;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
