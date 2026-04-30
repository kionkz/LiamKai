<template>
  <div class="create-order-container" :class="{ 'modal-mode': isModal }">
    <div v-if="isModal" class="modal-sticky-title">
      <h1>Create New Order</h1>
      <button type="button" class="modal-sticky-close" @click="emit('close')">×</button>
    </div>

    <div v-else class="header-section">
      <button @click="$router.push('/orders')" class="btn btn-back">← Back to Order History</button>
    </div>

    <div v-if="loading" class="loading-state">
      <p>Loading form data...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="loadFormData" class="btn btn-secondary">Retry</button>
    </div>

    <div v-else class="card">
      <form @submit.prevent="submitOrder">
        <div class="form-section">
          <h3>Customer Information</h3>
          <div class="form-grid two-column">
            <div class="form-group">
              <label>Customer *</label>
              <SearchableSelect v-model="form.customer_id" :options="customerOptions" placeholder="Search customer..." />
            </div>
            <div class="pricing-source-card">
              <span class="eyebrow">Pricing Source</span>
              <strong>{{ customerPriceLabel }}</strong>
              <p>{{ customerRuleMessage }}</p>
            </div>
          </div>
          <div v-if="creditLimitExceeded" class="credit-limit-alert">
            ⚠ <strong>{{ selectedCustomer?.name }}</strong> has ₱15,000 or more in unpaid orders. They must make a payment before new orders can be placed.
          </div>
        </div>

        <div class="form-section">
          <h3>Fulfillment Schedule</h3>
          <div class="form-group">
            <label>Order Priority *</label>
            <div class="radio-group priority-options">
              <label>
                <input v-model="form.order_priority" type="radio" value="regular" />
                Regular Order
              </label>
              <label class="urgent-option">
                <input v-model="form.order_priority" type="radio" value="urgent" />
                Rushed / Urgent Order
              </label>
            </div>
            <p v-if="form.order_priority === 'urgent'" class="priority-note">
              Urgent orders are scheduled for today. You can adjust the time only.
            </p>
          </div>
          <div class="form-grid two-column">
            <div class="form-group">
              <label>Fulfillment Type *</label>
              <div class="radio-group">
                <label><input v-model="form.fulfillment_type" type="radio" value="delivery" /> Delivery</label>
                <label><input v-model="form.fulfillment_type" type="radio" value="pickup" /> Pickup</label>
              </div>
            </div>
            <div v-if="form.order_priority === 'regular'" class="form-group">
              <label>Scheduled Date &amp; Time *</label>
              <input v-model="form.scheduled_for" type="datetime-local" :min="nowMin" required />
            </div>
            <div v-else class="urgent-schedule-grid">
              <div class="form-group">
                <label>Delivery Date *</label>
                <input :value="todayDateInput" type="date" disabled />
              </div>
              <div class="form-group">
                <label>Delivery Time *</label>
                <input v-model="urgentTime" type="time" required @input="syncUrgentSchedule" />
              </div>
            </div>
          </div>
          <div v-if="form.fulfillment_type === 'delivery'" class="form-group">
            <label>Delivery Address *</label>
            <input v-model="form.delivery_address" type="text" placeholder="Enter delivery address" required />
          </div>
        </div>

        <div class="form-section">
          <div class="products-header">
            <h3>Products</h3>
            <button type="button" @click="addProduct" class="btn btn-secondary">+ Add Product</button>
          </div>

          <div v-for="(item, idx) in form.items" :key="idx" class="product-item">
            <div class="product-field product-field-main">
              <label class="mini-label">Product</label>
              <SearchableSelect
                v-model="form.items[idx].product_id"
                :options="productOptions"
                placeholder="Search product..."
                @change="updateProductPrice(idx)"
              />
            </div>
            <div class="product-field">
              <label class="mini-label">Quantity</label>
              <div class="quantity-input-wrap">
                <input
                  v-model.number="form.items[idx].quantity"
                  type="number"
                  placeholder="Qty"
                  :min="getItemMin(item)"
                  :max="getItemMax(item)"
                  :step="getItemStep(item)"
                  required
                  :class="{ 'stock-error': hasInsufficientStock(idx) || Boolean(getItemQuantityError(idx)) }"
                  @input="form.items[idx].quantity = Math.max(0, form.items[idx].quantity || 0)"
                  @blur="normalizeItemQuantity(idx)"
                />
                <span class="unit-suffix">{{ getItemUnit(item) }}</span>
              </div>
              <p class="quantity-helper" :class="{ error: Boolean(getItemQuantityError(idx)) }">
                {{ getItemQuantityError(idx) || getItemQuantityHint(item) }}
              </p>
            </div>
            <div class="product-field">
              <label class="mini-label">Applied Price</label>
              <div class="price-display" :class="{ wholesale: selectedCustomerType === 'wholesale' }">
                <span class="price-label">{{ selectedCustomerType === 'wholesale' ? 'Wholesale price' : 'Retail price' }}</span>
                <strong>{{ formatCurrency(form.items[idx].unit_price) }}</strong>
                <small v-if="item.product_id && selectedCustomerType === 'wholesale'">
                  <template v-if="getItemDiscountPercent(item) > 0">
                    {{ formatPercent(getItemDiscountPercent(item)) }} off retail {{ formatCurrency(getItemRetailPrice(item)) }}
                  </template>
                  <template v-else>
                    No wholesale discount for this product
                  </template>
                </small>
                <small v-else-if="item.product_id">
                  Base retail price
                </small>
                <small v-else>
                  Select a product to load pricing
                </small>
              </div>
            </div>
            <div class="product-field product-field-subtotal">
              <label class="mini-label">Subtotal</label>
              <p class="product-subtotal">{{ formatCurrency(Number(item.quantity || 0) * Number(item.unit_price || 0)) }}</p>
            </div>
            <div class="product-field product-field-remove">
              <label class="mini-label">Remove</label>
              <button type="button" @click="removeItem(idx)" class="btn-delete">✕</button>
            </div>
            <p v-if="hasInsufficientStock(idx)" class="stock-warning">
              Stock not enough. Available: {{ formatAvailableStock(item.product_id) }} {{ getItemUnit(item) }}
            </p>
          </div>
          <p v-if="form.items.length === 0" class="no-items-msg">No products added yet. Click "+ Add Product" to get started.</p>
        </div>

        <div class="form-section">
          <h3>Order Summary</h3>
          <div class="summary">
            <div class="summary-row">
              <span>{{ customerPriceLabel }} subtotal</span>
              <span>{{ formatCurrency(calculateSubtotal()) }}</span>
            </div>
            <div class="summary-row total">
              <span>Total</span>
              <span>{{ formatCurrency(calculateSubtotal()) }}</span>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Notes</label>
          <textarea v-model="form.notes" placeholder="Additional notes..."></textarea>
        </div>

        <p v-if="formError" class="form-error">{{ formError }}</p>

        <div class="form-actions">
          <button v-if="isModal" type="button" class="btn btn-secondary" @click="emit('close')">Cancel</button>
          <router-link v-else to="/orders" class="btn btn-secondary">Cancel</router-link>
          <button type="submit" :disabled="submitting || hasAnyQuantityViolation || creditLimitExceeded" class="btn btn-primary">{{ submitting ? 'Creating Order...' : 'Create Order' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api';
import SearchableSelect from '../../components/SearchableSelect.vue';
import { formatPeso } from '../../utils/currency';
import { findCustomerPricingQuantityViolation, getApiErrorMessage, getCustomerPricingRuleMessage } from '../../utils/orderValidation';
import { resolveCustomerPriceLabel, resolveDiscountedPrice, resolveOrderUnitPrice, resolveRetailPrice } from '../../utils/pricing';

const buildDefaultSchedule = () => {
  const value = new Date();
  value.setMinutes(0, 0, 0);
  value.setHours(value.getHours() + 1);
  const local = new Date(value.getTime() - value.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
};

const toLocalDateTimeInput = (date) => {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
};

const todayDateInput = computed(() => toLocalDateTimeInput(new Date()).slice(0, 10));

const buildSameDaySchedule = (timeValue) => {
  const [hours = '00', minutes = '00'] = String(timeValue || '00:00').split(':');
  const value = new Date();
  value.setHours(Number(hours), Number(minutes), 0, 0);
  return toLocalDateTimeInput(value);
};

const extractTime = (dateTimeValue) => {
  return String(dateTimeValue || buildDefaultSchedule()).slice(11, 16) || '00:00';
};

const nowMin = computed(() => {
  const now = new Date();
  const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
});

const router = useRouter();
const props = defineProps({
  isModal: {
    type: Boolean,
    default: false,
  },
});
const emit = defineEmits(['close', 'created']);

const buildEmptyItem = () => ({ product_id: '', quantity: 1, unit_price: 0 });

const form = ref({
  customer_id: '',
  fulfillment_type: 'delivery',
  order_priority: 'regular',
  scheduled_for: buildDefaultSchedule(),
  delivery_address: '',
  items: [buildEmptyItem()],
  notes: '',
});

const customers = ref([]);
const products = ref([]);
const loading = ref(false);
const error = ref('');
const formError = ref('');
const submitting = ref(false);
const urgentTime = ref(extractTime(form.value.scheduled_for));

const customerOptions = computed(() => customers.value.map((customer) => ({
  value: customer.id,
  label: `${customer.name} (${customer.type || 'retail'})`,
})));

const selectedCustomer = computed(() => customers.value.find((customer) => String(customer.id) === String(form.value.customer_id)) || null);
const selectedCustomerType = computed(() => selectedCustomer.value?.type || 'retail');
const creditLimitExceeded = computed(() => selectedCustomer.value?.credit_limit_exceeded === true);
const customerPriceLabel = computed(() => `${resolveCustomerPriceLabel(selectedCustomerType.value)} pricing`);
const customerRuleMessage = computed(() => getCustomerPricingRuleMessage(selectedCustomerType.value));

const productOptions = computed(() => products.value.map((product) => ({
  value: product.id,
  label: product.name,
  metaRight: `${formatProductStock(product)} kg`,
})));

const getProductStock = (product) => {
  if (!product) return 0;

  const quantity = Number(product.inventory?.quantity ?? 0);
  const quantityOnHand = Number(product.inventory?.quantity_on_hand ?? quantity);
  return Number.isFinite(quantityOnHand) ? Math.max(0, quantityOnHand) : 0;
};

const formatProductStock = (product) => {
  const amount = getProductStock(product);
  return product?.unit_of_measure === 'Per pack'
    ? Number(amount).toLocaleString(undefined, { maximumFractionDigits: 0 })
    : Number(amount).toLocaleString(undefined, { maximumFractionDigits: 2 });
};

const loadFormData = async () => {
  loading.value = true;
  error.value = '';
  try {
    const [customersResponse, productsResponse] = await Promise.all([
      api.get('/customers'),
      api.get('/products', { params: { per_page: 250 } }),
    ]);

    if (customersResponse.data.success) {
      customers.value = customersResponse.data.data || [];
    }

    if (productsResponse.data.success) {
      products.value = productsResponse.data.data || [];
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load form data';
  } finally {
    loading.value = false;
  }
};

const findProduct = (productId) => products.value.find((item) => String(item.id) === String(productId));
const getItemUnit = (item) => findProduct(item.product_id)?.unit_of_measure || 'kg';
const getItemRetailPrice = (item) => resolveRetailPrice(findProduct(item.product_id));
const getItemDiscountPercent = (item) => findProduct(item.product_id) ? Number(findProduct(item.product_id)?.pricing?.[0]?.discount_percent ?? findProduct(item.product_id)?.discount_percent ?? 0) : 0;
const formatCurrency = formatPeso;
const formatPercent = (value) => `${Number(value || 0).toFixed(2)}%`;

const addProduct = () => {
  form.value.items.push(buildEmptyItem());
};

const removeItem = (idx) => {
  form.value.items.splice(idx, 1);
};

const updateProductPrice = (idx) => {
  const item = form.value.items[idx];
  const product = findProduct(item.product_id);
  if (!product) return;

  item.unit_price = resolveOrderUnitPrice(product, selectedCustomerType.value);

  if (getItemUnit(item) === 'kg') {
    if (selectedCustomerType.value === 'wholesale' && Number(item.quantity || 0) < 10) {
      item.quantity = 10;
    }

    if (selectedCustomerType.value !== 'wholesale' && Number(item.quantity || 0) >= 10) {
      item.quantity = 9.99;
    }
  } else if (!item.quantity || Number(item.quantity) < 1) {
    item.quantity = 1;
  }
};

const updateAllPrices = () => {
  form.value.items.forEach((item, idx) => {
    if (item.product_id) {
      updateProductPrice(idx);
    }
  });
};

const getAvailableStock = (productId) => {
  const product = findProduct(productId);
  return getProductStock(product);
};

const formatAvailableStock = (productId) => {
  const amount = getAvailableStock(productId);
  const product = findProduct(productId);
  return product?.unit_of_measure === 'Per pack' ? Number(amount).toFixed(0) : Number(amount).toFixed(2);
};

const hasInsufficientStock = (idx) => {
  const item = form.value.items[idx];
  if (!item?.product_id || !item?.quantity) return false;
  return Number(item.quantity) > getAvailableStock(item.product_id);
};

const hasAnyInsufficientStock = () => form.value.items.some((_, idx) => hasInsufficientStock(idx));

const getItemQuantityError = (idx) => {
  const item = form.value.items[idx];
  if (!item?.product_id) return '';

  return findCustomerPricingQuantityViolation(
    [item],
    selectedCustomerType.value,
    (currentItem) => getItemUnit(currentItem)
  )?.message || '';
};

const getItemQuantityHint = (item) => {
  if (!item?.product_id) {
    return 'Select a product to apply quantity rules.';
  }

  if (getItemUnit(item) !== 'kg') {
    return 'Pack-based products are ordered per pack.';
  }

  return selectedCustomerType.value === 'wholesale'
    ? 'Wholesale orders must be 10kg or more.'
    : 'Retail orders must stay below 10kg.';
};

const hasAnyQuantityViolation = computed(() => form.value.items.some((_, idx) => Boolean(getItemQuantityError(idx))));

const getQuantityRuleError = () => {
  return findCustomerPricingQuantityViolation(
    form.value.items,
    selectedCustomerType.value,
    (item) => getItemUnit(item)
  )?.message || '';
};

const getItemMin = (item) => {
  return getItemUnit(item) === 'Per pack'
    ? 1
    : selectedCustomerType.value === 'wholesale' ? 10 : 0.01;
};

const getItemMax = (item) => {
  if (getItemUnit(item) === 'Per pack') return null;
  return selectedCustomerType.value === 'wholesale' ? null : 9.99;
};

const getItemStep = (item) => getItemUnit(item) === 'Per pack' ? 1 : 0.01;

const normalizeItemQuantity = (idx) => {
  const item = form.value.items[idx];
  if (!item) return;

  const unit = getItemUnit(item);
  const quantity = Number(item.quantity || 0);

  if (unit === 'Per pack') {
    item.quantity = quantity < 1 ? 1 : Math.round(quantity);
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

  if (quantity <= 0) {
    item.quantity = 0.01;
  }
};

watch(() => form.value.customer_id, (customerId) => {
  if (!customerId || form.value.fulfillment_type !== 'delivery') {
    return;
  }

  const customer = customers.value.find((item) => String(item.id) === String(customerId));
  if (customer?.address && !form.value.delivery_address) {
    form.value.delivery_address = customer.address;
  }
});

watch(() => selectedCustomerType.value, () => {
  updateAllPrices();
  form.value.items.forEach((_, idx) => normalizeItemQuantity(idx));
});

watch(() => form.value.fulfillment_type, (type) => {
  if (type === 'pickup') {
    form.value.delivery_address = '';
    return;
  }

  if (selectedCustomer.value?.address && !form.value.delivery_address) {
    form.value.delivery_address = selectedCustomer.value.address;
  }
});

const syncUrgentSchedule = () => {
  form.value.scheduled_for = buildSameDaySchedule(urgentTime.value);
};

watch(() => form.value.order_priority, (priority) => {
  if (priority === 'urgent') {
    urgentTime.value = extractTime(form.value.scheduled_for);
    syncUrgentSchedule();
  }
});

const calculateSubtotal = () => form.value.items.reduce((sum, item) => sum + (Number(item.quantity || 0) * Number(item.unit_price || 0)), 0);

const submitOrder = async () => {
  formError.value = '';

  if (!form.value.customer_id) {
    formError.value = 'Please select a customer.';
    return;
  }

  if (creditLimitExceeded.value) {
    formError.value = `${selectedCustomer.value?.name || 'This customer'} has ₱15,000 or more in unpaid orders. They must make a payment before placing new orders.`;
    return;
  }

  const validItems = form.value.items.filter((item) => item.product_id);
  if (validItems.length === 0) {
    formError.value = 'Please add at least one product to the order.';
    return;
  }

  if (hasAnyInsufficientStock()) {
    formError.value = 'Stock is not enough for one or more products. Please reduce the quantity.';
    return;
  }

  const quantityRuleError = getQuantityRuleError();
  if (quantityRuleError) {
    formError.value = quantityRuleError;
    return;
  }

  if (!form.value.scheduled_for) {
    formError.value = 'Please choose a scheduled date and time.';
    return;
  }

  if (form.value.order_priority === 'urgent') {
    syncUrgentSchedule();
    if (form.value.scheduled_for.slice(0, 10) !== todayDateInput.value) {
      formError.value = 'Rushed / urgent orders must be scheduled for today.';
      return;
    }
  }

  if (new Date(form.value.scheduled_for) < new Date()) {
    formError.value = 'The scheduled date and time cannot be in the past.';
    return;
  }

  if (form.value.fulfillment_type === 'delivery' && !form.value.delivery_address.trim()) {
    formError.value = 'Please provide the delivery address.';
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      customer_id: form.value.customer_id,
      fulfillment_type: form.value.fulfillment_type,
      order_priority: form.value.order_priority,
      scheduled_for: form.value.scheduled_for,
      delivery_address: form.value.fulfillment_type === 'delivery' ? form.value.delivery_address : null,
      notes: form.value.notes,
      items: form.value.items.map((item) => ({
        product_id: item.product_id,
        quantity: Number(item.quantity || 0),
      })),
    };

    const response = await api.post('/orders', payload);
    if (response.data.success) {
      if (props.isModal) {
        emit('created');
        emit('close');
      } else {
        router.push('/orders');
      }
      return;
    }

    formError.value = response.data.error || response.data.message || 'Failed to create order';
  } catch (err) {
    formError.value = getApiErrorMessage(err, 'Failed to create order');
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  loadFormData();
});
</script>

<style scoped>
.create-order-container {
  width: min(100%, 1240px);
  margin: 0 auto;
}

.create-order-container.modal-mode {
  max-width: 100%;
  margin: 0;
  padding: 0 0 28px;
}

.header-section {
  margin-bottom: 16px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-sticky-title {
  position: sticky;
  top: 0;
  z-index: 30;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border-bottom: 1px solid #e7ebf2;
  padding: 20px 32px 18px;
  margin: 0;
  min-height: 72px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
}

.modal-sticky-title h1,
.header-left h1 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #0a1d37;
}

.modal-sticky-close,
.btn-back {
  border: none;
  border-radius: 10px;
  background: #eff2f6;
  color: #0a1d37;
  cursor: pointer;
}

.modal-sticky-close {
  width: 38px;
  height: 38px;
  font-size: 24px;
}

.btn-back {
  padding: 10px 14px;
}

.card {
  background: #fff;
  border-radius: 12px;
  padding: 28px 32px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.modal-mode .card {
  padding: 24px 32px 28px;
  border-radius: 0;
  box-shadow: none;
}

.form-section {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e0e0e0;
  overflow: visible;
}

.form-section h3,
.products-header h3 {
  margin: 0 0 18px;
  font-size: 15px;
  font-weight: 700;
  color: #0a1d37;
  letter-spacing: -0.01em;
}

.form-grid {
  display: grid;
  gap: 16px;
}

.form-grid.two-column {
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 18px;
}

.form-group label {
  font-size: 14px;
  font-weight: 400;
  color: #344054;
}

.form-group input:not([type='checkbox']):not([type='radio']),
.form-group textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #d8dde3;
  border-radius: 10px;
  font-size: 14px;
}

.form-group textarea {
  min-height: 96px;
  resize: vertical;
}

.pricing-source-card {
  display: grid;
  gap: 6px;
  border-radius: 14px;
  background: linear-gradient(135deg, #fff7ed, #ffedd5);
  border: 1px solid #fdba74;
  padding: 16px;
}

.pricing-source-card .eyebrow {
  margin: 0;
  color: #9a3412;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.pricing-source-card strong {
  color: #7c2d12;
  font-size: 18px;
}

.pricing-source-card p {
  margin: 0;
  color: #9a3412;
  font-size: 13px;
}

.radio-group {
  display: flex;
  gap: 18px;
  flex-wrap: wrap;
}

.radio-group label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
}

.radio-group input[type='radio'] {
  flex: 0 0 auto;
  width: 14px;
  height: 14px;
  margin: 0;
}

.priority-options {
  display: flex;
  align-items: center;
  gap: 12px;
}

.priority-options label {
  min-height: 40px;
  min-width: 0;
  width: auto;
  justify-content: flex-start;
  padding: 9px 14px;
  white-space: nowrap;
  border: 1px solid #d8dde3;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
}

.priority-options label:has(input:checked) {
  border-color: #e57c2a;
  background: #fff7ed;
  color: #9a3412;
  font-weight: 800;
}

.priority-options label.urgent-option:has(input:checked) {
  border-color: #fecaca;
  background: #fee2e2;
  color: #b91c1c;
}

.priority-note {
  margin: 0;
  width: fit-content;
  padding: 9px 12px;
  border-radius: 8px;
  background: #fff1f2;
  color: #991b1b;
  font-size: 13px;
  font-weight: 700;
}

.urgent-schedule-grid {
  display: grid;
  grid-template-columns: minmax(140px, 1fr) minmax(120px, 1fr);
  gap: 12px;
}

.products-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.no-items-msg {
  margin: 0;
  padding: 28px 20px;
  text-align: center;
  color: #667085;
  font-size: 14px;
  border: 2px dashed #e4e7ec;
  border-radius: 12px;
}

.product-item {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: minmax(220px, 2.5fr) minmax(190px, 210px) minmax(210px, 1.6fr) minmax(120px, 140px) 60px;
  gap: 0;
  align-items: stretch;
  margin-bottom: 12px;
  padding: 0;
  border: 1px solid #dde3ec;
  border-radius: 14px;
  background: #ffffff;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
  overflow: visible;
}

.product-item:focus-within {
  z-index: 40;
}

.product-field {
  min-width: 0;
  padding: 14px 16px;
  background: #fff;
  display: flex;
  flex-direction: column;
  overflow: visible;
}

.product-field:first-child {
  border-radius: 14px 0 0 14px;
}

.product-field:last-child {
  border-radius: 0 14px 14px 0;
}

.product-field:not(.product-field-main) {
  border-left: 1px solid #eaecf0;
}

.product-field-main {
  background: #f8faff;
  position: relative;
  overflow: visible;
  z-index: 3;
}

.product-field-main :deep(.searchable-select.open) {
  z-index: 60;
}

.product-field-main :deep(.dropdown-panel) {
  z-index: 70;
}

.product-field-subtotal {
  background: #f9fafb;
}

.product-field-remove {
  align-items: center;
  justify-content: center;
  background: #fff5f5;
}

.mini-label {
  display: block;
  margin-bottom: 6px;
  color: #667085;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.quantity-input-wrap {
  position: relative;
}

.quantity-input-wrap input {
  width: 100%;
  padding: 10px 52px 10px 12px;
  border: 1px solid #d8dde3;
  border-radius: 10px;
  font-size: 14px;
  font-family: inherit;
  background: #fff;
}

.unit-suffix {
  position: absolute;
  right: 12px;
  top: 12px;
  color: #667085;
  font-size: 13px;
}

.quantity-helper {
  margin: 8px 0 0;
  color: #667085;
  font-size: 12px;
  line-height: 1.4;
}

.quantity-helper.error {
  color: #b42318;
  font-weight: 600;
}

.price-display {
  display: grid;
  gap: 4px;
  min-height: 86px;
  padding: 12px 14px;
  border: 1px solid #d8dde3;
  border-radius: 12px;
  background: #f8fafc;
}

.price-display.wholesale {
  background: #fff7ed;
  border-color: #fdba74;
}

.price-label {
  color: #667085;
  font-size: 12px;
  font-weight: 600;
}

.price-display strong {
  color: #0a1d37;
  font-size: 16px;
  line-height: 1.1;
}

.price-display small {
  color: #667085;
  font-size: 12px;
  line-height: 1.3;
}

.product-subtotal {
  margin: 0;
  padding: 8px 0 0;
  font-weight: 700;
  color: #0a1d37;
  font-size: 18px;
  line-height: 1.1;
}

.btn-delete {
  width: 34px;
  height: 34px;
  margin-top: 0;
  border: 1px solid #fecdca;
  border-radius: 8px;
  background: #fff1f2;
  color: #b42318;
  cursor: pointer;
  font-size: 13px;
  line-height: 1;
}

.btn-delete:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.stock-warning {
  margin: 0;
  padding: 10px 16px 14px;
  font-size: 13px;
  grid-column: 1 / -1;
  border-top: 1px solid #f1f5f9;
}

.stock-warning {
  color: #b54708;
  background: #fff7ed;
}

.stock-error {
  border-color: #f04438;
}

.summary {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  color: #344054;
}

.summary-row.total {
  padding-top: 10px;
  border-top: 1px solid #e4e7ec;
  font-weight: 700;
  color: #0a1d37;
}

.form-error {
  margin: 0 0 18px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid #fecdca;
  background: #fff1f2;
  color: #b42318;
}

.credit-limit-alert {
  margin-top: 12px;
  padding: 12px 14px;
  border-radius: 8px;
  border: 1px solid #f5c6cb;
  background: #f8d7da;
  color: #721c24;
  font-size: 13px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 10px 16px;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
}

.btn-primary {
  background: #e57c2a;
  color: #fff;
}

.btn-secondary {
  background: #f1f3f5;
  color: #25303d;
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.loading-state,
.error-state {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
}

@media (max-width: 900px) {
  .create-order-container {
    width: 100%;
  }

  .card {
    padding: 24px;
  }

  .product-item {
    grid-template-columns: 1fr;
  }

  .product-field {
    padding: 12px 14px;
  }

  .product-field:not(.product-field-main) {
    border-left: none;
    border-top: 1px solid #eaecf0;
  }

  .form-actions {
    flex-direction: column-reverse;
  }

  .form-actions .btn {
    width: 100%;
  }
}

@media (max-width: 640px) {
  .priority-options {
    align-items: stretch;
    flex-direction: column;
  }

  .priority-options label {
    width: 100%;
  }
}
</style>
