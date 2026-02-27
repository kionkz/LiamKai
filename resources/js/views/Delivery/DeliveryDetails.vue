<template>
  <div class="delivery-details">
    <div class="card">
      <div class="header">
        <h1>Delivery #DLV-4001</h1>
        <button @click="$router.back()">← Back</button>
      </div>

      <div class="details-grid">
        <div class="details-section">
          <h3>Delivery Information</h3>
          <div class="detail-row">
            <span class="label">Status:</span>
            <span class="status pending">In Transit</span>
          </div>
          <div class="detail-row">
            <span class="label">Current Location:</span>
            <span>Manila, Metro Manila</span>
          </div>
          <div class="detail-row">
            <span class="label">Assigned Driver:</span>
            <span>Juan Dela Cruz</span>
          </div>
          <div class="detail-row">
            <span class="label">Scheduled Time:</span>
            <span>2:00 PM - 4:00 PM</span>
          </div>
        </div>

        <div class="details-section">
          <h3>Delivery Items</h3>
          <table class="items-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in 3" :key="i">
                <td>Product {{ i }}</td>
                <td>{{ i + 1 }}</td>
                <td>₱{{ (Math.random() * 5000 + 1000).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="actions-section">
        <h3>Update Status</h3>
        <div class="status-buttons">
          <button @click="updateStatus('picked-up')" class="btn btn-status">📦 Picked Up</button>
          <button @click="updateStatus('in-transit')" class="btn btn-status active">🚚 In Transit</button>
          <button @click="updateStatus('delivered')" class="btn btn-status">✓ Delivered</button>
        </div>

        <div v-if="showDeliveryForm" class="form-section">
          <h3>Delivery Confirmation</h3>
          <div class="form-group">
            <label>Recipient Name:</label>
            <input v-model="deliveryForm.recipientName" type="text" />
          </div>
          <div class="form-group">
            <label>Notes:</label>
            <textarea v-model="deliveryForm.notes" placeholder="Any special notes..."></textarea>
          </div>
          <button @click="confirmDelivery" class="btn btn-primary">Confirm Delivery</button>
        </div>
      </div>

      <div v-if="showCODForm" class="payment-section">
        <h3>COD Payment Collection</h3>
        <div class="form-group">
          <label>Amount to Collect:</label>
          <input v-model.number="codForm.amount" type="number" step="0.01" />
        </div>
        <div class="form-group">
          <label>Payment Method Received:</label>
          <select v-model="codForm.method">
            <option value="cash">Cash</option>
            <option value="check">Check</option>
          </select>
        </div>
        <button @click="collectCOD" class="btn btn-primary">Confirm Collection</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const showDeliveryForm = ref(false);
const showCODForm = ref(false);

const deliveryForm = ref({
  recipientName: '',
  notes: ''
});

const codForm = ref({
  amount: 0,
  method: 'cash'
});

const updateStatus = (status) => {
  if (status === 'delivered') {
    showDeliveryForm.value = true;
  }
  alert(`Status updated to: ${status}`);
};

const confirmDelivery = () => {
  alert(`Delivery confirmed by: ${deliveryForm.value.recipientName}`);
  showDeliveryForm.value = false;
};

const collectCOD = () => {
  alert(`COD collected: ₱${codForm.value.amount}`);
  showCODForm.value = false;
};
</script>

<style scoped>
.delivery-details {
  max-width: 900px;
  margin: 0 auto;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 30px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.header h1 {
  margin: 0;
  color: #0a1d37;
}

.header button {
  padding: 8px 12px;
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
}

.details-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
  margin-bottom: 30px;
}

.details-section h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
  font-size: 14px;
  text-transform: uppercase;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #e0e0e0;
}

.detail-row .label {
  color: #666;
  font-weight: 500;
}

.status {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.status.pending {
  background-color: #e3f2fd;
  color: #1976d2;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  background-color: #f9f9f9;
  border-radius: 6px;
  overflow: hidden;
}

.items-table th {
  padding: 10px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  border-bottom: 2px solid #e0e0e0;
}

.items-table td {
  padding: 10px;
  border-bottom: 1px solid #e0e0e0;
}

.actions-section {
  padding: 20px;
  background-color: #f9f9f9;
  border-radius: 6px;
  margin-bottom: 20px;
}

.actions-section h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
}

.status-buttons {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.btn {
  padding: 10px 15px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
}

.btn-status {
  background-color: #f0f0f0;
  color: #333;
  border: 1px solid #ddd;
  flex: 1;
}

.btn-status:hover {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
}

.btn-status.active {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
}

.btn-primary {
  background-color: #e57c2a;
  color: white;
}

.btn-primary:hover {
  background-color: #d46a1a;
}

.form-section,
.payment-section {
  background: white;
  padding: 20px;
  border-radius: 6px;
  margin-top: 15px;
  border: 1px solid #e0e0e0;
}

.form-section h3,
.payment-section h3 {
  margin: 0 0 15px 0;
  color: #0a1d37;
  font-size: 14px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #333;
  font-size: 13px;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 13px;
  font-family: inherit;
}

.form-group textarea {
  resize: vertical;
  min-height: 60px;
}

@media (max-width: 768px) {
  .details-grid {
    grid-template-columns: 1fr;
  }

  .status-buttons {
    flex-direction: column;
  }
}
</style>
