<?php
declare(strict_types=1);
require_once __DIR__ . '/INC/session.php';
stockKeeperStartSession();
require_once __DIR__ . '/INC/isLogedin.php';
include __DIR__ . '/INC/header.php';
include __DIR__ . '/INC/navbar.php';
?>
<main class="container-fluid py-4" id="partnersPage">
    <?php
    $pageHeadingTitle = 'Partners';
    $pageHeadingDescription = 'Manage suppliers, customers, and their separate account balances.';
    $pageHeadingIcon = 'fa fa-users text-primary';
    $pageHeadingActions = '<button class="btn btn-primary" type="button" id="addPartnerButton"><i class="fa fa-plus me-1"></i>Add Partner</button>';
    include __DIR__ . '/INC/page_heading.php';
    unset($pageHeadingTitle, $pageHeadingDescription, $pageHeadingIcon, $pageHeadingActions);
    ?>

    <?php
    $partnerStats = [
        ['Partners', 'partnerCount', '0', ''],
        ['Customer Receivables', 'customerOutstandingTotal', '₦0.00', 'text-danger'],
        ['Supplier Payables', 'supplierOutstandingTotal', '₦0.00', 'text-warning'],
        ['Partner Advances', 'partnerAdvanceTotal', '₦0.00', 'text-success'],
    ];
    ?>
    <div class="row mb-4">
        <?php foreach ($partnerStats as [$label, $id, $value, $valueClass]):
            $statCardLabel = $label; $statCardValueId = $id; $statCardValue = $value;
            $statCardValueClass = 'h4 mb-0 ' . $valueClass; $statCardTag = 'h4';
            include __DIR__ . '/INC/stat_card.php';
        endforeach; unset($partnerStats, $statCardLabel, $statCardValueId, $statCardValue, $statCardValueClass, $statCardTag); ?>
    </div>


    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between">
            <h5 class="mb-2 mb-md-0">Partner Directory</h5>
            <input class="form-control" style="max-width:300px" id="partnerSearch" type="search" placeholder="Search partners" aria-label="Search partners">
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Partner</th><th>Role</th><th>Contact</th><th class="text-end">Customer owes</th><th class="text-end">Supplier payable</th><th class="text-end">Customer advance</th><th class="text-end">Supplier advance</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="partnersTableBody"><tr><td colspan="9" class="text-center text-muted py-5">Loading partners…</td></tr></tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center"><small id="partnersListMessage" class="text-muted">Loading…</small><button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPartnersButton">Refresh</button></div>
    </div>
</main>

<div class="modal fade" id="partnerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
        <form id="partnerForm">
            <div class="modal-header"><h5 class="modal-title" id="partnerModalTitle">Add Partner</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="partner_id" id="partnerId">
                <div class="row">
                    <div class="form-group col-md-6"><label for="partnerName">Name *</label><input class="form-control" id="partnerName" name="name" maxlength="120" required></div>
                    <div class="form-group col-md-3"><label for="partnerRole">Can</label><select class="form-control" id="partnerRole" name="role"><option value="both">Supply and purchase</option><option value="supplier">Supply stock</option><option value="customer">Purchase stock</option></select></div>
                    <div class="form-group col-md-3"><label for="partnerStatus">Status</label><select class="form-control" id="partnerStatus" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <div class="form-group col-md-6"><label for="partnerPhone">Phone</label><input class="form-control" id="partnerPhone" name="phone" maxlength="40"></div>
                    <div class="form-group col-md-6"><label for="partnerEmail">Email</label><input class="form-control" id="partnerEmail" name="email" type="email" maxlength="160"></div>
                    <div class="form-group col-12"><label for="partnerAddress">Address</label><textarea class="form-control" id="partnerAddress" name="address" rows="2" maxlength="500"></textarea></div>
                    <div class="form-group col-12 mb-0"><label for="partnerNotes">Notes</label><textarea class="form-control" id="partnerNotes" name="notes" rows="2" maxlength="1000"></textarea></div>
                </div>
                <fieldset id="partnerOpeningBalances" class="border rounded p-3 mt-3">
                    <legend class="w-auto px-2 h6">Opening balances (optional)</legend>
                    <div class="row mb-0">
                        <div class="form-group col-md-6"><label for="openingCustomerOutstanding">Customer owes us (₦)</label><input type="number" min="0" step="0.01" class="form-control" id="openingCustomerOutstanding" name="customer_outstanding" value="0"></div>
                        <div class="form-group col-md-6"><label for="openingSupplierOutstanding">We owe supplier (₦)</label><input type="number" min="0" step="0.01" class="form-control" id="openingSupplierOutstanding" name="supplier_outstanding" value="0"></div>
                        <div class="form-group col-md-6"><label for="openingCustomerAdvance">Customer advance received (₦)</label><input type="number" min="0" step="0.01" class="form-control" id="openingCustomerAdvance" name="customer_advance" value="0"></div>
                        <div class="form-group col-md-6 mb-0"><label for="openingSupplierAdvance">Supplier advance paid (₦)</label><input type="number" min="0" step="0.01" class="form-control" id="openingSupplierAdvance" name="supplier_advance" value="0"></div>
                    </div>
                </fieldset>
                <small class="text-muted">Balances are tracked separately for customer sales and supplier purchases.</small>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="savePartnerButton">Save Partner</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="partnerPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="partnerPaymentForm"><div class="modal-header"><h5 class="modal-title" id="partnerPaymentTitle">Record Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><input type="hidden" name="partner_id" id="paymentPartnerId"><input type="hidden" name="account_type" id="paymentAccountType"><p id="paymentPartnerName" class="fw-bold"></p><div class="form-group"><label for="paymentAmount">Amount (₦)</label><input type="number" name="amount" id="paymentAmount" class="form-control" min="0.01" step="0.01" required></div><div class="form-group mb-0"><label for="paymentNotes">Reference / notes</label><input type="text" name="notes" id="paymentNotes" class="form-control" maxlength="300"></div><small class="form-text text-muted">Any amount above the outstanding balance is recorded as an advance.</small></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Record Payment</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="partnerHistoryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="partnerHistoryTitle">Account Activity</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="table-responsive"><table class="table mb-0"><thead class="table-light"><tr><th>Date</th><th>Account</th><th>Activity</th><th class="text-end">Amount</th><th>Note</th></tr></thead><tbody id="partnerHistoryBody"></tbody></table></div></div></div>
