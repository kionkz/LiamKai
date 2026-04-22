<template>
  <div class="pos-container">
    <div class="pos-main">
      <div class="pos-products">
        <div class="products-topbar">
          <h3>Walk-In</h3>
          <div class="search-block">
            <input
              v-model="searchProduct"
              type="text"
              placeholder="Search product..."
              class="search-input"
              @keyup.enter="applySearch"
            />
            <button type="button" class="btn-filter" @click="applySearch">Filter</button>
          </div>
        </div>

        <div class="category-filter">
          <SearchableSelect
            v-model="selectedCategory"
            :options="categoryOptions"
            placeholder="Filter by category..."
          />
        </div>

        <div v-if="loadingProducts" class="loading-products">Loading products...</div>
        <div v-else-if="loadError" class="loading-products error">{{ loadError }}</div>

        <div class="product-table-wrap">
          <table class="product-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Price</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="product in filteredProducts"
                :key="product.id"
                class="product-row"
                :class="{ 'out-of-stock': getEffectiveStock(product.id) <= 0 }"
                @click="openQtyModal(product)"
              >
                <td class="td-name">{{ product.name }}</td>
                <td class="td-cat">{{ product.category || 'Others' }}</td>
                <td class="td-stock" :class="{ 'stock-zero': getEffectiveStock(product.id) <= 0 }">
                  {{ formatStock(getEffectiveStock(product.id), product.unit) }}
                </td>
                <td class="td-price">{{ formatCurrency(product.price) }}</td>
                <td class="td-add">
                  <button
                    type="button"
                    class="btn-add"
                    :disabled="getEffectiveStock(product.id) <= 0"
                    @click.stop="openQtyModal(product)"
                  >+ Add</button>
                </td>
              </tr>
              <tr v-if="!loadingProducts && !loadError && filteredProducts.length === 0">
                <td colspan="5" class="td-empty">No products found</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="pos-cart">
        <div class="cart-header">
          <h3>Current Order</h3>
          <button v-if="lastReceipt" type="button" class="btn-reprint" @click="printReceipt(lastReceipt)">Reprint Receipt</button>
        </div>
        <div v-if="cart.length === 0" class="empty-cart">
          <p>No items in cart</p>
        </div>
        <div v-else class="cart-items">
          <div v-for="(item, idx) in cart" :key="idx" class="cart-item">
            <div class="item-info">
              <p class="item-name">{{ item.name }}</p>
              <p class="item-price">{{ formatCurrency(item.price) }}</p>
            </div>
            <div class="item-controls">
              <button type="button" @click="decrementQty(idx)">-</button>
              <span class="qty">{{ formatQty(item) }} {{ item.unit }}</span>
              <button type="button" @click="incrementQty(idx)">+</button>
            </div>
            <div class="item-subtotal">
              {{ formatCurrency(item.price * item.qty) }}
            </div>
            <button type="button" @click="removeFromCart(idx)" class="btn-remove">x</button>
          </div>
        </div>

        <div v-if="cart.length > 0" class="cart-summary">
          <div class="summary-row">
            <span>Subtotal:</span>
            <span>{{ formatCurrency(calculateSubtotal()) }}</span>
          </div>
          <div class="summary-row total-row">
            <span>Total:</span>
            <span class="total">{{ formatCurrency(calculateSubtotal()) }}</span>
          </div>

          <div class="payment-options">
            <h4>Payment Method</h4>
            <label><input v-model="paymentMethod" type="radio" value="cash" @change="gcashRef = ''" /> Cash</label>
            <label><input v-model="paymentMethod" type="radio" value="gcash" /> GCash</label>
          </div>

          <div v-if="paymentMethod === 'gcash'" class="form-group gcash-ref-group">
            <label>GCash Reference Number *</label>
            <input v-model="gcashRef" type="text" placeholder="e.g. 1234567890" maxlength="50" />
          </div>

          <div class="cart-actions">
            <button type="button" @click="clearCart" class="btn btn-secondary">Clear</button>
            <button type="button" @click="completeTransaction" class="btn btn-primary"
              :disabled="paymentMethod === 'gcash' && !gcashRef.trim()">
              Complete & Print
            </button>
          </div>
        </div>
      </div>
    </div>
  <!-- Quantity input modal -->
  <div v-if="qtyModal.open" class="qty-overlay" @click.self="closeQtyModal">
    <div class="qty-modal">
      <div class="qty-modal-header">
        <h3>{{ qtyModal.product?.name }}</h3>
        <button type="button" class="qty-modal-close" @click="closeQtyModal">×</button>
      </div>
      <div class="qty-modal-body">
        <p class="qty-modal-meta">{{ formatCurrency(qtyModal.product?.price) }} per {{ qtyModal.product?.unit }}</p>
        <label class="qty-modal-label">
          {{ qtyModal.product?.unit === 'Per pack' ? 'Number of Packs' : 'Quantity (kg)' }}
        </label>
        <input
          ref="qtyInputRef"
          v-model.number="qtyModal.qty"
          type="number"
          class="qty-modal-input"
          :min="qtyModal.product?.unit === 'Per pack' ? 1 : 0.01"
          :step="qtyModal.product?.unit === 'Per pack' ? 1 : 0.01"
          :max="getEffectiveStock(qtyModal.product?.id)"
          @input="qtyModal.qty = Math.max(0, qtyModal.qty || 0)"
          @keyup.enter="confirmQtyModal"
        />
        <p class="qty-modal-stock">
          Available:
          <strong>{{ formatStock(getEffectiveStock(qtyModal.product?.id), qtyModal.product?.unit) }}</strong>
        </p>
        <p v-if="qtyModalError" class="qty-modal-error">{{ qtyModalError }}</p>
      </div>
      <div class="qty-modal-actions">
        <button type="button" class="btn btn-secondary" @click="closeQtyModal">Cancel</button>
        <button type="button" class="btn btn-primary" @click="confirmQtyModal">Add to Cart</button>
      </div>
    </div>
  </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import api from '../../api';
