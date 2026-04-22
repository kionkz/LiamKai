<template>
  <div class="login-container">
    <div class="login-card">
      <div class="logo">
        <h1>LiamKai</h1>
        <p>Fish Trading System</p>
      </div>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="username">Username</label>
          <input
            v-model="form.username"
            type="text"
            id="username"
            placeholder="Enter your username"
            autocomplete="username"
            required
          />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="password-field">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              id="password"
              placeholder="Enter your password"
              autocomplete="current-password"
              required
            />
            <button
              type="button"
              class="password-toggle"
              @click="showPassword = !showPassword"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
            >
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
        </div>

        <div v-if="error" class="error-message">
          {{ error }}
        </div>

        <button
          type="submit"
          class="login-btn"
          :disabled="loading"
        >
          {{ loading ? 'Logging in...' : 'Login' }}
        </button>
      </form>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

const router = useRouter();
const authStore = useAuthStore();

const form = ref({
  username: '',
  password: ''
});

const loading = ref(false);
const error = ref('');
const showPassword = ref(false);

const handleLogin = async () => {
  error.value = '';
  loading.value = true;
  
  console.log('Login attempt with:', form.value.username);

  const result = await authStore.login(form.value.username, form.value.password);
  
  console.log('Login result:', result);

  if (result.success) {
    console.log('Login successful, redirecting to home');
    if (result.mustChangePassword) {
      router.push('/change-password');
    } else {
      router.push('/');
    }
  } else {
    error.value = result.message || 'Login failed';
    console.error('Login failed:', error.value);
  }

  loading.value = false;
};

</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0a1d37 0%, #1a3a52 100%);
  padding: 20px;
}

.login-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  padding: 40px;
  width: 100%;
  max-width: 400px;
}

.logo {
  text-align: center;
  margin-bottom: 30px;
}

.logo h1 {
  font-size: 28px;
  color: #e57c2a;
  margin: 0;
}

.logo p {
  color: #666;
  font-size: 14px;
  margin: 5px 0 0 0;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #0a1d37;
  font-size: 14px;
}

.form-group input {
  width: 100%;
  padding: 12px 15px;
  border: 2px solid #e0e0e0;
  border-radius: 6px;
  font-size: 14px;
  transition: border-color 0.3s;
}

.form-group input:focus {
  outline: none;
  border-color: #e57c2a;
  background-color: #fef9f5;
}

.password-field {
  position: relative;
}

.password-field input {
  padding-right: 88px;
}

.password-toggle {
  position: absolute;
  top: 50%;
  right: 10px;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: #0a1d37;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.password-toggle:hover {
  color: #e57c2a;
}

.error-message {
  background-color: #fee;
  color: #c33;
  padding: 12px;
  border-radius: 6px;
  margin-bottom: 15px;
  font-size: 13px;
  border-left: 4px solid #c33;
}

.login-btn {
  width: 100%;
  padding: 12px;
  background-color: #e57c2a;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.3s;
  margin-top: 10px;
}

.login-btn:hover:not(:disabled) {
  background-color: #d46a1a;
}

.login-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}


</style>
