// Dashboard page module. Shared fetching and formatting live in AppCore.
class DashboardPage {
  constructor(app) {
    this.app = app;
    this.rangeFilter = document.getElementById('dashboardRangeFilter');
    this.autoRefreshToggle = document.getElementById('dashboardAutoRefreshToggle');
    this.timer = null;
    this.controller = null;
  }

  init() {
    this.rangeFilter?.addEventListener('change', () => this.load());
    this.autoRefreshToggle?.addEventListener('change', () => this.updateAutoRefresh());
    this.load();
    this.updateAutoRefresh();
  }

  movementLabel(type) {
    return ({ opening: 'Opening Stock', stock_in: 'Stock In', stock_out: 'Stock Out', adjustment: 'Adjustment' })[type] || type || 'Movement';
  }

  renderLowStock(products) {
    const table = document.getElementById('lowStockProductsTable');
    if (!table) return;
    if (!Array.isArray(products) || products.length === 0) {
      this.app.renderTableMessage(table, 3, 'No low-stock products. Great work!');
      return;
    }
    table.innerHTML = products.map(product => `<tr><td>${this.app.escapeHtml(product.product_name || 'Unnamed product')}</td><td>${this.app.formatQuantity(product.quantity)} ${this.app.escapeHtml(product.product_unit || 'units')}</td><td>${this.app.formatQuantity(product.reorder_level)} ${this.app.escapeHtml(product.product_unit || 'units')}</td></tr>`).join('');
  }

  renderMovements(movements) {
    const table = document.getElementById('recentStockMovementsTable');
    if (!table) return;
    if (!Array.isArray(movements) || movements.length === 0) {
      this.app.renderTableMessage(table, 6, 'No stock movements found for this period.');
      return;
    }
    table.innerHTML = movements.map(item => `<tr><td>${this.app.escapeHtml(this.app.formatDate(item.created_at))}</td><td>${this.app.escapeHtml(item.product_name || 'Unnamed product')}</td><td>${this.app.escapeHtml(this.movementLabel(item.reference_type))}</td><td>${this.app.formatQuantity(item.qty_in)}</td><td>${this.app.formatQuantity(item.qty_out)}</td><td>${this.app.formatQuantity(item.balance_after)}</td></tr>`).join('');
  }

  render(data) {
    const values = [
      ['#totalProducts', this.app.formatQuantity(data.totalProducts)],
      ['#totalStockUnits', this.app.formatQuantity(data.totalStockUnits)],
      ['#inventoryValue', this.app.formatMoney(data.inventoryValue)],
      ['#totalMovements', this.app.formatQuantity(data.totalMovements)],
      ['#lowStockItems', this.app.formatQuantity(data.lowStockItems)],
      ['#outOfStockItems', this.app.formatQuantity(data.outOfStockItems)],
      ['#stockInToday', this.app.formatQuantity(data.stockInToday)],
      ['#stockOutToday', this.app.formatQuantity(data.stockOutToday)]
    ];
    values.forEach(([selector, value]) => this.app.setText(selector, value));
    this.renderLowStock(data.lowStockProducts);
    this.renderMovements(data.recentStockMovements);
    this.app.setText('#dashboardLastUpdated', data.generatedAt ? 'Last updated: ' + this.app.formatDate(data.generatedAt) : 'Last updated: ' + this.app.formatDate(new Date()));
  }

  showError(message) {
    this.app.renderTableMessage('#lowStockProductsTable', 3, 'Unable to load low-stock products.');
    this.app.renderTableMessage('#recentStockMovementsTable', 6, 'Unable to load recent stock movements.');
    this.app.setText('#dashboardLastUpdated', message);
  }

  async load() {
    if (!document.getElementById('totalProducts')) return;
    this.controller?.abort();
    const controller = new AbortController();
    this.controller = controller;
    this.app.setText('#dashboardLastUpdated', 'Loading dashboard…');
    try {
      const response = await this.app.requestApi('apiDashboard.php', 'loadDashboard', { range: this.rangeFilter?.value || 'all' }, { signal: controller.signal });
      this.render(response.data || {});
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (error.status !== 401) {
        this.showError(error.message || 'Could not connect to the dashboard API.');
        console.error('Dashboard request failed:', error);
      }
    } finally {
      if (this.controller === controller) this.controller = null;
    }
  }

  updateAutoRefresh() {
    if (this.timer !== null) window.clearInterval(this.timer);
    this.timer = this.autoRefreshToggle?.checked ? window.setInterval(() => this.load(), 60000) : null;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('totalProducts')) new DashboardPage(window.AppCoreInstance).init();
});