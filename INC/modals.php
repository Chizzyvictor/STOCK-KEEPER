<!-- =====================================================
     STANDARD MODAL HEADER STYLE:
     modal-header bg-primary text-white
====================================================== -->

<!-- ============================
     EDIT COMPANY MODALS
============================= -->
<div class="modal fade" id="editCompanyNameModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="editCompanyNameForm">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-building me-2"></i>Edit Company Name
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal">
          </button>
        </div>

        <div class="modal-body">
          <input type="text" class="form-control" id="editCompanyNameInput" required>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary w-100">
            Save Changes
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

<div class="modal fade" id="stockHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="stockHistoryTitle">Stock and price history</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Movement</th>
                <th>Partner</th>
                <th class="text-end">In</th>
                <th class="text-end">Out</th>
                <th class="text-end">Balance</th>
                <th class="text-end">Cost (before → after)</th>
                <th class="text-end">Selling (before → after)</th>
                <th class="text-end">Value</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody id="stockHistoryTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
</div>

<!-- ============================
     ADD PRODUCT MODAL
============================= -->
<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="addProductForm">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-box me-2"></i>Add New Product
          </h5>
          <button type="button" class="btn-close btn-close-white"
                  data-bs-dismiss="modal" aria-label="Close">
          </button>
        </div>

        <div class="modal-body">
          <div class="row">
            <div class="mb-3 col-md-8">
              <label for="productNameInput" class="form-label">Product Name *</label>
              <input type="text" name="product_name" id="productNameInput"
                     class="form-control" maxlength="200" required>
            </div>

            <div class="mb-3 col-md-4">
              <label for="productSkuInput" class="form-label">SKU / Product Code</label>
              <input type="text" name="sku" id="productSkuInput"
                     class="form-control" maxlength="100"
                     placeholder="Generated from category and product name">
              <div class="form-text">Suggested from the category and product name. You can edit it.</div>
            </div>
          </div>

          <div class="row">
            <div class="mb-3 col-md-6">
              <label for="productCategorySelect" class="form-label">Category</label>
              <select name="category_id" id="productCategorySelect"
                      class="form-select">
                <option value="0">No category</option>
              </select>
            </div>

            <div class="mb-3 col-md-6">
              <label for="productUnitSelect1" class="form-label">Unit *</label>
              <select name="product_unit" id="productUnitSelect1"
                      class="form-select" required>
                 <option value="">Loading units...</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="mb-3 col-md-4">
              <label for="productCostPriceInput" class="form-label">Cost Price (₦) *</label>
              <input type="number" name="cost_price"
                     id="productCostPriceInput"
                     class="form-control" min="0" step="0.01"
                     value="0" required>
            </div>

            <div class="mb-3 col-md-4">
              <label for="productSellingPriceInput" class="form-label">Selling Price (₦) *</label>
              <input type="number" name="selling_price"
                     id="productSellingPriceInput"
                     class="form-control" min="0" step="0.01"
                     value="0" required>
            </div>

            <div class="mb-3 col-md-4">
              <label for="reorderLevelInput" class="form-label">Reorder Level</label>
              <input type="number" name="reorder_level"
                     id="reorderLevelInput"
                     class="form-control" min="0" step="0.001"
                     value="0">
            </div>
          </div>

          <div class="alert alert-light border mb-0">
            <i class="fa fa-info-circle text-primary me-1"></i>
            New products start with zero stock. Use Stock In to record
            the quantity you already have.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light"
                  data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">
            <i class="fa fa-save me-1"></i>Save Product
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- ============================
     EDIT PRODUCT MODAL
