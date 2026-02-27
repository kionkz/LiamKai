import axios from 'axios';

const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// Add token to requests if available
const token = localStorage.getItem('authToken');
if (token) {
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

// Send cookies (for Laravel Sanctum session auth) so XSRF token header can be set
api.defaults.withCredentials = true;

// Ensure axios knows the cookie/header names (defaults, but explicit is clearer)
api.defaults.xsrfCookieName = 'XSRF-TOKEN';
api.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

function getCookie(name) {
  if (typeof document === 'undefined') return null;
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
  return null;
}

// Ensure CSRF cookie for state-changing requests when not using Bearer token
api.interceptors.request.use(async (config) => {
  const method = (config.method || '').toLowerCase();
  const needsCsrf = ['post', 'put', 'patch', 'delete'].includes(method);
  const hasAuthHeader = !!(config.headers && (config.headers.Authorization || config.headers.authorization));

  if (needsCsrf && !hasAuthHeader) {
    try {
      // Request the CSRF cookie from Sanctum endpoint on the app root
      const root = api.defaults.baseURL.replace(/\/api\/?$/, '');
      await axios.get(root + '/sanctum/csrf-cookie', { withCredentials: true });
      // After requesting cookie, read it and set header explicitly (handles some CORS corner cases)
      const xsrf = getCookie(api.defaults.xsrfCookieName);
      if (xsrf && typeof document !== 'undefined') {
        // axios will normally set this automatically in browser, but explicitly add the header
        config.headers[api.defaults.xsrfHeaderName] = decodeURIComponent(xsrf);
      }
    } catch (e) {
      // ignore — request will likely fail later with proper error
    }
  }

  return config;
}, (error) => Promise.reject(error));

export default api;