import SearchableSelect from '../../components/SearchableSelect.vue';
import { useAuthStore } from '../../stores/authStore';
import { exportReceiptPdf } from '../../utils/receiptPdf';
import { resolveRetailPrice } from '../../utils/pricing';

const searchProduct = ref('');
const selectedCategory = ref('All');
const cart = ref([]);
const paymentMethod = ref('cash');
const gcashRef = ref('');
const products = ref([]);
const loadingProducts = ref(false);
const loadError = ref('');
const lastReceipt = ref(null);

const qtyModal = ref({ open: false, product: null, qty: 1 });
const qtyModalError = ref('');
const qtyInputRef = ref(null);

const authStore = useAuthStore();

const categories = computed(() => {
  const uniqueCategories = [...new Set(products.value.map((product) => product.category || 'Others'))];
  return ['All', ...uniqueCategories];
});

const categoryOptions = computed(() => categories.value.map((cat) => ({ value: cat, label: cat })));

const normalizeCategory = (value) => String(value || '').trim().toLowerCase();

const filteredProducts = computed(() => {
  const keyword = searchProduct.value.trim().toLowerCase();
  const isAll = !selectedCategory.value || normalizeCategory(selectedCategory.value) === 'all';

  return products.value.filter((product) => {
    const productCategory = normalizeCategory(product.category || 'Others');
    const matchesCategory = isAll || productCategory === normalizeCategory(selectedCategory.value);
    const matchesSearch = !keyword || String(product.name || '').toLowerCase().includes(keyword);
    return matchesCategory && matchesSearch;
  });
});

const getEffectiveStock = (productId) => {
  const product = products.value.find((p) => p.id === productId);
  if (!product) return 0;
  const inCart = cart.value.find((item) => item.id === productId)?.qty || 0;
  return Math.max(0, product.stock - inCart);
};

const openQtyModal = (product) => {
  if (getEffectiveStock(product.id) <= 0) return;
  const defaultQty = product.unit === 'Per pack' ? 1 : 0.5;
  qtyModal.value = { open: true, product, qty: defaultQty };
  qtyModalError.value = '';
  nextTick(() => qtyInputRef.value?.focus());
};

const closeQtyModal = () => {
  qtyModal.value.open = false;
  qtyModalError.value = '';
};

