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

        <div class="category-section">
          <h4>Category</h4>
          <div class="category-list">
            <button
              v-for="category in categories"
              :key="category"
              type="button"
              class="category-chip"
              :class="{ active: isCategorySelected(category) }"
              @click="toggleCategoryFilter(category)"
            >
              <span class="category-dot">{{ categoryInitial(category) }}</span>
              <span>{{ category }}</span>
            </button>
          </div>
        </div>

        <div v-if="loadingProducts" class="loading-products">Loading products...</div>
        <div v-else-if="loadError" class="loading-products error">{{ loadError }}</div>
        <div class="product-list">
          <div v-for="product in filteredProducts" :key="product.id" class="product-item" @click="addToCart(product)">
            <p class="product-name">{{ product.name }}</p>
            <p class="product-price">₱{{ product.price.toFixed(2) }}</p>
          </div>
          <div v-if="!loadingProducts && !loadError && filteredProducts.length === 0" class="empty-products">
            <p>No products found</p>
          </div>
        </div>
      </div>

      <div class="pos-cart">
        <div class="cart-header">
          <h3>🛒 Current Order</h3>
          <button v-if="lastReceipt" type="button" class="btn-reprint" @click="printReceipt(lastReceipt)">Reprint Receipt</button>
        </div>
        <div v-if="cart.length === 0" class="empty-cart">
          <p>No items in cart</p>
        </div>
        <div v-else class="cart-items">
          <div v-for="(item, idx) in cart" :key="idx" class="cart-item">
            <div class="item-info">
              <p class="item-name">{{ item.name }}</p>
              <p class="item-price">₱{{ item.price.toFixed(2) }}</p>
            </div>
            <div class="item-controls">
              <button @click="decrementQty(idx)">-</button>
              <span class="qty">{{ item.qty }}</span>
              <button @click="incrementQty(idx)">+</button>
            </div>
            <div class="item-subtotal">
              ₱{{ (item.price * item.qty).toFixed(2) }}
            </div>
            <button @click="removeFromCart(idx)" class="btn-remove">✕</button>
          </div>
        </div>

        <div v-if="cart.length > 0" class="cart-summary">
          <div class="summary-row">
            <span>Subtotal:</span>
            <span>₱{{ calculateSubtotal().toFixed(2) }}</span>
          </div>
          <div class="summary-row">
            <span>Total:</span>
            <span class="total">₱{{ calculateSubtotal().toFixed(2) }}</span>
          </div>

          <div class="payment-options">
            <h4>Payment Method</h4>
            <label><input v-model="paymentMethod" type="radio" value="cash" /> Cash</label>
            <label><input v-model="paymentMethod" type="radio" value="card" /> Card</label>
            <label><input v-model="paymentMethod" type="radio" value="cod" /> COD</label>
          </div>

          <div class="cart-actions">
            <button @click="clearCart" class="btn btn-secondary">Clear</button>
            <button @click="completeTransaction" class="btn btn-primary">Complete & Print</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api';
import { useAuthStore } from '../../stores/authStore';

const searchProduct = ref('');
const selectedCategories = ref(['All']);
const cart = ref([]);
const paymentMethod = ref('cash');
const products = ref([]);
const loadingProducts = ref(false);
const loadError = ref('');
const lastReceipt = ref(null);

const authStore = useAuthStore();

const categories = computed(() => {
  const uniqueCategories = [...new Set(products.value.map((product) => product.category || 'Others'))];
  return ['All', ...uniqueCategories];
});

const categoryInitial = (category) => String(category || 'A').charAt(0).toUpperCase();
const normalizeCategory = (value) => String(value || '').trim().toLowerCase();

const filteredProducts = computed(() => {
  const keyword = searchProduct.value.trim().toLowerCase();

  const selectedSet = new Set(selectedCategories.value.map((category) => normalizeCategory(category)));
  const isAllSelected = selectedSet.has('all') || selectedSet.size === 0;

  return products.value.filter((product) => {
    const productCategory = normalizeCategory(product.category || 'Others');
    const matchesCategory = isAllSelected || selectedSet.has(productCategory);
    const matchesSearch = !keyword || String(product.name || '').toLowerCase().includes(keyword);

    return matchesCategory && matchesSearch;
  });
});

