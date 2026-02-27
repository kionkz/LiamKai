<template>
  <div class="dashboard-container">
    <div class="widgets-grid">
      <div class="widget card">
        <div class="widget-header">
          <h3>Today's Sales</h3>
          <span class="icon">💰</span>
        </div>
        <div class="widget-body">
          <p class="big-number">₱12,450</p>
          <p class="meta">+15% from yesterday</p>
        </div>
      </div>

      <div class="widget card">
        <div class="widget-header">
          <h3>Pending Deliveries</h3>
          <span class="icon">🚚</span>
        </div>
        <div class="widget-body">
          <p class="big-number">8</p>
          <p class="meta">3 urgent</p>
        </div>
      </div>

      <div class="widget card">
        <div class="widget-header">
          <h3>Low Stock Alerts</h3>
          <span class="icon">⚠️</span>
        </div>
        <div class="widget-body">
          <p class="big-number" style="color: #e57c2a;">5</p>
          <p class="meta">Action required</p>
        </div>
      </div>

      <div class="widget card">
        <div class="widget-header">
          <h3>Purchase Orders</h3>
          <span class="icon">📦</span>
        </div>
        <div class="widget-body">
          <p class="big-number">3</p>
          <p class="meta">Awaiting delivery</p>
        </div>
      </div>
    </div>

    <div class="dashboard-content">
      <div class="card">
        <h2>Recent Orders</h2>
        <table class="data-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="i in 5" :key="i">
              <td>#ORD-{{ 1000 + i }}</td>
              <td>Customer {{ i }}</td>
              <td><span class="badge" :class="i % 2 === 0 ? 'retail' : 'wholesale'">{{ i % 2 === 0 ? 'Retail' : 'Wholesale' }}</span></td>
              <td>₱{{ (Math.random() * 10000 + 1000).toFixed(2) }}</td>
              <td><span class="status" :class="['pending', 'paid', 'delivered'][i % 3]">{{ ['Pending', 'Paid', 'Delivered'][i % 3] }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2>Revenue Summary</h2>
        <div class="revenue-summary">
          <div class="revenue-item">
            <p class="label">This Week</p>
            <p class="value">₱85,450</p>
          </div>
          <div class="revenue-item">
            <p class="label">This Month</p>
            <p class="value">₱324,890</p>
          </div>
          <div class="revenue-item">
            <p class="label">Outstanding</p>
            <p class="value">₱45,600</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>

<style scoped>
.dashboard-container {
  max-width: 1400px;
  margin: 0 auto;
  animation: fadeIn 0.3s ease-in;
  padding: 20px 0;
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

.widgets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
  margin-bottom: 40px;
}

.widget {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  border-left: 4px solid #e57c2a;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.widget:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.widget-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.widget-header h3 {
  margin: 0;
  font-size: 14px;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.widget-header .icon {
  font-size: 28px;
  opacity: 0.8;
}

.widget-body {
  padding-top: 8px;
}

.big-number {
  font-size: 32px;
  font-weight: 700;
  margin: 0;
  color: #0a1d37;
}

.meta {
  font-size: 12px;
  color: #999;
  margin: 8px 0 0 0;
}

.dashboard-content {
  display: grid;
  grid-template-columns: 1fr 350px;
  gap: 20px;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.card h2 {
  margin: 0 0 20px 0;
  color: #0a1d37;
  font-size: 18px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table thead {
  background-color: #f9f9f9;
}

.data-table th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
}

.data-table td {
  padding: 12px;
  border-bottom: 1px solid #e0e0e0;
  color: #333;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.badge.retail {
  background-color: #e3f2fd;
  color: #1976d2;
}

.badge.wholesale {
  background-color: #f3e5f5;
  color: #7b1fa2;
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

.status.paid {
  background-color: #e8f5e9;
  color: #388e3c;
}

.status.delivered {
  background-color: #e0f2f1;
  color: #00897b;
}

.revenue-summary {
  display: grid;
  gap: 15px;
}

.revenue-item {
  padding: 15px;
  background-color: #f9f9f9;
  border-radius: 6px;
  border-left: 4px solid #e57c2a;
}

.revenue-item .label {
  font-size: 12px;
  color: #999;
  margin: 0;
  text-transform: uppercase;
}

.revenue-item .value {
  font-size: 24px;
  font-weight: 700;
  color: #0a1d37;
  margin: 5px 0 0 0;
}

@media (max-width: 1200px) {
  .dashboard-content {
    grid-template-columns: 1fr;
  }
}
</style>