const confirmQtyModal = () => {
  qtyModalError.value = '';
  const { product, qty } = qtyModal.value;
  if (!product) return;

  const quantity = Number(qty || 0);
  const unit = product.unit || 'kg';
  const min = unit === 'Per pack' ? 1 : 0.01;

  if (quantity < min) {
    qtyModalError.value = `Minimum quantity is ${min} ${unit}.`;
    return;
  }

  const effectiveStock = getEffectiveStock(product.id);
  if (quantity > effectiveStock) {
    qtyModalError.value = `Not enough stock. Available: ${formatStock(effectiveStock, unit)}`;
    return;
  }

  addToCart(product, quantity);
  closeQtyModal();
};

const applySearch = () => {
  searchProduct.value = searchProduct.value.trim();
};

const loadProducts = async () => {
  loadingProducts.value = true;
  loadError.value = '';

  try {
    const response = await api.get('/products', { params: { per_page: 100 } });
    if (response.data?.success) {
      products.value = (response.data.data || []).map((product) => ({
        ...product,
        price: resolveRetailPrice(product),
        stock: Number(product.inventory?.quantity_on_hand ?? product.inventory?.quantity ?? 0),
        unit: product.unit_of_measure || 'kg',
      }));
      return;
    }

    loadError.value = response.data?.message || 'Failed to load products';
  } catch (err) {
    loadError.value = err.response?.data?.message || 'Failed to load products';
  } finally {
    loadingProducts.value = false;
  }
};

const formatStock = (value, unit = 'kg') => {
  const quantity = unit === 'Per pack' ? Number(value || 0).toFixed(0) : Number(value || 0).toFixed(2);
  return `${quantity} ${unit} available`;
};

const addToCart = (product, qty) => {
  const quantity = Number(qty || 1);
  const existingItem = cart.value.find((item) => item.id === product.id);

  if (existingItem) {
    existingItem.qty = Number((existingItem.qty + quantity).toFixed(2));
    return;
  }

  cart.value.push({
    id: product.id,
    name: product.name,
    price: Number(product.price || 0),
    qty: quantity,
    unit: product.unit || 'kg',
  });
};

const formatQty = (item) => {
  return item.unit === 'Per pack'
    ? Number(item.qty || 0).toFixed(0)
    : Number(item.qty || 0).toFixed(2);
};

const removeFromCart = (idx) => {
  cart.value.splice(idx, 1);
};

const incrementQty = (idx) => {
  const item = cart.value[idx];
  const product = products.value.find((p) => p.id === item.id);
  const effectiveStock = product ? product.stock : Infinity;
  const step = item.unit === 'Per pack' ? 1 : 0.01;
  const newQty = Number((item.qty + step).toFixed(2));
  if (newQty > effectiveStock) return;
  item.qty = newQty;
};

const decrementQty = (idx) => {
  const item = cart.value[idx];
  const step = item.unit === 'Per pack' ? 1 : 0.01;
  const min = step;
  const newQty = Number((item.qty - step).toFixed(2));
  if (newQty < min) return;
  item.qty = newQty;
};

const clearCart = () => {
  cart.value = [];
};

const calculateSubtotal = () => cart.value.reduce((sum, item) => sum + (item.price * item.qty), 0);

