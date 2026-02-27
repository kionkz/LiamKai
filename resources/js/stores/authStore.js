import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const token = ref(localStorage.getItem('authToken'));

  const isAuthenticated = computed(() => !!token.value && !!user.value);

  const login = async (email, password) => {
    try {
      const response = await api.post('/login', {
        email: email,
        password: password
      });

      token.value = response.data.token;
      user.value = response.data.user;

      localStorage.setItem('authToken', token.value);
      localStorage.setItem('user', JSON.stringify(user.value));

      api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;

      return { success: true };
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Login failed'
      };
    }
  };

  const logout = () => {
    token.value = null;
    user.value = null;
    localStorage.removeItem('authToken');
    localStorage.removeItem('user');
    delete api.defaults.headers.common['Authorization'];
  };

  const checkAuth = () => {
    const savedToken = localStorage.getItem('authToken');
    const savedUser = localStorage.getItem('user');

    if (savedToken && savedUser) {
      token.value = savedToken;
      user.value = JSON.parse(savedUser);
      api.defaults.headers.common['Authorization'] = `Bearer ${savedToken}`;
    }
  };

  return {
    user,
    token,
    isAuthenticated,
    login,
    logout,
    checkAuth
  };
});
