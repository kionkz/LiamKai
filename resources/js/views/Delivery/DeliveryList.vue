<template>
  <div class="delivery-container">
    <div class="header-section">
      <h1>Delivery Management</h1>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Delivery #</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Assigned To</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="i in 8" :key="i">
          <td>#DLV-{{ 4000 + i }}</td>
          <td>Customer {{ i }}</td>
          <td>{{ Math.floor(Math.random() * 5 + 1) }} items</td>
          <td>
            <span class="badge" :class="i % 3 === 0 ? 'urgent' : i % 2 === 0 ? 'high' : 'normal'">
              {{ i % 3 === 0 ? 'Urgent' : i % 2 === 0 ? 'High' : 'Normal' }}
            </span>
          </td>
          <td>
            <span class="status" :class="['pending', 'in-transit', 'delivered'][i % 3]">
              {{ ['Pending', 'In Transit', 'Delivered'][i % 3] }}
            </span>
          </td>
          <td>Employee {{ Math.floor(Math.random() * 5 + 1) }}</td>
          <td>
            <router-link :to="`/deliveries/${i}`" class="btn-small">View</router-link>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
</script>

<style scoped>
.delivery-container {
  animation: fadeIn 0.3s ease-in;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.header-section h1 {
  margin: 0;
  color: #0a1d37;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.data-table thead {
  background-color: #f9f9f9;
}

.data-table th {
  padding: 15px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.data-table td {
  padding: 15px;
  border-bottom: 1px solid #e0e0e0;
}

.data-table tbody tr:hover {
  background-color: #f9f9f9;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.badge.urgent {
  background-color: #fee;
  color: #c33;
}

.badge.high {
  background-color: #fff3e0;
  color: #f57c00;
}

.badge.normal {
  background-color: #e8f5e9;
  color: #388e3c;
}

.status {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.status.pending {
  background-color: #fff3e0;
  color: #f57c00;
}

.status.in-transit {
  background-color: #e3f2fd;
  color: #1976d2;
}

.status.delivered {
  background-color: #e8f5e9;
  color: #388e3c;
}

.btn-small {
  padding: 6px 12px;
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s;
}

.btn-small:hover {
  background-color: #e57c2a;
  color: white;
  border-color: #e57c2a;
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