const isCategorySelected = (category) => {
  return selectedCategories.value.includes(category);
};

const toggleCategoryFilter = (category) => {
  if (category === 'All') {
    selectedCategories.value = ['All'];
    return;
  }

  const withoutAll = selectedCategories.value.filter((item) => item !== 'All');
  if (withoutAll.includes(category)) {
    const next = withoutAll.filter((item) => item !== category);
    selectedCategories.value = next.length ? next : ['All'];
    return;
  }

  selectedCategories.value = [...withoutAll, category];
};

const applySearch = () => {
  // Typing already filters live; clicking Filter normalizes input.
  searchProduct.value = searchProduct.value.trim();
};

const resolvePrice = (product) => {
  const pricing = product.pricing?.[0];
  const retail = Number(pricing?.retail_price);
  const base = Number(product.base_price);

  if (!Number.isNaN(retail) && retail > 0) return retail;
  if (!Number.isNaN(base) && base > 0) return base;
  return 0;
};

const loadProducts = async () => {
  loadingProducts.value = true;
  loadError.value = '';

  try {
    const response = await api.get('/products');
    if (response.data?.success) {
      products.value = (response.data.data || []).map((product) => ({
        ...product,
        price: resolvePrice(product),
      }));
    } else {
      loadError.value = response.data?.message || 'Failed to load products';
    }
  } catch (err) {
    loadError.value = err.response?.data?.message || 'Failed to load products';
  } finally {
    loadingProducts.value = false;
  }
};

const addToCart = (product) => {
  const existingItem = cart.value.find((item) => item.id === product.id);

  if (existingItem) {
    existingItem.qty++;
  } else {
    cart.value.push({
      id: product.id,
      name: product.name,
      price: Number(product.price || 0),
      qty: 1,
    });
  }
};

const removeFromCart = (idx) => {
  cart.value.splice(idx, 1);
};

const incrementQty = (idx) => {
  cart.value[idx].qty++;
};

const decrementQty = (idx) => {
  if (cart.value[idx].qty > 1) {
    cart.value[idx].qty--;
  }
};

const calculateSubtotal = () => {
  return cart.value.reduce((sum, item) => sum + (item.price * item.qty), 0);
};

const formatCurrency = (amount) => `₱${Number(amount || 0).toFixed(2)}`;

const formatPaymentMethodLabel = (method) => {
  const labels = {
    cash: 'Cash',
    card: 'Card',
    cod: 'COD',
  };

  return labels[method] || String(method || '').toUpperCase();
};

const escapeHtml = (value) => String(value ?? '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#39;');

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