============================= -->
<div class="modal fade" id="editProductModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="editProductForm">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-pencil me-2"></i>Edit Product
          </h5>
          <button type="button" class="btn-close btn-close-white"
                  data-bs-dismiss="modal" aria-label="Close">
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="product_id" id="editProductIdInput">

          <div class="row">
            <div class="mb-3 col-md-8">
              <label for="editProductNameInput" class="form-label">Product Name *</label>
              <input type="text" name="product_name"
                     id="editProductNameInput" class="form-control"
                     maxlength="200" required>
            </div>

            <div class="mb-3 col-md-4">
              <label for="editProductSkuInput" class="form-label">SKU / Product Code</label>
              <input type="text" name="sku" id="editProductSkuInput"
                     class="form-control" maxlength="100">
            </div>
          </div>

          <div class="row">
            <div class="mb-3 col-md-6">
              <label for="editProductCategorySelect" class="form-label">Category</label>
              <select name="category_id" id="editProductCategorySelect"
                      class="form-select">
                <option value="0">No category</option>
              </select>
            </div>

            <div class="mb-3 col-md-6">
              <label for="editProductUnitSelect" class="form-label">Unit *</label>
              <select name="product_unit" id="editProductUnitSelect"
                      class="form-select" required>
                <option value="">Loading units...</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="mb-3 col-md-4">
              <label for="editCostPriceInput" class="form-label">Cost Price (₦) *</label>
              <input type="number" name="cost_price"
                     id="editCostPriceInput" class="form-control"
                     min="0" step="0.01" required>
            </div>

            <div class="mb-3 col-md-4">
              <label for="editSellingPriceInput" class="form-label">Selling Price (₦) *</label>
              <input type="number" name="selling_price"
                     id="editSellingPriceInput" class="form-control"
                     min="0" step="0.01" required>
            </div>

            <div class="mb-3 col-md-4">
              <label for="editReorderLevelInput" class="form-label">Reorder Level</label>
              <input type="number" name="reorder_level"
                     id="editReorderLevelInput" class="form-control"
                     min="0" step="0.001" required>
            </div>
          </div>

          <div class="mb-3">
            <label for="editStatusSelect" class="form-label">Product Status</label>
            <select name="status" id="editStatusSelect" class="form-select">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>

          <div class="alert alert-light border mb-0">
            <i class="fa fa-info-circle text-primary me-1"></i>
            Editing product details does not change its stock quantity.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light"
                  data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save me-1"></i>Update Product
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- ============================
     RESTOCK PRODUCT MODAL
============================= -->
<div class="modal fade" id="restockProductModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="restockProductForm">

        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">
            <i class="fa fa-plus me-2"></i>Restock Product
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <h4 class="mb-4 text-center" id="restockProductTitle"></h4>

          <div class="mb-3">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" id="restockQuantityInput" class="form-control" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success w-100">
            <i class="fa fa-plus me-1"></i>Restock
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

<!-- ============================
     ADD CATEGORY MODAL
============================= -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="addCategoryForm">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-plus me-2"></i>Add Category
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Category Name</label>
            <input type="text" name="category_name" id="categoryNameInput" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="category_description" id="categoryDescriptionInput" class="form-control" rows="3"></textarea> 
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary w-100">
            <i class="fa fa-plus me-1"></i>Add Category
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

<!-- ============================
    EDIT CATEGORY MODAL
============================= -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="editCategoryForm">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-pencil me-2"></i>Edit Category
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="category_id" id="editCategoryIdInput">

          <div class="mb-3">
            <label class="form-label">Category Name</label>
            <input type="text" name="category_name" id="editCategoryNameInput" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="category_description" id="editCategoryDescriptionInput" class="form-control" rows="3"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Change Status</label>
            <select name="status" id="editCategoryStatusSelect" class="form-select" required>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary w-100">
            <i class="fa fa-pencil me-1"></i>Update Category
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

<!-- Image Viewer -->
<div id="imageViewer" class="image-viewer">
    <span class="close-viewer">&times;</span>
    <img class="viewer-content" id="viewerImg">
</div>

<!-- ==========================================
     ADD PRODUCT UNIT MODAL
=========================================== -->
<div class="modal fade" id="addUnitModal" tabindex="-1"
     aria-labelledby="addUnitModalTitle"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow rounded">

            <form id="addUnitForm">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addUnitModalTitle">
                        <i class="fa fa-balance-scale me-2"></i>
                        Add Product Unit
                    </h5>

                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>

                <div class="modal-body">
                    <div id="addUnitFormMessage"
                         class="alert alert-danger"
                         role="alert"
                         hidden></div>
                    
                    <div class="mb-3">
                        <label for="unitNameInput" class="form-label">Unit Name *</label>
                        <input
                            type="text"
                            name="unit_name"
                            id="unitNameInput"
                            class="form-control"
                            maxlength="100"
                            placeholder="e.g. Sheets"
                            required>
                        <div class="form-text">
                            Enter a clear, descriptive unit name.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="unitSymbolInput" class="form-label">Unit Symbol</label>
                        <input
                            type="text"
                            name="unit_symbol"
                            id="unitSymbolInput"
                            class="form-control"
                            maxlength="30"
                            placeholder="e.g. sheet">
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="unitDescriptionInput" class="form-label">
                            Description
                        </label>
                        <textarea
                            name="description"
                            id="unitDescriptionInput"
                            class="form-control"
                            rows="3"
                            maxlength="500"
                            placeholder="Optional details about this unit"></textarea>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save me-1"></i>
                        Save Unit
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- ==========================================
     EDIT PRODUCT UNIT MODAL
