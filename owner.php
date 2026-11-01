<?php
session_start();
require_once 'db.php';

// Auth Guard: Only owner can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
  header('Location: login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owner Dashboard - Langkit University Shop</title>
  
  <!-- Font Styles -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;900&family=Cinzel:wght@700&display=swap" rel="stylesheet">
  
  <!-- FontAwesome & ChartJS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- External Stylesheet -->
  <link rel="stylesheet" href="styles.css">
</head>
<body class="owner-panel-body">

  <!-- Background Decorative Blurs -->
  <div class="decor-blob decor-1"></div>
  <div class="decor-blob decor-2"></div>

  <div class="admin-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar">
      <div class="sidebar-brand">
        <img src="logo.svg" alt="Langkit Logo" class="brand-icon">
        <div class="brand-text">
          <h1>Langkit</h1>
          <span>University Shop</span>
        </div>
      </div>
      
      <nav class="sidebar-nav">
        <ul>
          <li>
            <a href="#dashboard" class="nav-link active" data-tab="dashboard">
              <i class="fa-solid fa-chart-pie"></i>
              <span>Sales Overview</span>
            </a>
          </li>
          <li>
            <a href="#inventory" class="nav-link" data-tab="inventory">
              <i class="fa-solid fa-boxes-stacked"></i>
              <span>Inventory Insights</span>
            </a>
          </li>
          <li>
            <a href="#employees" class="nav-link" data-tab="employees">
              <i class="fa-solid fa-users-gear"></i>
              <span>Employee & Shifts</span>
            </a>
          </li>
          <li>
            <a href="#financials" class="nav-link" data-tab="financials">
              <i class="fa-solid fa-wallet"></i>
              <span>Financial Ledger</span>
            </a>
          </li>
          <li>
            <a href="#audit" class="nav-link" data-tab="audit">
              <i class="fa-solid fa-shield-halved"></i>
              <span>System Audit Trail</span>
            </a>
          </li>
        </ul>
      </nav>
      
      <div class="sidebar-footer">
        <div class="user-profile">
          <div class="profile-avatar">
            <i class="fa-solid fa-user-tie"></i>
          </div>
          <div class="profile-details">
            <h4 class="profile-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h4>
            <span class="profile-role">Store Owner</span>
          </div>
        </div>
        <a href="logout.php" class="logout-btn">
          <i class="fa-solid fa-power-off"></i>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="admin-main">
      <!-- Header bar -->
      <header class="admin-header">
        <div class="header-left">
          <button class="menu-toggle-btn"><i class="fa-solid fa-bars"></i></button>
          <h2 class="section-title-display" id="current-section-title">Sales Overview</h2>
        </div>
        <div class="header-right">
          <div class="live-clock">
            <i class="fa-solid fa-clock"></i>
            <span id="clock-display">Loading time...</span>
          </div>
          <button class="purge-history-btn" id="purge-data-btn">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span>Purge Data History</span>
          </button>
        </div>
      </header>

      <!-- Content Panels Container -->
      <div class="content-panels">
        
        <!-- ================= PANEL: DASHBOARD ================= -->
        <section id="panel-dashboard" class="content-panel active">
          <!-- Metric Grid -->
          <div class="metrics-grid">
            <div class="metric-card">
              <div class="metric-icon" style="background: rgba(139, 0, 0, 0.1); color: var(--primary-red);">
                <i class="fa-solid fa-calendar-day"></i>
              </div>
              <div class="metric-info">
                <h3>Today's Sales</h3>
                <p class="metric-value" id="val-sales-today">₱0.00</p>
                <span class="metric-trend">Updated real-time</span>
              </div>
            </div>
            
            <div class="metric-card">
              <div class="metric-icon" style="background: rgba(212, 175, 55, 0.1); color: var(--accent-gold);">
                <i class="fa-solid fa-calendar-week"></i>
              </div>
              <div class="metric-info">
                <h3>Weekly Sales</h3>
                <p class="metric-value" id="val-sales-weekly">₱0.00</p>
                <span class="metric-trend">Last 7 calendar days</span>
              </div>
            </div>

            <div class="metric-card">
              <div class="metric-icon" style="background: rgba(46, 125, 50, 0.1); color: #2E7D32;">
                <i class="fa-solid fa-hand-holding-dollar"></i>
              </div>
              <div class="metric-info">
                <h3>Estimated Profit</h3>
                <p class="metric-value" id="val-profit-estimate" style="color: #2E7D32;">₱0.00</p>
                <span class="metric-trend text-profit-footnote">Sales minus Cost & Expenses</span>
              </div>
            </div>

            <div class="metric-card">
              <div class="metric-icon" style="background: rgba(45, 45, 56, 0.1); color: var(--text-primary);">
                <i class="fa-solid fa-warehouse"></i>
              </div>
              <div class="metric-info">
                <h3>Inventory Value</h3>
                <p class="metric-value" id="val-inv-price">₱0.00</p>
                <span class="metric-trend" id="val-inv-cost">Cost basis: ₱0.00</span>
              </div>
            </div>
          </div>

          <!-- Alert Bar for low stock -->
          <div class="low-stock-banner" id="low-stock-alert-bar" style="display: none;">
            <div class="banner-left">
              <i class="fa-solid fa-triangle-exclamation"></i>
              <span><strong>Low Stock Warning:</strong> Some products are running critically low on inventory!</span>
            </div>
            <button class="banner-action-btn" onclick="document.querySelector('[data-tab=inventory]').click()">Resolve Inventory</button>
          </div>

          <!-- Analytical Charts Block -->
          <div class="dashboard-charts-row">
            <div class="chart-card main-chart">
              <div class="chart-card-header">
                <h3>Revenue Trends (Last 7 Days)</h3>
                <i class="fa-solid fa-chart-line"></i>
              </div>
              <div class="chart-container">
                <canvas id="revenueTrendChart"></canvas>
              </div>
            </div>

            <div class="chart-card side-chart">
              <div class="chart-card-header">
                <h3>Peak Sales Hours</h3>
                <i class="fa-solid fa-fire-flame-curved"></i>
              </div>
              <div class="chart-container">
                <canvas id="peakSalesChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Product Statistics Rows -->
          <div class="data-lists-grid">
            <div class="list-card">
              <div class="list-card-header">
                <h3>Best-Selling Products</h3>
                <span class="badge gold">Top Performance</span>
              </div>
              <div class="list-body">
                <ul class="stats-list" id="best-sellers-list">
                  <!-- JS Dynamic -->
                  <li class="empty-list-placeholder">No checkout transactions recorded yet.</li>
                </ul>
              </div>
            </div>

            <div class="list-card">
              <div class="list-card-header">
                <h3>Slow-Moving Items</h3>
                <span class="badge red">Low Movement</span>
              </div>
              <div class="list-body">
                <ul class="stats-list" id="slow-movers-list">
                  <!-- JS Dynamic -->
                  <li class="empty-list-placeholder">All products healthy.</li>
                </ul>
              </div>
            </div>

            <div class="list-card">
              <div class="list-card-header">
                <h3>Cashier Rankings</h3>
                <span class="badge gray">Revenue Share</span>
              </div>
              <div class="list-body">
                <ul class="stats-list" id="cashier-rankings-list">
                  <!-- JS Dynamic -->
                  <li class="empty-list-placeholder">No active shifts logged.</li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <!-- ================= PANEL: INVENTORY ================= -->
        <section id="panel-inventory" class="content-panel">
          <div class="panel-action-bar">
            <div class="search-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="inventory-search" placeholder="Search catalog by name or SKU...">
            </div>
            <div class="filter-actions">
              <select id="inventory-category-filter">
                <option value="ALL">All Categories</option>
                <option value="Accessories">Accessories</option>
                <option value="Drinkware">Drinkware</option>
                <option value="Apparel">Apparel</option>
              </select>
              <button class="action-btn-gold" id="btn-add-product">
                <i class="fa-solid fa-plus"></i>
                <span>Add Product</span>
              </button>
            </div>
          </div>

          <!-- Inventory Metrics -->
          <div class="sub-metrics-row">
            <div class="sub-metric-pill">
              <span class="pill-label">Total Unique Products:</span>
              <span class="pill-value" id="inv-unique-count">0</span>
            </div>
            <div class="sub-metric-pill">
              <span class="pill-label">Low Stock Alerts:</span>
              <span class="pill-value text-red" id="inv-low-stock-count">0</span>
            </div>
            <div class="sub-metric-pill">
              <span class="pill-label">Out of Stock:</span>
              <span class="pill-value text-dark-red" id="inv-oos-count">0</span>
            </div>
          </div>

          <!-- Products Table -->
          <div class="table-container shadow-card">
            <table class="data-table" id="inventory-table">
              <thead>
                <tr>
                  <th>Product Details</th>
                  <th>SKU Code</th>
                  <th>Category</th>
                  <th class="text-right">Unit Cost</th>
                  <th class="text-right">Retail Price</th>
                  <th class="text-center">Stock Level</th>
                  <th class="text-center">Min Threshold</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
              <tbody id="inventory-table-body">
                <!-- JS dynamic render -->
              </tbody>
            </table>
          </div>
        </section>

        <!-- ================= PANEL: EMPLOYEES ================= -->
        <section id="panel-employees" class="content-panel">
          <div class="double-panel-layout">
            
            <!-- Cashier list panel -->
            <div class="layout-side-panel">
              <div class="panel-header-row">
                <h3>Cashier Accounts</h3>
                <button class="action-btn-gold btn-sm" id="btn-add-cashier">
                  <i class="fa-solid fa-user-plus"></i>
                  <span>Register Cashier</span>
                </button>
              </div>
              
              <div class="table-container shadow-card compact-panel">
                <table class="data-table" id="cashier-accounts-table">
                  <thead>
                    <tr>
                      <th>Cashier Name</th>
                      <th>Username</th>
                      <th>Status</th>
                      <th class="text-center">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="cashier-table-body">
                    <!-- JS Dynamic -->
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Shifts logs panel -->
            <div class="layout-main-panel">
              <div class="panel-header-row">
                <h3>Shift Log Accountability</h3>
                <span class="log-indicator">Realtime Drawer Attendance Logs</span>
              </div>
              
              <div class="table-container shadow-card">
                <table class="data-table" id="shifts-log-table">
                  <thead>
                    <tr>
                      <th>Cashier</th>
                      <th>Open Time</th>
                      <th>Close Time</th>
                      <th class="text-right">Opening Cash</th>
                      <th class="text-right">Expected Drawer</th>
                      <th class="text-right">Actual Counted</th>
                      <th class="text-right">Discrepancy</th>
                      <th class="text-center">Shift Status</th>
                    </tr>
                  </thead>
                  <tbody id="shifts-table-body">
                    <!-- JS Dynamic -->
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- ================= PANEL: FINANCIALS ================= -->
        <section id="panel-financials" class="content-panel">
          <div class="financials-split-row">
            
            <!-- Expense Register Panel -->
            <div class="fin-card expense-panel-card">
              <div class="panel-header-row">
                <h3>Expenses Ledger</h3>
                <button class="action-btn-gold btn-sm" id="btn-add-expense">
                  <i class="fa-solid fa-receipt"></i>
                  <span>Log Shop Expense</span>
                </button>
              </div>
              <div class="sub-metrics-row compact">
                <div class="sub-metric-pill">
                  <span class="pill-label">Total Cash Outlays:</span>
                  <span class="pill-value text-red" id="fin-total-expenses">₱0.00</span>
                </div>
              </div>
              <div class="table-container shadow-card scroll-table">
                <table class="data-table" id="expenses-table">
                  <thead>
                    <tr>
                      <th>Timestamp</th>
                      <th>Description</th>
                      <th>Category</th>
                      <th class="text-right">Outlay Amount</th>
                    </tr>
                  </thead>
                  <tbody id="expenses-table-body">
                    <!-- JS dynamic -->
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Tax ledger and Summary -->
            <div class="fin-card totals-panel-card">
              <h3>Shop VAT Ledger Summary</h3>
              <div class="fin-metrics-summary shadow-card">
                <div class="summary-line">
                  <span>Gross Sales Subtotal:</span>
                  <span id="fin-gross-subtotal">₱0.00</span>
                </div>
                <div class="summary-line">
                  <span>Less Total Discount (Promo/Coupons):</span>
                  <span class="text-green" id="fin-gross-discount">-₱0.00</span>
                </div>
                <div class="summary-line">
                  <span>Taxable Gross Amount:</span>
                  <span id="fin-taxable-gross">₱0.00</span>
                </div>
                <div class="summary-line highlighted">
                  <span>Estimated 12% VAT Tax Collected:</span>
                  <span id="fin-vat-tax">₱0.00</span>
                </div>
                <div class="summary-line grand">
                  <span>Total Grand Net Intake:</span>
                  <span id="fin-net-intake">₱0.00</span>
                </div>
              </div>
              
              <div class="voids-panel shadow-card">
                <div class="voids-header">
                  <i class="fa-solid fa-ban text-red"></i>
                  <h4>Voided Sales Transactions Accumulation</h4>
                </div>
                <div class="void-stats">
                  <span class="void-label">Total Voided Capital:</span>
                  <span class="void-value text-red" id="fin-voids-total">₱0.00</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Transaction and Void Management Table -->
          <div class="financials-transactions-row">
            <div class="panel-header-row">
              <h3>Sale Order Receipts & Voids Manager</h3>
              <span class="invoice-helper">Owners can review and void transactions. Voiding immediately returns quantities back to inventory stock.</span>
            </div>
            
            <div class="table-container shadow-card">
              <table class="data-table" id="sales-history-table">
                <thead>
                  <tr>
                    <th>Invoice SKU ID</th>
                    <th>Date / Time</th>
                    <th>Served Cashier</th>
                    <th>Customer Type</th>
                    <th>Customer Email</th>
                    <th class="text-right">Discount</th>
                    <th class="text-right">VAT Tax</th>
                    <th class="text-right">Grand Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody id="sales-history-table-body">
                  <!-- JS Dynamic -->
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- ================= PANEL: AUDIT TRAIL ================= -->
        <section id="panel-audit" class="content-panel">
          <div class="panel-header-row">
            <h3>System Security Audit Trails</h3>
            <span class="audit-desc"><i class="fa-solid fa-circle-info"></i> Security log displaying all operations including logins, product edits, employee registrations, cash adjustments, checkouts, and historical voids. Capped at last 100 operations.</span>
          </div>

          <div class="table-container shadow-card">
            <table class="data-table" id="audit-trail-table">
              <thead>
                <tr>
                  <th style="width: 180px;">System Timestamp</th>
                  <th style="width: 140px;">Admin Account</th>
                  <th style="width: 160px;">Action Key</th>
                  <th>Details & Security Context</th>
                </tr>
              </thead>
              <tbody id="audit-table-body">
                <!-- JS dynamic -->
              </tbody>
            </table>
          </div>
        </section>

      </div>
    </main>
  </div>

  <!-- ========================================== -->
  <!--               MODAL DIALOGS                -->
  <!-- ========================================== -->

  <!-- 1. MODAL: ADD/EDIT PRODUCT -->
  <div class="modal-overlay" id="product-modal">
    <div class="modal-card">
      <div class="modal-header">
        <h3 id="product-modal-title">Add New Product</h3>
        <button class="modal-close-btn" data-close="product-modal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="product-form">
        <input type="hidden" id="prod-id" name="id">
        
        <div class="form-row">
          <div class="form-group-modal">
            <label for="prod-name">Product Label / Name *</label>
            <input type="text" id="prod-name" required placeholder="e.g. Reversible Lanyard">
          </div>
          <div class="form-group-modal">
            <label for="prod-sku">Barcode SKU Code *</label>
            <input type="text" id="prod-sku" required placeholder="e.g. LKT-LYD-001">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group-modal">
            <label for="prod-category">Catalog Category</label>
            <select id="prod-category">
              <option value="Accessories">Accessories</option>
              <option value="Drinkware">Drinkware</option>
              <option value="Apparel">Apparel</option>
            </select>
          </div>
          <div class="form-group-modal">
            <label for="prod-stock">Initial Stock Level *</label>
            <input type="number" id="prod-stock" min="0" required placeholder="100">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group-modal">
            <label for="prod-cost">Purchase Cost (₱) *</label>
            <input type="number" step="0.01" min="0" id="prod-cost" required placeholder="60.00">
          </div>
          <div class="form-group-modal">
            <label for="prod-price">Retail Price (₱) *</label>
            <input type="number" step="0.01" min="0" id="prod-price" required placeholder="150.00">
          </div>
        </div>

        <div class="form-group-modal">
          <label for="prod-threshold">Min Low-Stock Threshold Alert</label>
          <input type="number" id="prod-threshold" min="1" value="20" placeholder="20">
          <small class="helper-text">Trigger dashboard notifications when stock reaches or falls below this level.</small>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" data-close="product-modal">Cancel Details</button>
          <button type="submit" class="btn-confirm-gold" id="btn-save-product">Commit Product</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 2. MODAL: ADD/EDIT CASHIER -->
  <div class="modal-overlay" id="cashier-modal">
    <div class="modal-card">
      <div class="modal-header">
        <h3 id="cashier-modal-title">Register Cashier Employee</h3>
        <button class="modal-close-btn" data-close="cashier-modal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="cashier-form">
        <input type="hidden" id="cashier-id" name="id">

        <div class="form-group-modal">
          <label for="cashier-name">Employee Full Name *</label>
          <input type="text" id="cashier-name" required placeholder="e.g. Jane Doe">
        </div>

        <div class="form-group-modal">
          <label for="cashier-username">Access Username *</label>
          <input type="text" id="cashier-username" required placeholder="e.g. jane123">
        </div>

        <div class="form-group-modal">
          <label for="cashier-password" id="label-cashier-pass">Access Password *</label>
          <input type="password" id="cashier-password" required placeholder="Min 6 characters">
          <small class="helper-text" id="pass-helper-text">Hashed automatically using industry-grade PHP encryption.</small>
        </div>

        <div class="form-group-modal" id="cashier-status-container" style="display: none;">
          <label for="cashier-status">Employee Account Status</label>
          <select id="cashier-status">
            <option value="active">Active System Operator</option>
            <option value="inactive">Suspended / Deactivated</option>
          </select>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" data-close="cashier-modal">Cancel Credentials</button>
          <button type="submit" class="btn-confirm-gold">Save Account</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 3. MODAL: ADD EXPENSE -->
  <div class="modal-overlay" id="expense-modal">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Record Shop Outlay Expense</h3>
        <button class="modal-close-btn" data-close="expense-modal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="expense-form">
        <div class="form-group-modal">
          <label for="exp-desc">Outlay Description / Purpose *</label>
          <input type="text" id="exp-desc" required placeholder="e.g. Mugs Supplier restocking payment">
        </div>

        <div class="form-row">
          <div class="form-group-modal">
            <label for="exp-category">Expense Classification</label>
            <select id="exp-category">
              <option value="Supplier Payables">Supplier Payables</option>
              <option value="Store Operations">Store Operations</option>
              <option value="Utilities Bill">Utilities Bill</option>
              <option value="Marketing & Promo">Marketing & Promo</option>
              <option value="Staff Petty Cash">Staff Petty Cash</option>
            </select>
          </div>
          <div class="form-group-modal">
            <label for="exp-amount">Outlay Amount (₱) *</label>
            <input type="number" step="0.01" min="0.01" id="exp-amount" required placeholder="1250.00">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" data-close="expense-modal">Cancel Log</button>
          <button type="submit" class="btn-confirm-gold">Record Expense</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Load Dashboard Business Scripts -->
  <script src="owner.js?v=<?php echo time(); ?>"></script>

</body>
</html>
