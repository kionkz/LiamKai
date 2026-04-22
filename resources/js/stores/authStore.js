import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api';
import { canAccess, defaultRouteForRole } from '../config/permissions';

export const useAuthStore = defineStore('auth', () => {
  const parseStoredUser = () => {
    const savedUser = localStorage.getItem('user');
    if (!savedUser) {
      return null;
    }

    try {
      return JSON.parse(savedUser);
    } catch {
      localStorage.removeItem('user');
      return null;
    }
  };

  const user = ref(parseStoredUser());
  const token = ref(localStorage.getItem('authToken'));

  const isAuthenticated = computed(() => !!token.value && !!user.value);
  const userRole = computed(() => user.value?.role || null);
  const isAdmin = computed(() => user.value?.role === 'admin');

  /** Check whether the current user's role can access a given module. */
  const hasPermission = (module) => canAccess(userRole.value, module);

  /** The route the user should land on after login. */
  const homeRoute = computed(() => defaultRouteForRole(userRole.value));

  const login = async (username, password) => {
    try {
      const response = await api.post('/login', { username, password });

      token.value = response.data.token;
      user.value = response.data.user;

      localStorage.setItem('authToken', token.value);
      localStorage.setItem('user', JSON.stringify(user.value));

      api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;

      return {
        success: true,
        mustChangePassword: !!response.data.user?.must_change_password,
      };
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Login failed'
      };
    }
  };

  /** Called after a forced password change to refresh stored user + token. */
  const applyPasswordChange = (updatedUser, newToken) => {
    user.value = updatedUser;
    token.value = newToken;
    localStorage.setItem('user', JSON.stringify(updatedUser));
    localStorage.setItem('authToken', newToken);
    api.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
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
    const savedUser = parseStoredUser();

    if (savedToken && savedUser) {
      if (!canAccess(savedUser.role, 'profile')) {
        localStorage.removeItem('authToken');
        localStorage.removeItem('user');
        delete api.defaults.headers.common['Authorization'];
        token.value = null;
        user.value = null;
        return;
      }

      token.value = savedToken;
      user.value = savedUser;
      api.defaults.headers.common['Authorization'] = `Bearer ${savedToken}`;
    }
  };

  return {
    user,
    token,
    isAuthenticated,
    userRole,
    isAdmin,
    hasPermission,
    homeRoute,
    login,
    logout,
    checkAuth,
    applyPasswordChange,
  };
});
