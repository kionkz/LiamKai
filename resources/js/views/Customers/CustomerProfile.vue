<template>
  <div class="customer-profile">
    <div v-if="loading" class="state-card">Loading customer details...</div>
    <div v-else-if="error" class="state-card error-state">
      <p>{{ error }}</p>
      <button @click="fetchCustomer" class="btn btn-secondary">Retry</button>
    </div>

    <template v-else-if="customer">
      <div class="profile-toolbar">
        <button @click="router.push('/customers')" class="btn btn-secondary">Back to Customers</button>
        <div class="toolbar-actions">
          <template v-if="isEditingCustomer">
            <button @click="cancelCustomerEdit" class="btn btn-secondary">Cancel</button>
            <button @click="saveCustomerEdit" :disabled="saving || (customerEditSubmitted && !canSaveCustomer)" class="btn btn-primary">
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </template>
          <button v-else-if="canWriteCustomers" @click="startCustomerEdit" class="btn btn-primary">Edit Customer</button>
        </div>
      </div>

      <section class="profile-card">
        <input
          v-if="isEditingCustomer"
          v-model="editForm.name"
          class="title-input"
          type="text"
          placeholder="Customer name"
        />
        <h1 v-else>{{ customer.name }}</h1>
        <p class="profile-subtitle">Customer profile and order history</p>

        <div class="info-grid">
          <div class="info-panel">
            <h2>Contact Information</h2>
            <div class="field-row">
              <label>Name</label>
              <input v-if="isEditingCustomer" v-model="editForm.name" class="form-input" type="text" @blur="markFieldTouched('name')" />
              <span v-else>{{ customer.name }}</span>
              <small v-if="shouldShowFieldError('name')">{{ fieldErrors.name }}</small>
            </div>
            <div class="field-row">
              <label>Phone</label>
              <input v-if="isEditingCustomer" v-model="editForm.phone" class="form-input" type="tel" inputmode="numeric" maxlength="11" @input="normalizePhoneField" @blur="handlePhoneBlur" />
              <span v-else>{{ customer.phone }}</span>
              <small v-if="shouldShowFieldError('phone')">{{ fieldErrors.phone }}</small>
            </div>
            <div class="field-row">
              <label>Email</label>
              <input v-if="isEditingCustomer" v-model="editForm.email" class="form-input" type="email" @blur="markFieldTouched('email')" />
              <span v-else>{{ customer.email || '-' }}</span>
              <small v-if="shouldShowFieldError('email')">{{ fieldErrors.email }}</small>
            </div>
            <div class="field-row">
              <label>Address</label>
              <input v-if="isEditingCustomer" v-model="editForm.address" class="form-input" type="text" @blur="markFieldTouched('address')" />
              <span v-else>{{ customer.address }}</span>
              <small v-if="shouldShowFieldError('address')">{{ fieldErrors.address }}</small>
            </div>
          </div>

          <div class="info-panel">
            <h2>Account Information</h2>
            <div class="field-row readonly-row">
              <label>Customer Type</label>
              <span class="badge" :class="customer.type">{{ customer.type === 'wholesale' ? 'Wholesale' : 'Retail' }}</span>
            </div>
            <div class="field-row readonly-row">
              <label>Status</label>
              <span class="badge" :class="customer.status || 'active'">{{ formatStatus(customer.status || 'active') }}</span>
            </div>
            <div v-if="customer.type === 'wholesale'" class="credit-summary">
              <div><span>Credit Used</span><strong>{{ formatCurrency(customer.credit_used || currentBalance) }}</strong></div>
              <div><span>Credit Limit</span><strong>{{ formatCurrency(customer.credit_limit) }}</strong></div>
            </div>
          </div>
        </div>
      </section>

      <section v-if="!isEditingCustomer" class="profile-card">
        <div class="orders-header">
          <div>
            <h2>Order History</h2>
            <p>{{ paginatedOrders.length ? `Showing ${paginatedOrders.length} of ${orders.length} orders` : 'No orders yet' }}</p>
          </div>
        </div>

        <div v-if="orders.length === 0" class="empty-state">No orders found for this customer.</div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Date</th>
              <th>Items Ordered</th>
              <th>Amount</th>
              <th>Fulfillment Status</th>
              <th>Order State</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="order in paginatedOrders"
              :key="order.id"
              :class="{ 'cancelled-row': order.order_status === 'cancelled' }"
            >
              <td>#{{ String(order.id).padStart(4, '0') }}</td>
              <td>{{ formatDate(order.order_date || order.created_at) }}</td>
              <td>
                <span v-for="item in orderItems(order)" :key="item.id" class="item-chip">
                  {{ item.product?.name || 'Unknown' }} x {{ formatNumber(item.quantity) }}
                </span>
              </td>
              <td>{{ formatCurrency(order.total_amount) }}</td>
              <td>
                <span class="status" :class="fulfillmentStatusClass(order)">
                  {{ formatFulfillmentStatus(order) }}
                </span>
              </td>
              <td>
                <span class="status" :class="orderStateClass(order)">
                  {{ formatOrderState(order) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="pagination" v-if="orderLastPage > 1">
          <button class="btn btn-secondary" @click="changeOrderPage(orderPage - 1)" :disabled="orderPage === 1">Previous</button>
          <button
            v-for="page in orderLastPage"
            :key="page"
            class="page-button"
            :class="{ active: page === orderPage }"
            @click="changeOrderPage(page)"
          >{{ page }}</button>
          <button class="btn btn-secondary" @click="changeOrderPage(orderPage + 1)" :disabled="orderPage === orderLastPage">Next</button>
        </div>
      </section>
    </template>

    <div v-if="showOrderEditModal" class="modal-overlay" @click.self="closeOrderEdit">
      <div class="modal-content modal-wide" @click.stop>
        <div class="modal-header">
          <h2>Edit Order #{{ String(orderEditTarget?.id || '').padStart(4, '0') }}</h2>
          <button @click="closeOrderEdit" class="btn-close">×</button>
        </div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label>Fulfillment Type</label>
              <select v-model="orderEditForm.fulfillment_type" class="form-input" data-searchable="off">
                <option value="delivery">Delivery</option>
                <option value="pickup">Pickup</option>
              </select>
            </div>
            <div class="form-group">
              <label>Scheduled Date &amp; Time</label>
              <input v-model="orderEditForm.scheduled_for" class="form-input" type="datetime-local" />
            </div>
          </div>
          <div v-if="orderEditForm.fulfillment_type === 'delivery'" class="form-group">
            <label>Delivery Address</label>
            <input v-model="orderEditForm.delivery_address" class="form-input" type="text" />
          </div>

          <div class="edit-items-header">
            <h3>Products</h3>
            <button type="button" class="btn btn-secondary btn-small" @click="addEditItem">Add Product</button>
          </div>

          <div v-for="(item, index) in orderEditForm.items" :key="index" class="edit-item-row">
            <div class="form-group">
              <label>Product</label>
              <SearchableSelect v-model="item.product_id" :options="productOptions" placeholder="Select product" @change="syncEditItemPrice(index)" />
            </div>
            <div class="form-group">
              <label>Quantity</label>
              <input v-model.number="item.quantity" class="form-input" type="number" min="0.01" step="0.01" @input="item.quantity = Math.max(0, item.quantity || 0)" @blur="normalizeEditItemQuantity(index)" />
            </div>
            <div class="form-group">
              <label>Unit Price</label>
              <input :value="formatCurrency(item.unit_price)" class="form-input" type="text" readonly />
            </div>
            <div class="form-group">
              <label>Subtotal</label>
              <input :value="formatCurrency(Number(item.quantity || 0) * Number(item.unit_price || 0))" class="form-input" type="text" readonly />
            </div>
            <button type="button" class="btn btn-danger btn-remove" @click="removeEditItem(index)">Remove</button>
          </div>

          <p v-if="orderEditError" class="inline-warning">{{ orderEditError }}</p>
          <div class="edit-total">
            <span>Total</span>
            <strong>{{ formatCurrency(orderEditTotal) }}</strong>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" @click="closeOrderEdit">Cancel</button>
          <button type="button" class="btn btn-primary" :disabled="savingOrderEdit" @click="saveOrderEdit">
            {{ savingOrderEdit ? 'Saving...' : 'Save Order' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api';
import SearchableSelect from '../../components/SearchableSelect.vue';
import { useAuthStore } from '../../stores/authStore';
import { formatPeso } from '../../utils/currency';
import { getApiErrorMessage } from '../../utils/orderValidation';
import { resolveOrderUnitPrice } from '../../utils/pricing';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const customer = ref(null);
const loading = ref(false);
const error = ref('');
const saving = ref(false);
const isEditingCustomer = ref(false);
const orderPage = ref(1);
const ordersPerPage = ref(5);
const customerEditSubmitted = ref(false);
const touchedFields = ref({});
const products = ref([]);
const showOrderEditModal = ref(false);
const savingOrderEdit = ref(false);
const orderEditTarget = ref(null);
const orderEditError = ref('');

const editForm = ref({ name: '', email: '', phone: '', address: '' });
const orderEditForm = ref({
  fulfillment_type: 'delivery',
  scheduled_for: '',
  delivery_address: '',
  items: [],
});

const canWriteCustomers = computed(() => authStore.can('customers.write'));
const canEditOrders = computed(() => authStore.can('orders.edit'));
const orders = computed(() => customer.value?.orders || []);
const orderLastPage = computed(() => Math.max(1, Math.ceil(orders.value.length / ordersPerPage.value)));
const paginatedOrders = computed(() => {
  const start = (orderPage.value - 1) * ordersPerPage.value;
  return orders.value.slice(start, start + ordersPerPage.value);
});

const fieldErrors = computed(() => {
  const errors = {};
  if (!editForm.value.name.trim()) errors.name = 'Name is required.';
  if (!editForm.value.address.trim()) errors.address = 'Address is required.';
  if (editForm.value.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(editForm.value.email)) errors.email = 'Enter a valid email address.';
  if (!/^\d{11}$/.test(normalizePhPhone(editForm.value.phone))) errors.phone = 'Phone number must contain exactly 11 digits.';
  return errors;
});

const canSaveCustomer = computed(() => Object.keys(fieldErrors.value).length === 0);

const currentBalance = computed(() => orders.value
  .filter((order) => ['unpaid', 'partially_paid'].includes(order.payment_status))
  .reduce((sum, order) => sum + Number(order.outstanding_balance || 0), 0));

const normalizePhPhone = (value) => {
  return String(value || '').replace(/\D/g, '').slice(0, 11);
};

const normalizePhoneField = () => {
  editForm.value.phone = normalizePhPhone(editForm.value.phone);
};

const markFieldTouched = (field) => {
  touchedFields.value = { ...touchedFields.value, [field]: true };
};

const handlePhoneBlur = () => {
  normalizePhoneField();
  markFieldTouched('phone');
};

const shouldShowFieldError = (field) => {
  return isEditingCustomer.value
    && !!fieldErrors.value[field]
    && customerEditSubmitted.value;
};

const formatCurrency = formatPeso;
const formatNumber = (value) => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
const formatDate = (value) => value ? new Date(value).toLocaleDateString() : '-';
const formatStatus = (value) => String(value || '').replace('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const orderItems = (order) => order.items || order.order_items || [];
const productOptions = computed(() => products.value.map((product) => ({ value: product.id, label: product.name })));
const orderEditTotal = computed(() => orderEditForm.value.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0));

const fulfillmentStatusClass = (order) => {
  if (order.fulfillment_status === 'completed' || order.delivery_status === 'delivered') return 'paid';
  if (order.fulfillment_status === 'in_progress' || order.delivery_status === 'processing') return 'partial';
  if (order.fulfillment_status === 'cancelled' || order.delivery_status === 'cancelled') return 'cancelled';
  return 'pending';
};

const formatFulfillmentStatus = (order) => {
  if (order.fulfillment_status === 'completed' || order.delivery_status === 'delivered') return 'Delivered';
  if (order.fulfillment_status === 'in_progress' || order.delivery_status === 'processing') return 'In Progress';
  if (order.fulfillment_status === 'cancelled' || order.delivery_status === 'cancelled') return 'Cancelled';
  return 'Pending';
};

const orderStateClass = (order) => order.order_status === 'cancelled' ? 'cancelled' : 'paid';

const formatOrderState = (order) => {
  if (order.order_status === 'cancelled') return 'Cancelled';
  return 'Active';
};

const changeOrderPage = (page) => {
  if (page < 1 || page > orderLastPage.value) return;
  orderPage.value = page;
};

const startCustomerEdit = () => {
  if (!canWriteCustomers.value) {
    error.value = 'Access denied. You do not have permission to perform this action.';
    return;
  }
  editForm.value = {
    name: customer.value.name || '',
    email: customer.value.email || '',
    phone: customer.value.phone || '',
    address: customer.value.address || '',
  };
  customerEditSubmitted.value = false;
  touchedFields.value = {};
  isEditingCustomer.value = true;
};

const cancelCustomerEdit = () => {
  isEditingCustomer.value = false;
  startCustomerEdit();
  isEditingCustomer.value = false;
};

const fetchCustomer = async () => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get(`/customers/${route.params.id}`);
    if (response.data.success) {
      customer.value = response.data.data;
      if (route.query.edit === '1' && canWriteCustomers.value) {
        startCustomerEdit();
      }
      if (orderPage.value > orderLastPage.value) orderPage.value = orderLastPage.value;
      return;
    }
    error.value = response.data.message || 'Failed to load customer';
  } catch (err) {
    error.value = getApiErrorMessage(err, 'Failed to load customer');
  } finally {
    loading.value = false;
  }
};

watch(() => route.query.edit, (value) => {
  if (value === '1' && customer.value && canWriteCustomers.value) {
    startCustomerEdit();
  }
});

const loadProducts = async () => {
  try {
    const response = await api.get('/products', { params: { per_page: 250 } });
    if (response.data.success) {
      products.value = response.data.data || [];
    }
  } catch (err) {
    error.value = getApiErrorMessage(err, 'Failed to load products');
  }
};

const saveCustomerEdit = async () => {
  if (!canWriteCustomers.value) {
    alert('Access denied. You do not have permission to perform this action.');
    return;
  }
  customerEditSubmitted.value = true;
  if (!canSaveCustomer.value) return;
  saving.value = true;
  try {
    normalizePhoneField();
    const response = await api.put(`/customers/${customer.value.id}`, {
      name: editForm.value.name,
      email: editForm.value.email || null,
      phone: editForm.value.phone,
      address: editForm.value.address,
    });
    if (response.data.success) {
      customer.value = { ...customer.value, ...response.data.data };
      isEditingCustomer.value = false;
      return;
    }
    alert(response.data.message || 'Failed to update customer');
  } catch (err) {
    alert(getApiErrorMessage(err, 'Failed to update customer'));
  } finally {
    saving.value = false;
  }
};

const formatDateTimeLocal = (value) => {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
};

const findProduct = (productId) => products.value.find((product) => String(product.id) === String(productId));
const selectedCustomerType = computed(() => customer.value?.type || 'retail');

const buildEditItem = (item = {}) => ({
  product_id: item.product_id || item.product?.id || '',
  quantity: Number(item.quantity || 1),
  unit_price: Number(item.unit_price || 0),
});

const closeOrderEdit = () => {
  showOrderEditModal.value = false;
  orderEditTarget.value = null;
  orderEditError.value = '';
};

const addEditItem = () => {
  orderEditForm.value.items.push(buildEditItem());
};

const removeEditItem = (index) => {
  orderEditForm.value.items.splice(index, 1);
};

const syncEditItemPrice = (index) => {
  const item = orderEditForm.value.items[index];
  const product = findProduct(item.product_id);
  item.unit_price = product ? resolveOrderUnitPrice(product, selectedCustomerType.value) : 0;
};

const normalizeEditItemQuantity = (index) => {
  const item = orderEditForm.value.items[index];
  const product = findProduct(item.product_id);
  const quantity = Number(item.quantity || 0);

  if (product?.unit_of_measure === 'Per pack') {
    item.quantity = Math.max(1, Math.round(quantity || 1));
    return;
  }

  if (selectedCustomerType.value === 'wholesale' && quantity < 10) {
    item.quantity = 10;
    return;
  }

  if (selectedCustomerType.value !== 'wholesale' && quantity >= 10) {
    item.quantity = 9.99;
    return;
  }

  item.quantity = quantity <= 0 ? 0.01 : quantity;
};

const validateOrderEdit = () => {
  if (!canEditOrders.value) return 'Access denied. You do not have permission to perform this action.';
  const validItems = orderEditForm.value.items.filter((item) => item.product_id);

  if (validItems.length === 0) return 'Add at least one product.';
  if (!orderEditForm.value.scheduled_for) return 'Choose a scheduled date and time.';
  if (orderEditForm.value.fulfillment_type === 'delivery' && !orderEditForm.value.delivery_address.trim()) return 'Delivery address is required.';

  for (const [index, item] of validItems.entries()) {
    const product = findProduct(item.product_id);
    const quantity = Number(item.quantity || 0);
    if (!product) return `Item ${index + 1}: select a valid product.`;
    if (quantity <= 0) return `Item ${index + 1}: quantity must be greater than zero.`;
    if (product.unit_of_measure === 'kg' && selectedCustomerType.value === 'retail' && quantity >= 10) return `Item ${index + 1}: retail orders must be below 10kg.`;
    if (product.unit_of_measure === 'kg' && selectedCustomerType.value === 'wholesale' && quantity < 10) return `Item ${index + 1}: wholesale orders must be at least 10kg.`;
  }

  return '';
};

const saveOrderEdit = async () => {
  if (!canEditOrders.value) {
    orderEditError.value = 'Access denied. You do not have permission to perform this action.';
    return;
  }
  orderEditError.value = validateOrderEdit();
  if (orderEditError.value || !orderEditTarget.value) return;

  savingOrderEdit.value = true;
  try {
    const response = await api.put(`/orders/${orderEditTarget.value.id}`, {
      fulfillment_type: orderEditForm.value.fulfillment_type,
      scheduled_for: orderEditForm.value.scheduled_for,
      delivery_address: orderEditForm.value.fulfillment_type === 'delivery' ? orderEditForm.value.delivery_address : null,
      items: orderEditForm.value.items
        .filter((item) => item.product_id)
        .map((item) => ({ product_id: item.product_id, quantity: Number(item.quantity || 0) })),
    });

    if (response.data.success) {
      closeOrderEdit();
      await fetchCustomer();
      return;
    }

    orderEditError.value = response.data.message || 'Failed to update order.';
  } catch (err) {
    orderEditError.value = getApiErrorMessage(err, 'Failed to update order.');
  } finally {
    savingOrderEdit.value = false;
  }
};

onMounted(async () => {
  await Promise.all([fetchCustomer(), loadProducts()]);
});
</script>

<style scoped>
.customer-profile { max-width: 1180px; margin: 0 auto; padding: 20px 0 36px; }
.state-card, .profile-card { background: #fff; border: 1px solid #e6ebf2; border-radius: 12px; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06); padding: 28px; margin-bottom: 18px; }
.error-state { color: #b91c1c; }
.profile-toolbar, .orders-header { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 18px; }
.toolbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
h1 { margin: 0; color: #102746; font-size: 34px; line-height: 1.15; }
h2 { margin: 0 0 16px; color: #102746; font-size: 17px; }
.profile-subtitle, .orders-header p { margin: 6px 0 0; color: #607089; }
.title-input { width: 100%; max-width: 520px; border: 2px solid #d9e1ec; border-radius: 10px; padding: 12px 14px; font-size: 28px; font-weight: 700; color: #102746; }
.info-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(320px, 0.7fr); gap: 24px; margin-top: 24px; }
.info-panel { border: 1px solid #eef1f5; border-radius: 10px; padding: 22px; background: #fbfcfe; }
.field-row { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 16px; align-items: center; padding: 12px 0; border-bottom: 1px solid #edf0f4; }
.field-row:last-child { border-bottom: 0; }
.field-row label { color: #4b5565; font-weight: 700; font-size: 13px; }
.field-row span { color: #111827; font-weight: 600; }
.field-row small { grid-column: 2; color: #b91c1c; font-weight: 600; }
.form-input { width: 100%; border: 2px solid #d9e1ec; border-radius: 8px; padding: 10px 12px; background: #fff; color: #111827; box-sizing: border-box; }
.form-input:focus, .title-input:focus { outline: none; border-color: #e57c2a; box-shadow: 0 0 0 3px rgba(229,124,42,0.12); }
.badge, .status { display: inline-flex; width: fit-content; align-items: center; border-radius: 6px; padding: 5px 10px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3px; }
.badge.retail { background: #e3f2fd; color: #1976d2; }
.badge.wholesale { background: #fff7ed; color: #c65f18; }
.badge.active, .status.paid { background: #dcfce7; color: #15803d; }
.badge.inactive, .status.cancelled { background: #e5e7eb; color: #4b5563; }
.status.pending { background: #ffedd5; color: #c2410c; }
.status.partial { background: #dbeafe; color: #1d4ed8; }
.credit-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 18px; }
.credit-summary div { background: #fff; border: 1px solid #e6ebf2; border-radius: 8px; padding: 14px; }
.credit-summary span { display: block; color: #607089; font-size: 12px; margin-bottom: 4px; }
.credit-summary strong { color: #102746; }
.data-table { width: 100%; border-collapse: collapse; background: white; }
.data-table th { padding: 14px 16px; text-align: left; background: #f8fafc; color: #4b5565; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; }
.data-table td { padding: 14px 16px; border-bottom: 1px solid #eef1f4; color: #111827; vertical-align: top; }
.data-table tbody tr { cursor: pointer; }
.data-table tbody tr:hover { background: #f9fafb; }
.selected-row { background: #fff7ed !important; box-shadow: inset 3px 0 #e57c2a; }
.cancelled-row { color: #6b7280; background: #f4f5f7; }
.item-chip { display: inline-flex; margin: 0 6px 6px 0; padding: 4px 8px; border-radius: 999px; background: #eef4ff; color: #173b70; font-size: 12px; font-weight: 700; }
.empty-state { padding: 28px; color: #607089; border: 1px dashed #cbd5e1; border-radius: 10px; text-align: center; }
.inline-warning { margin: 0 0 14px; padding: 10px 12px; border-radius: 8px; background: #fff7ed; color: #c2410c; font-weight: 700; }
.pagination { display: flex; justify-content: flex-end; align-items: center; gap: 8px; margin-top: 16px; }
.page-button { border: 1px solid #d9e1ec; background: #fff; color: #102746; border-radius: 8px; padding: 9px 12px; font-weight: 700; cursor: pointer; }
.page-button.active { background: #e57c2a; color: #fff; border-color: #e57c2a; }
.btn { border: 0; border-radius: 8px; padding: 10px 18px; cursor: pointer; font-weight: 800; }
.btn-primary { background: #e57c2a; color: #fff; }
.btn-secondary { background: #f8fafc; color: #102746; border: 1px solid #d9e1ec; }
.btn-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.btn-small { padding: 7px 11px; font-size: 12px; }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { width: min(92vw, 560px); background: #fff; border-radius: 12px; overflow: hidden; max-height: 92vh; overflow-y: auto; }
.modal-wide { width: min(96vw, 980px); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #eef1f4; }
.modal-header h2 { margin: 0; }
.btn-close { border: 0; background: transparent; font-size: 24px; cursor: pointer; color: #607089; }
.modal-body { padding: 24px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 8px; color: #374151; font-weight: 800; }
.field-note { color: #607089; font-size: 13px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 24px; border-top: 1px solid #eef1f4; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.edit-items-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 18px 0 12px; }
.edit-items-header h3 { margin: 0; color: #102746; font-size: 16px; }
.edit-item-row { display: grid; grid-template-columns: minmax(220px, 1.5fr) 120px 140px 140px auto; gap: 12px; align-items: end; padding: 14px; border: 1px solid #e6ebf2; border-radius: 10px; margin-bottom: 10px; background: #fbfcfe; }
.btn-remove { margin-bottom: 16px; }
.edit-total { display: flex; justify-content: flex-end; align-items: center; gap: 18px; padding: 14px 0 0; font-size: 16px; }
.edit-total strong { color: #102746; font-size: 20px; }
@media (max-width: 800px) {
  .profile-toolbar, .orders-header { align-items: stretch; flex-direction: column; }
  .toolbar-actions { width: 100%; }
  .info-grid, .field-row, .form-grid, .edit-item-row { grid-template-columns: 1fr; }
  .btn-remove { margin-bottom: 0; }
}
</style>
