<template>
  <div class="receiving-container">
    <div class="header-section">
      <router-link to="/purchasing" class="link-back">← Back to POs</router-link>
      <h1>Receiving Report - PO #{{ poNumber }}</h1>
    </div>

    <div class="po-info-card">
      <div class="info-row">
        <div class="info-group">
          <p class="label">PO Number</p>
          <p class="value">{{ poNumber }}</p>
        </div>
        <div class="info-group">
          <p class="label">Supplier</p>
          <p class="value">ABC Suppliers Inc.</p>
        </div>
        <div class="info-group">
          <p class="label">Original Date</p>
          <p class="value">{{ originalDate }}</p>
        </div>
      </div>
    </div>

    <form @submit.prevent="submitReceiving" class="receiving-form">
      <div class="form-section">
        <h2>Received Items</h2>
        
        <table class="items-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Ordered Qty</th>
              <th>Received Qty</th>
              <th>Damaged</th>
              <th>Short</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in items" :key="idx">
              <td>{{ item.product }}</td>
              <td>{{ item.ordered }}</td>
              <td>
                <input v-model.number="item.received" type="number" min="0" :max="item.ordered" required />
              </td>
              <td>
                <input v-model.number="item.damaged" type="number" min="0" :max="item.ordered" />
              </td>
              <td>
                <input v-model.number="item.short" type="number" min="0" :max="item.ordered" @change="calculateShort(idx)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="form-section">
        <h2>Damage & Shortage Notes</h2>
        <div class="form-group">
          <label>Damage Details</label>
          <textarea v-model="formData.damageNotes" rows="3" placeholder="Describe any damaged items..."></textarea>
        </div>

        <div class="form-group">
          <label>Shortage Details</label>
          <textarea v-model="formData.shortageNotes" rows="3" placeholder="Describe any missing items..."></textarea>
        </div>

        <div class="form-group">
          <label>Receiving Notes</label>
          <textarea v-model="formData.notes" rows="3" placeholder="General receiving notes..."></textarea>
        </div>
      </div>

      <div class="form-section">
        <h2>Confirmation</h2>
        <div class="check-group">
          <input v-model="formData.inspected" type="checkbox" id="inspected" required />
          <label for="inspected">Items have been physically inspected and counted</label>
        </div>

        <div class="check-group">
          <input v-model="formData.storageConfirmed" type="checkbox" id="storage" required />
          <label for="storage">Items have been stored in designated locations</label>
        </div>

        <div class="check-group">
          <input v-model="formData.recipientName" type="text" placeholder="Receiving Person Name (required)" required />
        </div>
      </div>

      <div class="summary-section">
        <div class="summary-row">
          <span>Total Items Ordered:</span>
          <span>{{ totalOrdered }}</span>
        </div>
        <div class="summary-row">
          <span>Total Received:</span>
          <span>{{ totalReceived }}</span>
        </div>
        <div class="summary-row alert-row" v-if="totalDamaged > 0">
          <span>Total Damaged:</span>
          <span>{{ totalDamaged }}</span>
        </div>
        <div class="summary-row alert-row" v-if="totalShort > 0">
          <span>Total Short:</span>
          <span>{{ totalShort }}</span>
        </div>
      </div>

      <div class="form-actions">
        <router-link to="/purchasing" class="btn btn-secondary">Cancel</router-link>
        <button type="submit" class="btn btn-primary">Confirm Receipt</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();

const poNumber = ref(`PO-${1000 + parseInt(route.params.id || 0)}`);
const originalDate = ref(new Date(Date.now() - 86400000 * 3).toLocaleDateString());

const items = ref([
  { product: 'Product A', ordered: 50, received: 0, damaged: 0, short: 0 },
  { product: 'Product B', ordered: 30, received: 0, damaged: 0, short: 0 },
  { product: 'Product C', ordered: 100, received: 0, damaged: 0, short: 0 },
  { product: 'Product D', ordered: 25, received: 0, damaged: 0, short: 0 },
]);

const formData = ref({
  damageNotes: '',
  shortageNotes: '',
  notes: '',
  inspected: false,
  storageConfirmed: false,
  recipientName: '',
});

const totalOrdered = computed(() => items.value.reduce((sum, item) => sum + item.ordered, 0));
const totalReceived = computed(() => items.value.reduce((sum, item) => sum + item.received, 0));
const totalDamaged = computed(() => items.value.reduce((sum, item) => sum + item.damaged, 0));
const totalShort = computed(() => items.value.reduce((sum, item) => sum + item.short, 0));

const calculateShort = (idx) => {
  const item = items.value[idx];
  const max = item.ordered - item.received;
  if (item.short > max) {
    item.short = max;
  }
};

const submitReceiving = () => {
  console.log('Receiving report:', { items: items.value, formData: formData.value });
  alert('Receiving report submitted successfully!');
  router.push('/purchasing');
};
</script>

<style scoped>
.receiving-container {
  animation: fadeIn 0.3s ease-in;
}

.header-section {
  margin-bottom: 20px;
}

.link-back {
  display: inline-block;
  color: #e57c2a;
  text-decoration: none;
  font-weight: 500;
  margin-bottom: 10px;
  transition: color 0.3s;
}

.link-back:hover {
  color: #d46a1a;
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
}

.po-info-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 25px;
}

.info-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}

.info-group {
  margin: 0;
}

.info-group .label {
  margin: 0;
  font-size: 12px;
  text-transform: uppercase;
  color: #999;
  font-weight: 600;
}

.info-group .value {
  margin: 5px 0 0 0;
  font-size: 16px;
  color: #0a1d37;
  font-weight: 600;
}

.receiving-form {
  background: white;
  border-radius: 8px;
  padding: 25px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.form-section {
  margin-bottom: 30px;
}

.form-section h2 {
  margin-top: 0;
  margin-bottom: 15px;
  color: #0a1d37;
  font-size: 16px;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}

.items-table thead {
  background-color: #f9f9f9;
}

.items-table th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  color: #666;
  border-bottom: 2px solid #e0e0e0;
}

.items-table td {
  padding: 12px;
  border-bottom: 1px solid #e0e0e0;
}

.items-table input {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.items-table input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 2px rgba(229, 124, 42, 0.1);
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

.form-group input[type="text"],
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  font-family: inherit;
}

.form-group textarea:focus,
.form-group input[type="text"]:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.check-group {
  margin-bottom: 12px;
  display: flex;
  align-items: center;
}

.check-group input[type="checkbox"] {
  margin-right: 10px;
  cursor: pointer;
  width: 18px;
  height: 18px;
}

.check-group label {
  margin: 0;
  cursor: pointer;
  font-size: 14px;
  color: #333;
}

.check-group input[type="text"] {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.check-group input[type="text"]:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.1);
}

.summary-section {
  background-color: #f9f9f9;
  padding: 20px;
  border-radius: 6px;
  margin-bottom: 25px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
  font-weight: 600;
  color: #0a1d37;
}

.summary-row.alert-row {
  color: #d32f2f;
}

.form-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  text-decoration: none;
  display: inline-block;
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
}

.btn-secondary:hover {
  background-color: #e0e0e0;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
