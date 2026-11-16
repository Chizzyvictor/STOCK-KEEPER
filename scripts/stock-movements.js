
class StockMovementsApi {
  constructor(url, app) { this.url = url; this.app = app; }
  async list(filters, signal) {
    const response = await this.app.requestApi(this.url, 'list', filters, { signal });
    return response.data || {};
  }
}

class StockMovementsPage {
  constructor(root) {
    this.root = root;
    this.app = window.AppCoreInstance;
    this.api = new StockMovementsApi('apiStockMovements.php', this.app);
    this.elements = {
      form: root.querySelector("#movementFilters"),
      search: root.querySelector("#movementSearch"),
      tableBody: root.querySelector("#movementTableBody"),
      summary: root.querySelector("#movementSummary"),
      pageLabel: root.querySelector("#movementPageLabel"),
      previous: root.querySelector("#movementPrevious"),
      next: root.querySelector("#movementNext"),
      clear: root.querySelector("#clearMovementFilters")
    };
    this.page = 1;
    this.totalPages = 1;
    this.searchTimer = null;
    this.requestController = null;
  }

  init() {
    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      this.page = 1;
      this.load();
    });

    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.searchTimer);
      this.searchTimer = window.setTimeout(() => {
        this.page = 1;
        this.load();
      }, 250);
    });

    this.elements.clear.addEventListener("click", () => {
      this.elements.form.reset();
      this.page = 1;
      this.load();
    });

    this.elements.previous.addEventListener("click", () => {
      if (this.page > 1) {
        this.page -= 1;
        this.load();
      }
    });

    this.elements.next.addEventListener("click", () => {
      if (this.page < this.totalPages) {
        this.page += 1;
        this.load();
      }
    });

    this.load();
  }

  getFilters() {
    return {
      page: String(this.page),
      search: this.elements.search.value.trim(),
      type: this.root.querySelector("#movementType").value,
      from_date: this.root.querySelector("#movementFromDate").value,
      to_date: this.root.querySelector("#movementToDate").value
    };
  }

  async load() {
    if (this.requestController) this.requestController.abort();
    this.requestController = new AbortController();
    this.renderMessage("Loading stock movements…", "muted");

    try {
      const data = await this.api.list(this.getFilters(), this.requestController.signal);
      this.page = Number(data.page) || 1;
      this.totalPages = Number(data.total_pages) || 1;
      this.renderRows(Array.isArray(data.movements) ? data.movements : []);
      this.renderPagination(Number(data.total) || 0);
    } catch (error) {
      if (error.name === "AbortError") return;
      this.renderMessage(error.message || "Could not load stock movements.", "danger");
    }
  }

  renderMessage(message, tone) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 11;
    cell.className = `text-center text-${tone} py-5`;
    cell.textContent = message;
    row.append(cell);
    this.elements.tableBody.replaceChildren(row);
  }

  renderRows(movements) {
    if (!movements.length) {
      this.renderMessage("No stock movements match these filters.", "muted");
      return;
    }

    const rows = movements.map((movement) => {
      const typeLabels = {
        stock_in: "Stock In",
        stock_out: "Stock Out",
        adjustment: "Adjustment / Price change",
        opening: "Opening prices"
      };
      const badgeTone = movement.reference_type === "stock_in"
        ? "success"
        : movement.reference_type === "stock_out" ? "danger" : "secondary";
      const label = typeLabels[movement.reference_type] || movement.reference_type || "—";
      const row = document.createElement("tr");

      row.append(
        this.cell(movement.created_at, "text-nowrap"),
        this.badgeCell(label, badgeTone),
        this.productCell(movement.product_name, movement.sku),
        this.cell(movement.partner_name),
        this.cell(this.quantity(movement.qty_in, movement.product_unit), "text-end text-success"),
        this.cell(this.quantity(movement.qty_out, movement.product_unit), "text-end text-danger"),
        this.cell(this.quantity(movement.balance_after, movement.product_unit), "text-end fw-bold"),
        this.cell(this.priceFlow(movement.previous_cost_price, movement.unit_cost), "text-end text-nowrap"),
        this.cell(this.priceFlow(movement.previous_selling_price, movement.unit_selling_price), "text-end text-nowrap"),
        this.cell(movement.reference_type === "opening" || movement.reference_type === "adjustment" ? "—" : this.money(movement.total_amount), "text-end text-nowrap"),
        this.cell(movement.note)
      );
      return row;
    });

    this.elements.tableBody.replaceChildren(...rows);
  }

  renderPagination(total) {
    this.elements.summary.textContent = `${total} matching record${total === 1 ? "" : "s"}`;
    this.elements.pageLabel.textContent = `Page ${this.page} of ${this.totalPages}`;
    this.elements.previous.disabled = this.page <= 1;
    this.elements.next.disabled = this.page >= this.totalPages;
  }

  cell(value, className = "") {
    const cell = document.createElement("td");
    cell.textContent = value == null || value === "" ? "—" : String(value);
    if (className) cell.className = className;
    return cell;
  }

  badgeCell(label, tone) {
    const cell = document.createElement("td");
    const badge = document.createElement("span");
    badge.className = `badge bg-${tone}`;
    badge.textContent = label;
    cell.append(badge);
    return cell;
  }

  productCell(name, sku) {
    const cell = document.createElement("td");
    cell.append(document.createTextNode(name || "—"));
    if (sku) {
      const detail = document.createElement("small");
      detail.className = "d-block text-muted";
      detail.textContent = sku;
      cell.append(detail);
    }
    return cell;
  }

  quantity(value, unit) {
    const amount = window.AppCoreInstance.formatQuantity(value);
    return `${amount}${unit ? ` ${unit}` : ""}`;
  }

  money(value) {
    if (value == null) return "—";
    return window.AppCoreInstance.formatMoney(value);
  }

  priceFlow(before, after) {
    const oldPrice = before == null ? "—" : this.money(before);
    const newPrice = after == null ? "—" : this.money(after);
    return `${oldPrice} → ${newPrice}`;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("#stockMovementsPage");
  if (root) new StockMovementsPage(root).init();
});