</div>

<div class="modal fade" id="partnerStatementModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title mb-1" id="partnerStatementTitle">Partner Statement</h5><small class="text-muted" id="partnerStatementPeriod"></small></div><button type="button" class="btn-close no-print" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body" id="partnerStatementPrintArea">
            <form id="partnerStatementFilters" class="row align-items-end statement-controls no-print mb-3">
                <input type="hidden" id="statementPartnerId">
                <div class="form-group col-md-3 mb-2"><label for="statementAccount">Account</label><select class="form-control" id="statementAccount"><option value="customer">Customer</option><option value="supplier">Supplier</option></select></div>
                <div class="form-group col-md-3 mb-2"><label for="statementFromDate">From</label><input type="date" class="form-control" id="statementFromDate"></div>
                <div class="form-group col-md-3 mb-2"><label for="statementToDate">To</label><input type="date" class="form-control" id="statementToDate"></div>
                <div class="form-group col-md-3 mb-2"><button type="submit" class="btn btn-primary me-1">Apply</button><button type="button" class="btn btn-outline-secondary" id="statementAllDates">All dates</button></div>
            </form>
            <div class="row mb-3 statement-summary">
                <div class="col-md-3 mb-2"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Opening balance</small><strong id="statementOpeningBalance">—</strong></div></div>
                <div class="col-md-3 mb-2"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Charges / debits</small><strong id="statementDebits">—</strong></div></div>
                <div class="col-md-3 mb-2"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Payments / credits</small><strong id="statementCredits">—</strong></div></div>
                <div class="col-md-3 mb-2"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Closing balance</small><strong id="statementClosingBalance">—</strong></div></div>
            </div>
            <div class="alert alert-light border py-2"><small>Current account balance (all dates): <strong id="statementCurrentBalance">—</strong></small></div>
            <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Date</th><th>Activity</th><th>Reference / note</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Running balance</th></tr></thead><tbody id="partnerStatementBody"><tr><td colspan="6" class="text-center text-muted py-4">Select a partner to load their statement.</td></tr></tbody></table></div>
        </div>
        <div class="modal-footer no-print"><button type="button" class="btn btn-outline-secondary" id="exportPartnerStatement"><i class="fa fa-download me-1"></i>Export CSV</button><button type="button" class="btn btn-outline-primary" id="printPartnerStatement"><i class="fa fa-print me-1"></i>Print</button><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #partnerStatementModal, #partnerStatementModal * { visibility: visible !important; }
    #partnerStatementModal { position: absolute !important; display: block !important; inset: 0 !important; width: 100% !important; height: auto !important; overflow: visible !important; background: #fff !important; }
    #partnerStatementModal .modal-dialog { max-width: none !important; width: 100% !important; margin: 0 !important; }
    #partnerStatementModal .modal-content, #partnerStatementModal .modal-body { border: 0 !important; overflow: visible !important; }
    #partnerStatementModal .no-print, #partnerStatementModal .statement-controls { display: none !important; }
    #partnerStatementModal .table { font-size: 10pt; }
}
</style>

<?php include __DIR__ . '/INC/footer.php'; ?>
<script src="scripts/partners.js?v=<?= (string) filemtime(__DIR__ . '/scripts/partners.js') ?>"></script>
</body></html>