const buildReceiptHtml = (receipt) => {
  const itemsMarkup = receipt.items.map((item) => `
      <tr>
        <td>${escapeHtml(item.name)}</td>
        <td class="num">${item.qty}</td>
        <td class="num">${formatCurrency(item.price)}</td>
        <td class="num">${formatCurrency(item.subtotal)}</td>
      </tr>
    `).join('');

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Walk-In POS Receipt</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 24px; color: #111827; }
    .receipt { max-width: 420px; margin: 0 auto; }
    .header { text-align: center; margin-bottom: 16px; }
    .header h1 { margin: 0 0 4px; font-size: 24px; }
    .header p { margin: 0; color: #4b5563; font-size: 12px; }
    .meta { margin: 16px 0; font-size: 12px; }
    .meta-row { display: flex; justify-content: space-between; margin-bottom: 4px; gap: 12px; }
    table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 12px; }
    th, td { border-bottom: 1px dashed #d1d5db; padding: 8px 0; text-align: left; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .totals { margin-top: 16px; }
    .total-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
    .grand-total { font-weight: 700; font-size: 16px; border-top: 2px solid #111827; padding-top: 8px; }
    .footer { margin-top: 20px; text-align: center; color: #4b5563; font-size: 12px; }
    @media print {
      body { padding: 0; }
      .receipt { max-width: none; }
    }
  </style>
</head>
<body>
  <div class="receipt">
    <div class="header">
      <h1>LiamKai</h1>
      <p>Walk-In POS Receipt</p>
    </div>

    <div class="meta">
      <div class="meta-row"><span>Receipt No.</span><span>${escapeHtml(receipt.receiptNumber)}</span></div>
      <div class="meta-row"><span>Date</span><span>${escapeHtml(receipt.issuedAt)}</span></div>
      <div class="meta-row"><span>Cashier</span><span>${escapeHtml(receipt.cashier)}</span></div>
      <div class="meta-row"><span>Payment</span><span>${escapeHtml(receipt.paymentMethod)}</span></div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Item</th>
          <th class="num">Qty</th>
          <th class="num">Price</th>
          <th class="num">Amount</th>
        </tr>
      </thead>
      <tbody>${itemsMarkup}</tbody>
    </table>

    <div class="totals">
      <div class="total-row"><span>Subtotal</span><span>${formatCurrency(receipt.subtotal)}</span></div>
      <div class="total-row grand-total"><span>Total</span><span>${formatCurrency(receipt.total)}</span></div>
    </div>

    <div class="footer">Thank you for your purchase.</div>
  </div>
  <script>
    window.addEventListener('load', function () {
      window.print();
    });
    window.addEventListener('afterprint', function () {
      window.close();
    });
  <\/script>
</body>
</html>`;
};

const printReceipt = (receipt) => {
  const printWindow = window.open('', '_blank', 'width=520,height=720');

  if (!printWindow) {
    alert('Unable to open the print window. Please allow pop-ups and try again.');
    return false;
  }

  printWindow.document.open();
  printWindow.document.write(buildReceiptHtml(receipt));
  printWindow.document.close();
  return true;
};

const clearCart = () => {
  cart.value = [];
};

const completeTransaction = () => {
  const receipt = buildReceiptData();
  lastReceipt.value = receipt;

  if (!printReceipt(receipt)) {
    return;
  }

  clearCart();
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
  display: flex;
  flex-direction: column;
  overflow: hidden;
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

.category-section {
  margin-bottom: 14px;
}

.category-section h4 {
  margin: 0 0 8px;
  color: #0a1d37;
  font-size: 22px;
}

.category-list {
  display: flex;
  gap: 8px;
  overflow-x: auto;
}

.category-chip {
  border: 1px solid transparent;
  border-radius: 10px;
  background: #f3f5f8;
  color: #27344d;
  width: 90px;
  min-width: 90px;
  padding: 8px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.category-chip.active {
  border-color: #e57c2a;
}

.category-dot {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  background: linear-gradient(145deg, #42a5d9, #1f5f96);
  color: #fff;
  font-size: 22px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.product-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 10px;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.loading-products {
  font-size: 13px;
  color: #666;
  margin-bottom: 10px;
}

.loading-products.error {
  color: #c33;
}

.empty-products {
  grid-column: 1 / -1;
  text-align: center;
  color: #999;
  padding: 20px 0;
}

.product-item {
  background-color: #f9f9f9;
  padding: 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  border: 2px solid transparent;
  text-align: center;
}

.product-item:hover {
  background-color: #e57c2a;
  color: white;
  border-color: #d46a1a;
}

.product-name {
  margin: 0 0 8px 0;
  font-weight: 600;
  font-size: 13px;
}

.product-price {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
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
  overflow: hidden;
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
  margin-bottom: 10px;
  font-size: 13px;
}

.summary-row .total {
  font-weight: 700;
  font-size: 16px;
  color: #e57c2a;
}

.payment-options {
  margin: 15px 0;
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

.payment-options input[type="radio"] {
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

.btn-primary:hover {
  background-color: #d46a1a;
}

.btn-secondary {
  background-color: #f0f0f0;
  color: #333;
  border: 1px solid #ddd;
}

.btn-secondary:hover {
  background-color: #e0e0e0;
}
</style>
