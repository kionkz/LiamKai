<template>
  <div class="pos-container">
    <div class="pos-main">
      <div class="pos-products">
        <h3>Products</h3>
        <input v-model="searchProduct" type="text" placeholder="Search product..." class="search-input" />
        <div class="product-list">
          <div v-for="i in 12" :key="i" class="product-item" @click="addToCart(i)">
            <p class="product-name">Product {{ i }}</p>
            <p class="product-price">₱{{ (Math.random() * 500 + 50).toFixed(2) }}</p>
          </div>
        </div>
      </div>

      <div class="pos-cart">
        <h3>🛒 Current Order</h3>
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
            <button @click="completeTransaction" class="btn btn-primary">Complete</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const searchProduct = ref('');
const cart = ref([]);
const paymentMethod = ref('cash');

const addToCart = (productId) => {
  const price = Math.random() * 500 + 50;
  const existingItem = cart.value.find(item => item.id === productId);
  
  if (existingItem) {
    existingItem.qty++;
  } else {
    cart.value.push({ id: productId, name: `Product ${productId}`, price, qty: 1 });
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

const clearCart = () => {
  cart.value = [];
};

const completeTransaction = () => {
  alert(`Transaction completed\nTotal: ₱${calculateSubtotal().toFixed(2)}\nPayment: ${paymentMethod.value}`);
  clearCart();
  paymentMethod.value = 'cash';
};
</script>

<style scoped>
.pos-container {
  height: calc(100vh - 100px);
  display: flex;
}

.pos-main {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
  height: 100%;
}

.pos-products {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
}

.pos-products h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
}

.search-input {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  margin-bottom: 15px;
  font-size: 14px;
}

.product-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 10px;
  flex: 1;
  overflow-y: auto;
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
}

.pos-cart h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
}

.empty-cart {
  text-align: center;
  color: #999;
  padding: 40px 0;
  flex: 1;
}

.cart-items {
  flex: 1;
  overflow-y: auto;
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