=========================================== -->
<div class="modal fade" id="editUnitModal" tabindex="-1"
     aria-labelledby="editUnitModalTitle"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow rounded">

            <form id="editUnitForm">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editUnitModalTitle">
                        <i class="fa fa-pencil me-2"></i>
                        Edit Product Unit
                    </h5>

                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="unit_id"
                        id="editUnitIdInput">

                    <div class="mb-3">
                        <label for="editUnitNameInput" class="form-label">Unit Name *</label>
                        <input
                            type="text"
                            name="unit_name"
                            id="editUnitNameInput"
                            class="form-control"
                            maxlength="100"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="editUnitSymbolInput" class="form-label">Unit Symbol</label>
                        <input
                            type="text"
                            name="unit_symbol"
                            id="editUnitSymbolInput"
                            class="form-control"
                            maxlength="30">
                    </div>

                    <div class="mb-3">
                        <label for="editUnitDescriptionInput" class="form-label">
                            Description
                        </label>
                        <textarea
                            name="description"
                            id="editUnitDescriptionInput"
                            class="form-control"
                            rows="3"
                            maxlength="500"></textarea>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="editUnitStatusSelect" class="form-label">Status *</label>
                        <select
                            name="status"
                            id="editUnitStatusSelect"
                            class="form-select"
                            required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div class="alert alert-light border mt-3 mb-0">
                        <i class="fa fa-info-circle text-primary me-1"></i>
                        Deactivating a unit removes it from active choices
                        once connected to product forms. Existing product
                        records are not deleted.
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save me-1"></i>
                        Update Unit
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- Stock Transaction Modal (In / Out / Adjust) -->
<div class="modal fade" id="stockTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="stockModalTitle">Stock In</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="stockTransactionForm">
                <div class="modal-body">
                    <input type="hidden" id="stockTransactionProductId" name="product_id">
                    <input type="hidden" id="stockTransactionType" name="transaction_type">

                    <div class="p-3 mb-3 bg-light rounded border">
                        <span class="text-muted small d-block">Selected Product:</span>
                        <strong id="stockModalProductName" class="text-dark"></strong>
                        <div class="small text-muted mt-1">Current Stock: <span id="stockModalCurrentQuantity" class="fw-bold">0</span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" id="stockQuantityLabel">Quantity <span class="text-danger">*</span></label>
                        <input type="number" step="any" min="0.001" class="form-control" id="stockQuantityInput" name="quantity" required>
                    </div>

                    <div class="mb-3" id="stockPartnerGroup">
                        <label for="stockPartnerSelect" class="form-label" id="stockPartnerLabel">Supplier / Customer</label>
                        <select class="form-select" id="stockPartnerSelect" name="partner_id"></select>
                    </div>

                    <div class="mb-3" id="stockPriceGroup">
                        <label for="stockUnitPriceInput" class="form-label" id="stockUnitPriceLabel">Unit Price (₦)</label>
                        <input type="number" min="0" step="0.01" class="form-control" id="stockUnitPriceInput" name="unit_price">
                    </div>

                    <div class="mb-3" id="stockPaidGroup">
                        <label for="stockPaidInput" class="form-label" id="stockPaidLabel">Amount Paid / Received (₦)</label>
                        <input type="number" min="0" step="0.01" class="form-control" id="stockPaidInput" name="amount_paid" value="0">
                        <div class="form-text">A short payment becomes outstanding; an excess payment becomes an advance.</div>
                    </div>

                    <div id="stockAdjustmentPrices" class="d-none">
                        <p class="small text-muted mb-2">Optional: update the product’s current prices.</p>
                        <div class="row">
                            <div class="mb-3 col-md-6">
                              <label for="adjustCostPriceInput" class="form-label">New Cost Price (₦)</label>
                              <input type="number" min="0" step="0.01" class="form-control" id="adjustCostPriceInput" name="new_cost_price" placeholder="Leave unchanged">
                            </div>
                            <div class="mb-3 col-md-6">
                              <label for="adjustSellingPriceInput" class="form-label">New Selling Price (₦)</label>
                              <input type="number" min="0" step="0.01" class="form-control" id="adjustSellingPriceInput" name="new_selling_price" placeholder="Leave unchanged">
                            </div>
                        </div>
                    </div>

                    <div id="stockHistoryPreview" class="small text-muted mb-3"></div>

                    <div class="mb-3">
                        <label class="form-label">Notes / Reference</label>
                        <textarea class="form-control" id="stockNotesInput" name="notes" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary shadow-sm" id="saveStockTransactionBtn">Confirm Action</button>
                </div>
            </form>
        </div>
    </div>
</div>