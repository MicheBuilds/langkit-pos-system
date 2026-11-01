<?php
session_start();
require_once 'db.php';

// Auth Guard: Both Cashier and Owner can access POS
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'cashier' && $_SESSION['user_role'] !== 'owner')) {
  header('Location: login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales POS Terminal - Langkit University Shop</title>
  
  <!-- Font Styles -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;900&family=Cinzel:wght@700&display=swap" rel="stylesheet">
  
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- External Stylesheet -->
  <link rel="stylesheet" href="styles.css">
</head>
<body class="pos-panel-body">

  <!-- Background Decorative Blurs -->
  <div class="decor-blob decor-1"></div>
  <div class="decor-blob decor-2"></div>

  <div class="pos-wrapper">
    
    <!-- LEFT PANEL: CATALOG -->
    <main class="pos-catalog-panel">
      <!-- POS Brand Header -->
      <header class="pos-header">
        <div class="brand-pos-header">
          <img src="logo.svg" alt="Langkit Logo" class="pos-logo-icon">
          <div class="pos-brand-text">
            <h2>Langkit University Shop</h2>
            <span>Point of Sales Terminal</span>
          </div>
        </div>
        
        <div class="pos-actions-bar">
          <div class="pos-search-box">
            <i class="fa-solid fa-barcode"></i>
            <input type="text" id="pos-search-input" placeholder="Search product name or scan SKU...">
          </div>
          <div class="pos-category-filters" id="pos-filters-container">
            <button class="filter-pill active" data-cat="ALL">All Catalog</button>
            <button class="filter-pill" data-cat="Apparel">Apparel</button>
            <button class="filter-pill" data-cat="Accessories">Accessories</button>
            <button class="filter-pill" data-cat="Drinkware">Drinkware</button>
          </div>
        </div>
      </header>

      <!-- Products Grid -->
      <div class="pos-products-scroll-area">
        <div class="pos-products-grid" id="pos-catalog-grid">
          <!-- JS dynamic renders -->
        </div>
      </div>
    </main>

    <!-- RIGHT PANEL: SALES CART & DRAWER -->
    <aside class="pos-cart-panel">
      
      <!-- User / Shift accountability header -->
      <div class="cart-user-header">
        <div class="cart-user-details">
          <div class="cart-avatar">
            <i class="fa-solid fa-cash-register"></i>
          </div>
          <div class="cart-details">
            <h4 id="pos-cashier-display-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h4>
            <span class="badge-role gold"><?php echo strtoupper($_SESSION['user_role']); ?> SESSION</span>
          </div>
        </div>
        
        <div class="shift-indicator-row">
          <span class="badge" id="shift-badge-status">CHECKING SHIFT...</span>
          <button class="action-btn-gold btn-sm" id="btn-shift-tools" style="display: none;">
            <i class="fa-solid fa-toolbox"></i>
            <span>Shift Tools</span>
          </button>
        </div>
      </div>

      <!-- Main Cart Items container -->
      <div class="cart-items-body">
        <div class="cart-items-header">
          <h3>Shopping Cart</h3>
          <span class="cart-qty-badge" id="cart-total-qty">0 items</span>
        </div>
        
        <div class="cart-items-scroll" id="cart-items-list-container">
          <!-- Placeholder when empty -->
          <div class="empty-cart-placeholder">
            <i class="fa-solid fa-basket-shopping"></i>
            <p>Shopping Cart is currently empty.</p>
            <span>Scan barcode SKU or select products from the catalog to begin order.</span>
          </div>
        </div>
      </div>

      <!-- Bill summary computation footer -->
      <div class="cart-bill-footer">
        <div class="bill-line">
          <span>Subtotal</span>
          <span class="font-medium" id="bill-subtotal">₱0.00</span>
        </div>
        
        <div class="bill-line">
          <div class="discount-label-group">
            <span>Discount Offer</span>
            <select id="bill-discount-select">
              <option value="0">None (0%)</option>
              <option value="5">Student Promo (5%)</option>
              <option value="10">Faculty Discount (10%)</option>
              <option value="12">Loyalty Reward (12%)</option>
              <option value="20">PWD/Senior Citizen (20%)</option>
            </select>
          </div>
          <span class="text-green font-medium" id="bill-discount-val">-₱0.00</span>
        </div>

        <div class="bill-line">
          <span>Sales VAT (12%)</span>
          <span class="font-medium" id="bill-tax">₱0.00</span>
        </div>

        <div class="bill-grand-line">
          <span>Grand Total</span>
          <span id="bill-grand-total">₱0.00</span>
        </div>

        <!-- Cart action buttons -->
        <div class="cart-action-buttons">
          <button class="btn-clear-cart" id="btn-clear-pos-cart">
            <i class="fa-solid fa-trash-can"></i>
            <span>Clear Cart</span>
          </button>
          
          <button class="btn-checkout-pos" id="btn-proceed-checkout" disabled>
            <span>PROCEED TO CHECKOUT</span>
            <i class="fa-solid fa-credit-card"></i>
          </button>
        </div>

        <!-- Back to admin for Owner -->
        <?php if ($_SESSION['user_role'] === 'owner'): ?>
          <a href="owner.php" class="back-admin-link">
            <i class="fa-solid fa-sliders"></i>
            <span>Return to Administrative Dashboard</span>
          </a>
        <?php else: ?>
          <a href="logout.php" class="back-admin-link logout">
            <i class="fa-solid fa-power-off"></i>
            <span>Secure Logout Session</span>
          </a>
        <?php endif; ?>
      </div>

    </aside>

  </div>

  <!-- ========================================== -->
  <!--               MODAL DIALOGS                -->
  <!-- ========================================== -->

  <!-- 1. MODAL: SHIFT OPEN DRAWER INITIATOR -->
  <div class="modal-overlay" id="shift-open-modal">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Open Sales Accountability Shift</h3>
      </div>
      <form id="shift-open-form">
        <div class="modal-body-pad">
          <div class="shift-welcome-badge">
            <i class="fa-solid fa-cash-register"></i>
            <div>
              <strong>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</strong>
              <p>To begin processing product checkout transactions, you must first register your active drawer accountability shift.</p>
            </div>
          </div>
          
          <div class="form-group-modal">
            <label for="shift-opening-cash">Opening Drawer Cash Petty (₱) *</label>
            <input type="number" step="0.01" min="0" id="shift-opening-cash" required value="1000.00" placeholder="1000.00">
            <small class="helper-text">Enter the sum of cash bills and coins seeded in the physical register drawer drawer.</small>
          </div>
        </div>
        
        <div class="modal-footer">
          <?php if ($_SESSION['user_role'] === 'owner'): ?>
            <a href="owner.php" class="btn-cancel" style="text-decoration: none; text-align: center; line-height: 2.2;">Cancel & Exit</a>
          <?php else: ?>
            <a href="logout.php" class="btn-cancel" style="text-decoration: none; text-align: center; line-height: 2.2;">Cancel & Logout</a>
          <?php endif; ?>
          <button type="submit" class="btn-confirm-gold">Verify & Open Shift</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 2. MODAL: SHIFT CLOSE / TOOLS PANEL -->
  <div class="modal-overlay" id="shift-tools-modal">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Shift Operations Drawer</h3>
        <button class="modal-close-btn" data-close="shift-tools-modal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body-pad">
        <h4 class="drawer-section-title">Current Shift Accountability Summary</h4>
        
        <div class="shift-stats-summary shadow-card">
          <div class="summary-line">
            <span>Logged Cashier:</span>
            <span id="st-cashier">Loading...</span>
          </div>
          <div class="summary-line">
            <span>Shift Started Time:</span>
            <span id="st-open-time">Loading...</span>
          </div>
          <div class="summary-line">
            <span>Opening Drawer Seed:</span>
            <span id="st-opening-cash">₱0.00</span>
          </div>
          <div class="summary-line">
            <span>Accumulated Net Sales:</span>
            <span class="text-green font-bold" id="st-sales-total">₱0.00</span>
          </div>
          <div class="summary-line highlighted">
            <span>Expected Drawer Cash Balance:</span>
            <span id="st-expected-cash">₱0.00</span>
          </div>
        </div>

        <form id="shift-close-form" class="shift-drawer-close-form">
          <h4 class="drawer-section-title text-red">Close Drawer & Count Cash Out</h4>
          <p class="drawer-instructions">Count all physical bills and coins inside the drawer, then enter the absolute total below to close the accountability shift logs.</p>
          
          <div class="form-group-modal">
            <label for="shift-closing-cash">Actual Counted Cash Drawer (₱) *</label>
            <input type="number" step="0.01" min="0" id="shift-closing-cash" required placeholder="0.00">
          </div>

          <div class="modal-footer" style="padding-top: 15px; margin-top: 15px; border-top: 1px solid rgba(45,45,56,0.1);">
            <button type="button" class="btn-cancel" data-close="shift-tools-modal">Resume POS</button>
            <button type="submit" class="btn-confirm-red">
              <i class="fa-solid fa-lock"></i>
              <span>Close Shift & Count Out</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 3. MODAL: ADD TO CART CONFIRMATION & SIZE SELECTION -->
  <div class="modal-overlay" id="add-to-cart-modal">
    <div class="modal-card select-modal">
      <div class="modal-header">
        <h3>Confirm Cart Addition?</h3>
        <button class="modal-close-btn" data-close="add-to-cart-modal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="add-to-cart-form">
        <input type="hidden" id="ac-product-id">
        
        <div class="modal-body-pad select-body">
          <div class="ac-product-header">
            <div class="ac-img-wrapper">
              <img src="" id="ac-product-img" alt="" onerror="this.src='logo.svg';">
            </div>
            <div class="ac-details">
              <h3 id="ac-product-name">Product Name</h3>
              <span class="category-tag" id="ac-product-category">Category</span>
              <p class="ac-price" id="ac-product-price">₱0.00</p>
              <span class="ac-stock" id="ac-product-stock">Stock: 0 remaining</span>
            </div>
          </div>

          <div class="form-group-modal" id="ac-size-container" style="display: none;">
            <label for="ac-size-select">Select Apparel Size Variant</label>
            <select id="ac-size-select">
              <option value="S">Small (S)</option>
              <option value="M">Medium (M)</option>
              <option value="L">Large (L)</option>
              <option value="XL">Extra Large (XL)</option>
            </select>
          </div>

          <div class="form-group-modal">
            <label for="ac-qty-input">Select Order Quantity</label>
            <div class="qty-stepper">
              <button type="button" class="qty-btn" id="ac-qty-minus"><i class="fa-solid fa-minus"></i></button>
              <input type="number" id="ac-qty-input" min="1" value="1" readonly>
              <button type="button" class="qty-btn" id="ac-qty-plus"><i class="fa-solid fa-plus"></i></button>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" data-close="add-to-cart-modal">Cancel Add</button>
          <button type="submit" class="btn-confirm-gold">
            <i class="fa-solid fa-cart-plus"></i>
            <span>Confirm Add to Cart</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- 4. MODAL: PROCEED TO CHECKOUT BILLING & RECEIPTS -->
  <div class="modal-overlay" id="checkout-modal">
    <div class="modal-card select-modal billing-modal">
      <div class="modal-header">
        <h3>Confirm Order Checkout?</h3>
        <button class="modal-close-btn" data-close="checkout-modal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="checkout-form">
        <div class="modal-body-pad billing-body">
          <div class="billing-grid">
            
            <!-- Left Side: Invoice details -->
            <div class="billing-left-summary">
              <h4>Checkout Bill Summary</h4>
              <div class="checkout-totals-box">
                <div class="bill-line small">
                  <span>Subtotal sum:</span>
                  <span id="ch-subtotal">₱0.00</span>
                </div>
                <div class="bill-line small">
                  <span>Applied Promo Discount:</span>
                  <span class="text-green" id="ch-discount">-₱0.00</span>
                </div>
                <div class="bill-line small">
                  <span>VAT 12% Tax:</span>
                  <span id="ch-tax">₱0.00</span>
                </div>
                <div class="bill-line grand-ch">
                  <span>Grand Total Bill:</span>
                  <span id="ch-grand-total">₱0.00</span>
                </div>
              </div>
              
              <!-- Gmail digital SMTP Receipt simulation -->
              <div class="form-group-modal gmail-input-wrapper">
                <label for="ch-buyer-email">
                  <i class="fa-solid fa-envelope text-gold"></i>
                  <span>Send E-Receipt (Buyer Gmail Address)</span>
                </label>
                <input type="email" id="ch-buyer-email" placeholder="e.g. buyer@gmail.com (Optional)">
                <small class="helper-text">If filled, compiles full invoice HTML and triggers local SMTP dispatch simulation.</small>
              </div>
            </div>

            <!-- Right Side: Cash handling -->
            <div class="billing-right-cash">
              <h4>Register Cash Tendered</h4>
              
              <div class="form-group-modal">
                <label for="ch-customer-type">Customer Category</label>
                <select id="ch-customer-type">
                  <option value="Walk-in Customer">Walk-in Customer</option>
                  <option value="University Student">University Student</option>
                  <option value="Campus Faculty">Campus Faculty</option>
                  <option value="Administrative Staff">Administrative Staff</option>
                  <option value="Store Supplier Agent">Store Supplier Agent</option>
                </select>
              </div>

              <div class="form-group-modal">
                <label for="ch-cash-tendered">Amount Tendered Cash (₱) *</label>
                <input type="number" step="0.01" min="0" id="ch-cash-tendered" required placeholder="0.00" style="font-size: 1.4rem; font-weight: 700; color: var(--primary-red); text-align: right;">
              </div>

              <!-- Quick cash buttons -->
              <div class="quick-cash-grid">
                <button type="button" class="btn-quick-cash" data-cash="100">₱100</button>
                <button type="button" class="btn-quick-cash" data-cash="200">₱200</button>
                <button type="button" class="btn-quick-cash" data-cash="500">₱500</button>
                <button type="button" class="btn-quick-cash" data-cash="1000">₱1,000</button>
              </div>

              <div class="change-due-box">
                <span>Change Due Back:</span>
                <span class="change-value text-green" id="ch-change-due">₱0.00</span>
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" data-close="checkout-modal">Cancel Order</button>
          <button type="submit" class="btn-confirm-gold" id="btn-complete-checkout" disabled>
            <i class="fa-solid fa-print"></i>
            <span>Confirm & Print Invoice</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- 5. MODAL: DIGITAL PAPER THERMAL RECEIPT SUCCESS INVOICE -->
  <div class="modal-overlay" id="receipt-success-modal">
    <div class="modal-card receipt-success-card">
      <div class="modal-header" style="background: var(--primary-red); color: #fff;">
        <h3>Sales Checkout Completed</h3>
      </div>
      
      <div class="modal-body-pad receipt-success-body">
        <div class="success-icon-banner">
          <i class="fa-solid fa-circle-check text-green animate-bounce"></i>
          <h4>Sale Transaction Processed!</h4>
          <p>The transaction has been safely committed to the SQL database ledger.</p>
        </div>

        <!-- SMTP Simulation logs printout box -->
        <div class="smtp-sim-logs-container" id="success-smtp-log-box" style="display: none;">
          <div class="smtp-logs-header">
            <i class="fa-solid fa-server"></i>
            <span>SMTP Email Receipt Server Log</span>
          </div>
          <pre class="smtp-log-text" id="success-smtp-log-text"></pre>
        </div>

        <!-- Simulated Paper Thermal Receipt view -->
        <div class="thermal-receipt-paper" id="thermal-receipt-container">
          <!-- Renders dynamically -->
        </div>

        <div class="receipt-footer-buttons">
          <button class="btn-action-print" onclick="window.print()">
            <i class="fa-solid fa-print"></i>
            <span>Print Physical Paper</span>
          </button>
          <button class="btn-confirm-gold" id="btn-close-receipt-success" style="width: 100%;">
            <span>Open New Sale Transaction</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Load POS Operations Engine -->
  <script src="cashier.js"></script>

</body>
</html>