const formatCurrency = (amount) => `PHP ${Number(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatPaymentMethodLabel = (method) => {
  const labels = { cash: 'Cash', gcash: 'GCash' };
  return labels[method] || String(method || '').toUpperCase();
};

const buildReceiptData = () => {
  const issuedAt = new Date();
  const subtotal = calculateSubtotal();

  return {
    receiptNumber: `POS-${issuedAt.getFullYear()}${String(issuedAt.getMonth() + 1).padStart(2, '0')}${String(issuedAt.getDate()).padStart(2, '0')}-${issuedAt.getTime().toString().slice(-6)}`,
    issuedAt: issuedAt.toLocaleString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    }),
    cashier: authStore.user?.name || authStore.user?.username || 'Cashier',
    paymentMethod: formatPaymentMethodLabel(paymentMethod.value),
    gcashRef: paymentMethod.value === 'gcash' ? gcashRef.value.trim() : null,
    items: cart.value.map((item) => ({
      name: item.name,
      qty: Number(item.qty || 0),
      price: Number(item.price || 0),
      subtotal: Number(item.price || 0) * Number(item.qty || 0),
    })),
    subtotal,
    total: subtotal,
  };
};

const printReceipt = (receipt = buildReceiptData()) => {
  exportReceiptPdf({
    title: 'Walk-In POS Receipt',
    subtitle: 'LiamKai',
    filename: `${receipt.receiptNumber}.pdf`,
    meta: [
      { label: 'Receipt No.', value: receipt.receiptNumber },
      { label: 'Date', value: receipt.issuedAt },
      { label: 'Cashier', value: receipt.cashier },
      { label: 'Payment', value: receipt.paymentMethod },
      ...(receipt.gcashRef ? [{ label: 'GCash Ref', value: receipt.gcashRef }] : []),
    ],
    items: receipt.items.map((item) => ({
      name: item.name,
      qty: item.qty,
      unitPrice: formatCurrency(item.price),
      amount: formatCurrency(item.subtotal),
    })),
    totals: [
      { label: 'Subtotal', value: formatCurrency(receipt.subtotal) },
      { label: 'Total', value: formatCurrency(receipt.total) },
    ],
  });
};

const completeTransaction = () => {
  if (!cart.value.length) return;
  if (paymentMethod.value === 'gcash' && !gcashRef.value.trim()) return;

  const receipt = buildReceiptData();
  lastReceipt.value = receipt;
  printReceipt(receipt);
  clearCart();
  gcashRef.value = '';
  paymentMethod.value = 'cash';
};

onMounted(() => {
  loadProducts();
});
</script>

<style scoped>
.pos-container {
  height: 100%;
  min-height: 0;
}

.pos-main {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
  height: 100%;
  min-height: 0;
}

.pos-products {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}

.pos-products h3 {
  margin: 0;
  color: #0a1d37;
}

.products-topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.search-block {
  display: flex;
  gap: 8px;
  align-items: center;
}

.search-input {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 999px;
  font-size: 14px;
  min-width: 240px;
}

.btn-filter {
  border: none;
  border-radius: 8px;
  background: #e57c2a;
  color: #fff;
  font-weight: 700;
  height: 38px;
  padding: 0 16px;
  cursor: pointer;
}

.category-filter {
  margin-bottom: 14px;
}

.loading-products {
  font-size: 13px;
  color: #666;
  margin-bottom: 10px;
}

.loading-products.error {
  color: #c33;
}

.product-table-wrap {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
}

.product-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.product-table thead tr {
  background: #f3f6fb;
  position: sticky;
  top: 0;
  z-index: 2;
}

.product-table th {
  padding: 11px 14px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: #667085;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  border-bottom: 1px solid #e4e7ec;
  white-space: nowrap;
}

.product-table td {
  padding: 12px 14px;
  border-bottom: 1px solid #f1f4f8;
  vertical-align: middle;
}

.product-table tbody tr:last-child td {
  border-bottom: none;
}

.product-row {
  cursor: pointer;
  transition: background 0.12s;
}

.product-row:hover {
  background: #fef6ee;
}

.td-name {
  font-weight: 600;
  color: #0a1d37;
}

.td-cat {
  color: #667085;
  font-size: 13px;
}

.td-stock {
  color: #5b6575;
  font-size: 13px;
  white-space: nowrap;
}

.td-price {
  font-weight: 700;
  color: #e57c2a;
  white-space: nowrap;
}

.td-add {
  text-align: right;
}

.btn-add {
  padding: 6px 14px;
  border: 1px solid #e57c2a;
  border-radius: 8px;
  background: #fff7f0;
  color: #e57c2a;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.12s;
}

.btn-add:hover {
  background: #e57c2a;
  color: #fff;
}

.btn-add:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.td-empty {
  text-align: center;
  padding: 32px;
  color: #9caab8;
  font-size: 14px;
}

.stock-zero {
  color: #b42318;
  font-weight: 600;
}

.out-of-stock {
  opacity: 0.5;
  pointer-events: none;
}

/* ── Quantity Modal ─────────────────────────────── */
.qty-overlay {
  position: fixed;
  inset: 0;
  background: rgba(10, 29, 55, 0.45);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qty-modal {
  background: #fff;
  border-radius: 18px;
  width: min(420px, 94vw);
  box-shadow: 0 24px 60px rgba(10, 29, 55, 0.18);
  overflow: hidden;
}

.qty-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #e4e7ec;
}

.qty-modal-header h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #0a1d37;
}

.qty-modal-close {
  width: 34px;
  height: 34px;
  border: none;
  border-radius: 8px;
  background: #f1f4f8;
  color: #0a1d37;
  font-size: 22px;
  line-height: 1;
  cursor: pointer;
}

.qty-modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.qty-modal-meta {
  margin: 0;
  font-size: 13px;
  color: #667085;
}

.qty-modal-label {
  font-size: 13px;
  font-weight: 600;
  color: #344054;
}

.qty-modal-input {
  width: 100%;
  padding: 14px 16px;
  border: 1px solid #d8dde3;
  border-radius: 12px;
  font-size: 22px;
  font-weight: 700;
  color: #0a1d37;
  text-align: center;
  box-sizing: border-box;
}

.qty-modal-input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.18);
}

.qty-modal-stock {
  margin: 0;
  font-size: 13px;
  color: #667085;
}

.qty-modal-error {
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: #fff1f2;
  border: 1px solid #fecdca;
  color: #b42318;
  font-size: 13px;
  font-weight: 600;
}

.qty-modal-actions {
  display: flex;
  gap: 10px;
  padding: 16px 24px 24px;
}

.pos-cart {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.pos-cart h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
}

.cart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 15px;
}

.cart-header h3 {
  margin-bottom: 0;
}

.btn-reprint {
  border: 1px solid #d8dde3;
  background: #f8fafc;
  color: #27344d;
  border-radius: 999px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-reprint:hover {
  background: #eef2f7;
}

.empty-cart {
  text-align: center;
  color: #999;
  padding: 40px 0;
  flex: 1;
}

.cart-items {
  flex: 1;
  overflow: auto;
  margin-bottom: 15px;
}

.cart-item {
  display: grid;
  grid-template-columns: 1fr auto auto auto;
  gap: 10px;
  align-items: center;
  padding: 10px;
  background-color: #f9f9f9;
  border-radius: 6px;
  margin-bottom: 10px;
  font-size: 12px;
}

.item-info {
  margin: 0;
}

.item-name {
  margin: 0;
  font-weight: 600;
  color: #0a1d37;
}

.item-price {
  margin: 3px 0 0 0;
  color: #666;
  font-size: 11px;
}

.item-controls {
  display: flex;
  gap: 5px;
  align-items: center;
}

.item-controls button {
  padding: 2px 6px;
  border: 1px solid #ddd;
  background: white;
  cursor: pointer;
  border-radius: 3px;
  font-weight: bold;
}

.qty {
  min-width: 20px;
  text-align: center;
}

.item-subtotal {
  font-weight: 600;
  color: #e57c2a;
  text-align: right;
}

.btn-remove {
  background: #fee;
  color: #c33;
  border: none;
  width: 24px;
  height: 24px;
  border-radius: 3px;
  cursor: pointer;
  font-weight: bold;
}

.cart-summary {
  border-top: 2px solid #e0e0e0;
  padding-top: 15px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
  font-size: 13px;
}

.total-row {
  border-top: 1px solid #e0e0e0;
  padding-top: 10px;
  margin-top: 4px;
}

.summary-row .total {
  font-weight: 700;
  font-size: 18px;
  color: #e57c2a;
}

.gcash-ref-group {
  margin: 8px 0 4px;
}

.gcash-ref-group label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #444;
  margin-bottom: 5px;
}

.gcash-ref-group input {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 13px;
  box-sizing: border-box;
}

.gcash-ref-group input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 2px rgba(229,124,42,0.15);
}

.payment-options {
  margin: 15px 0 8px;
  padding: 10px 0;
  border-top: 1px solid #e0e0e0;
}

.payment-options h4 {
  margin: 0 0 8px 0;
  font-size: 12px;
  color: #666;
  text-transform: uppercase;
}

.payment-options label {
  display: flex;
  align-items: center;
  margin: 5px 0;
  font-size: 12px;
  cursor: pointer;
}

.payment-options input[type='radio'] {
  margin-right: 8px;
}

.cart-actions {
  display: flex;
  gap: 10px;
  margin-top: 15px;
}

.btn {
  padding: 8px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  font-size: 12px;
  flex: 1;
  transition: all 0.3s;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #d46a1a;
}

.btn-primary:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}

.btn-secondary {
  background-color: #f0f0f0;
  color: #333;
  border: 1px solid #ddd;
}

.btn-secondary:hover {
  background-color: #e0e0e0;
}

@media (max-width: 1024px) {
  .pos-main {
    grid-template-columns: 1fr;
  }
}
</style>
