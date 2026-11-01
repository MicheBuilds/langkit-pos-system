// Langkit University Shop - Cashier Sales POS Terminal Front-End Engine
// Interacts with api.php for shifts logs, products catalog, and checkout transactions

document.addEventListener('DOMContentLoaded', () => {
  // --- STATE ---
  let products = [];
  let cart = [];
  let activeShift = null;
  let selectedProduct = null;
  
  // Filters state
  let searchQuery = '';
  let activeCategory = 'ALL';

  // --- INITIALIZATION ---
  checkActiveShift();
  setupFilterControls();
  setupCartControls();
  setupModalHandlers();

  // --- SHIFT CONTROL FLOW ---
  function checkActiveShift() {
    fetch('api.php?action=get_active_shift')
      .then(res => res.json())
      .then(res => {
        const badge = document.getElementById('shift-badge-status');
        const toolsBtn = document.getElementById('btn-shift-tools');

        if (res.status === 'success') {
          if (res.has_active_shift) {
            activeShift = res.shift;
            
            badge.className = 'badge green';
            badge.textContent = `DRAWER OPEN (₱${parseFloat(activeShift.opening_cash).toFixed(2)})`;
            toolsBtn.style.display = 'inline-flex';
            
            // Enable checkout flow and load catalog
            loadPOSCatalog();
          } else {
            activeShift = null;
            badge.className = 'badge red animate-pulse';
            badge.textContent = 'NO ACTIVE SHIFT DRAWER';
            toolsBtn.style.display = 'none';
            
            // Force Open Shift Modal
            document.getElementById('shift-open-modal').classList.add('open');
          }
        }
      })
      .catch(err => console.error('Shift check error:', err));
  }

  // Handle opening shift drawer
  document.getElementById('shift-open-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const cash = parseFloat(document.getElementById('shift-opening-cash').value);

    fetch('api.php?action=open_shift', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ opening_cash: cash })
    })
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          alert('Sales Accountability Shift drawer opened successfully.');
          document.getElementById('shift-open-modal').classList.remove('open');
          checkActiveShift();
        } else {
          alert('SHIFT ERROR: ' + res.message);
        }
      })
      .catch(err => console.error(err));
  });

  // Handle closing shift drawer
  document.getElementById('shift-close-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const closingCash = parseFloat(document.getElementById('shift-closing-cash').value);

    if (confirm('Are you sure you want to CLOSE the register drawer and finalize this shift accountability record?\n\nThis will lock all transaction records and log any cash discrepancies!')) {
      fetch('api.php?action=close_shift', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ closing_cash: closingCash })
      })
        .then(res => res.json())
        .then(res => {
          if (res.status === 'success') {
            const summary = res.summary;
            alert(
              `SHIFT CLOSED ACCOUNTABILITY SUCCESS!\n\n` +
              `Total Sales Intake: ₱${summary.sales_total.toFixed(2)}\n` +
              `Drawer Expected Cash: ₱${summary.expected_cash.toFixed(2)}\n` +
              `Drawer Counted Cash: ₱${summary.actual_cash.toFixed(2)}\n` +
              `Cash Discrepancy: ₱${summary.discrepancy.toFixed(2)}\n\n` +
              `Drawer locked. Thank you for your work!`
            );
            
            // Redirect or reload
            location.reload();
          } else {
            alert('SHIFT CLOSE ERROR: ' + res.message);
          }
        })
        .catch(err => console.error(err));
    }
  });

  // --- CATALOG LOADING & RENDERING ---
  function loadPOSCatalog() {
    fetch('api.php?action=get_products')
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          products = res.data;
          renderPOSCatalog();
        }
      })
      .catch(err => console.error(err));
  }

  function renderPOSCatalog() {
    const grid = document.getElementById('pos-catalog-grid');
    grid.innerHTML = '';

    // Filter
    let filtered = products.filter(p => {
      const matchSearch = p.name.toLowerCase().includes(searchQuery) || p.sku.toLowerCase().includes(searchQuery);
      const matchCat = activeCategory === 'ALL' || p.category === activeCategory;
      return matchSearch && matchCat;
    });

    if (filtered.length === 0) {
      grid.innerHTML = `
        <div class="empty-pos-placeholder">
          <i class="fa-solid fa-folder-open text-muted"></i>
          <p>No matching products available in the shop inventory catalog.</p>
        </div>
      `;
      return;
    }

    filtered.forEach(p => {
      const isOos = p.stock === 0;
      const isLow = p.stock <= p.threshold && p.stock > 0;
      
      let stockClass = 'stock-badge green';
      let stockText = `${p.stock} In Stock`;
      if (isOos) {
        stockClass = 'stock-badge red';
        stockText = 'OUT OF STOCK';
      } else if (isLow) {
        stockClass = 'stock-badge gold';
        stockText = `Low Stock (${p.stock})`;
      }

      const card = document.createElement('div');
      card.className = `pos-product-card ${isOos ? 'oos-card' : ''}`;
      card.setAttribute('data-id', p.id);

      card.innerHTML = `
        <div class="card-img-container">
          <img src="${p.image_path}" alt="${escapeHTML(p.name)}" onerror="this.src='logo.svg';">
          <span class="${stockClass}">${stockText}</span>
        </div>
        <div class="card-body">
          <span class="card-sku">${p.sku}</span>
          <h3 class="card-title">${escapeHTML(p.name)}</h3>
          <div class="card-footer">
            <span class="card-price">₱${parseFloat(p.price).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
            <button class="btn-card-add" ${isOos ? 'disabled' : ''}>
              <i class="fa-solid fa-plus"></i>
            </button>
          </div>
        </div>
      `;

      if (!isOos) {
        card.addEventListener('click', (e) => {
          // If clicking card-add button or card itself
          openAddToCartModal(p);
        });
      }

      grid.appendChild(card);
    });
  }

  // --- FILTER CONTROLS ---
  function setupFilterControls() {
    // Search
    document.getElementById('pos-search-input').addEventListener('input', (e) => {
      searchQuery = e.target.value.toLowerCase();
      renderPOSCatalog();
    });

    // Barcode quick scan listener (Press Enter in search box)
    document.getElementById('pos-search-input').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        // If query is an exact match for SKU
        const match = products.find(p => p.sku.toUpperCase() === searchQuery.toUpperCase());
        if (match && match.stock > 0) {
          openAddToCartModal(match);
          // Clear search
          e.target.value = '';
          searchQuery = '';
          renderPOSCatalog();
        }
      }
    });

    // Categories
    const pills = document.querySelectorAll('.filter-pill');
    pills.forEach(pill => {
      pill.addEventListener('click', () => {
        pills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        activeCategory = pill.getAttribute('data-cat');
        renderPOSCatalog();
      });
    });
  }

  // --- ADD TO CART MODAL CONTROLS ---
  function openAddToCartModal(p) {
    selectedProduct = p;
    
    // Bind modal info
    document.getElementById('ac-product-id').value = p.id;
    document.getElementById('ac-product-img').src = p.image_path;
    document.getElementById('ac-product-name').textContent = p.name;
    document.getElementById('ac-product-category').textContent = p.category;
    document.getElementById('ac-product-price').textContent = `₱${parseFloat(p.price).toFixed(2)}`;
    
    const stockEl = document.getElementById('ac-product-stock');
    stockEl.textContent = `Active Stock: ${p.stock} units remaining`;
    stockEl.className = p.stock <= p.threshold ? 'ac-stock text-gold font-bold' : 'ac-stock';

    // Reset stepper
    const qtyInput = document.getElementById('ac-qty-input');
    qtyInput.value = '1';

    // Show Size Variant choice only if Apparel category
    const sizeContainer = document.getElementById('ac-size-container');
    if (p.category === 'Apparel') {
      sizeContainer.style.display = 'block';
      document.getElementById('ac-size-select').value = 'M'; // default medium
    } else {
      sizeContainer.style.display = 'none';
    }

    document.getElementById('add-to-cart-modal').classList.add('open');
  }

  // Modal forms
  document.getElementById('ac-qty-minus').addEventListener('click', () => {
    const el = document.getElementById('ac-qty-input');
    let val = parseInt(el.value);
    if (val > 1) el.value = val - 1;
  });

  document.getElementById('ac-qty-plus').addEventListener('click', () => {
    const el = document.getElementById('ac-qty-input');
    let val = parseInt(el.value);
    if (val < selectedProduct.stock) el.value = val + 1;
  });

  document.getElementById('add-to-cart-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const qty = parseInt(document.getElementById('ac-qty-input').value);
    const size = selectedProduct.category === 'Apparel' ? document.getElementById('ac-size-select').value : 'Standard';

    // Confirm Cart Addition Alert Double Confirm
    if (confirm(`ADD TO BASKET?\nConfirm adding ${qty}x ${selectedProduct.name} (${size}) to customer cart?`)) {
      addToCart(selectedProduct, qty, size);
      document.getElementById('add-to-cart-modal').classList.remove('open');
    }
  });

  // Add Item to cart state
  function addToCart(p, qty, size) {
    // Check if item with same ID and size exists in cart
    const cartIdx = cart.findIndex(item => item.id === p.id && item.size === size);

    // Double check stock sum limit
    const currentQtyInCart = cartIdx !== -1 ? cart[cartIdx].qty : 0;
    if (currentQtyInCart + qty > p.stock) {
      alert(`OVER-STOCK BLOCKED!\nCannot add requested quantity. Only ${p.stock - currentQtyInCart} stock available (Cart already has ${currentQtyInCart}).`);
      return;
    }

    if (cartIdx !== -1) {
      cart[cartIdx].qty += qty;
    } else {
      cart.push({
        id: p.id,
        name: p.name,
        sku: p.sku,
        price: parseFloat(p.price),
        qty: qty,
        size: size,
        image_path: p.image_path,
        max_stock: p.stock
      });
    }

    renderCart();
  }

  // --- SHOPPING CART MANAGEMENT ---
  function setupCartControls() {
    // Clear Cart
    document.getElementById('btn-clear-pos-cart').addEventListener('click', () => {
      if (cart.length > 0 && confirm('Are you sure you want to completely EMPTY the customer shopping cart?')) {
        cart = [];
        renderCart();
      }
    });

    // Trigger Checkout Modal
    document.getElementById('btn-proceed-checkout').addEventListener('click', openCheckoutModal);
  }

  function renderCart() {
    const list = document.getElementById('cart-items-list-container');
    const totalQtyEl = document.getElementById('cart-total-qty');
    
    list.innerHTML = '';
    
    let totalItems = 0;
    let subtotal = 0.00;

    if (cart.length === 0) {
      list.innerHTML = `
        <div class="empty-cart-placeholder">
          <i class="fa-solid fa-basket-shopping"></i>
          <p>Shopping Cart is currently empty.</p>
          <span>Scan barcode SKU or select products from the catalog to begin order.</span>
        </div>
      `;
      totalQtyEl.textContent = '0 items';
      document.getElementById('btn-proceed-checkout').disabled = true;
      recomputeBillSums(0, 0);
      return;
    }

    cart.forEach((item, index) => {
      totalItems += item.qty;
      const itemSub = item.price * item.qty;
      subtotal += itemSub;

      const row = document.createElement('div');
      row.className = 'cart-item-row';
      row.innerHTML = `
        <div class="cart-item-details">
          <img src="${item.image_path}" alt="" onerror="this.src='logo.svg';">
          <div class="cart-item-info">
            <span class="cart-item-name">${escapeHTML(item.name)}</span>
            <div class="cart-item-tags">
              <span class="size-tag">${item.size}</span>
              <span class="sku-tag">${item.sku}</span>
            </div>
          </div>
        </div>
        
        <div class="cart-item-qty-cell">
          <div class="qty-sm-stepper">
            <button class="cart-qty-btn-minus" data-idx="${index}"><i class="fa-solid fa-minus"></i></button>
            <span class="cart-qty-display">${item.qty}</span>
            <button class="cart-qty-btn-plus" data-idx="${index}"><i class="fa-solid fa-plus"></i></button>
          </div>
        </div>

        <div class="cart-item-price-cell">
          <span class="cart-item-sub">₱${itemSub.toFixed(2)}</span>
          <small class="cart-item-unit">₱${item.price.toFixed(2)} ea</small>
        </div>

        <button class="btn-cart-remove" data-idx="${index}">
          <i class="fa-solid fa-trash-can"></i>
        </button>
      `;
      list.appendChild(row);
    });

    totalQtyEl.textContent = `${totalItems} items`;
    document.getElementById('btn-proceed-checkout').disabled = false;

    // Trigger Calculations
    recomputeBillSums(subtotal, totalItems);

    // Bind item edits
    document.querySelectorAll('.cart-qty-btn-minus').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.getAttribute('data-idx'));
        if (cart[idx].qty > 1) {
          cart[idx].qty--;
          renderCart();
        }
      });
    });

    document.querySelectorAll('.cart-qty-btn-plus').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.getAttribute('data-idx'));
        if (cart[idx].qty < cart[idx].max_stock) {
          cart[idx].qty++;
          renderCart();
        } else {
          alert(`STOCK CAP REACHED!\nOnly ${cart[idx].max_stock} units available in shop store catalog.`);
        }
      });
    });

    document.querySelectorAll('.btn-cart-remove').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.getAttribute('data-idx'));
        cart.splice(idx, 1);
        renderCart();
      });
    });
  }

  // BIND DISCOUNT DROPDOWN
  document.getElementById('bill-discount-select').addEventListener('change', () => {
    // Re-render cart calculations
    renderCart();
  });

  function recomputeBillSums(subtotal, totalItems) {
    const taxRate = 0.12; // 12% VAT in Philippines
    const discPercent = parseFloat(document.getElementById('bill-discount-select').value);
    
    const discAmount = subtotal * (discPercent / 100);
    const taxableBase = subtotal - discAmount;
    const taxAmount = taxableBase * taxRate;
    const grandTotal = taxableBase + taxAmount;

    document.getElementById('bill-subtotal').textContent = formatPeso(subtotal);
    document.getElementById('bill-discount-val').textContent = '-' + formatPeso(discAmount);
    document.getElementById('bill-tax').textContent = formatPeso(taxAmount);
    document.getElementById('bill-grand-total').textContent = formatPeso(grandTotal);

    // Temporary save values to document fields for checkout modal retrieval
    document.getElementById('bill-grand-total').setAttribute('data-subtotal', subtotal);
    document.getElementById('bill-grand-total').setAttribute('data-discount-percent', discPercent);
    document.getElementById('bill-grand-total').setAttribute('data-discount-amount', discAmount);
    document.getElementById('bill-grand-total').setAttribute('data-tax', taxAmount);
    document.getElementById('bill-grand-total').setAttribute('data-total', grandTotal);
  }

  // --- CHECKOUT PROCESS CONTROLS ---
  function openCheckoutModal() {
    const totalEl = document.getElementById('bill-grand-total');
    const sub = parseFloat(totalEl.getAttribute('data-subtotal'));
    const dp = parseFloat(totalEl.getAttribute('data-discount-percent'));
    const da = parseFloat(totalEl.getAttribute('data-discount-amount'));
    const tax = parseFloat(totalEl.getAttribute('data-tax'));
    const total = parseFloat(totalEl.getAttribute('data-total'));

    // Bind summary modal
    document.getElementById('ch-subtotal').textContent = formatPeso(sub);
    document.getElementById('ch-discount').textContent = '-' + formatPeso(da);
    document.getElementById('ch-tax').textContent = formatPeso(tax);
    document.getElementById('ch-grand-total').textContent = formatPeso(total);
    
    // Save total to modal attribute
    document.getElementById('checkout-modal').setAttribute('data-grand-total', total);

    // Reset inputs
    document.getElementById('ch-buyer-email').value = '';
    document.getElementById('ch-cash-tendered').value = '';
    document.getElementById('ch-change-due').textContent = '₱0.00';
    document.getElementById('ch-customer-type').value = 'Walk-in Customer';

    document.getElementById('btn-complete-checkout').disabled = true;

    // Show modal
    document.getElementById('checkout-modal').classList.add('open');
  }

  // Handle cash tendered input change
  const cashInput = document.getElementById('ch-cash-tendered');
  cashInput.addEventListener('input', (e) => {
    calculateChangeDue();
  });

  // Quick cash buttons
  document.querySelectorAll('.btn-quick-cash').forEach(btn => {
    btn.addEventListener('click', () => {
      const value = parseFloat(btn.getAttribute('data-cash'));
      cashInput.value = value.toFixed(2);
      calculateChangeDue();
    });
  });

  function calculateChangeDue() {
    const grandTotal = parseFloat(document.getElementById('checkout-modal').getAttribute('data-grand-total'));
    const cash = parseFloat(cashInput.value) || 0.00;
    const diff = cash - grandTotal;

    const changeEl = document.getElementById('ch-change-due');
    const btn = document.getElementById('btn-complete-checkout');

    if (diff >= 0) {
      changeEl.textContent = formatPeso(diff);
      changeEl.className = 'change-value text-green font-bold';
      btn.disabled = false;
    } else {
      changeEl.textContent = 'Insufficient Cash';
      changeEl.className = 'change-value text-red font-bold animate-pulse';
      btn.disabled = true;
    }
  }

  // Handle checkout form submit
  document.getElementById('checkout-form').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const grandTotal = parseFloat(document.getElementById('checkout-modal').getAttribute('data-grand-total'));
    const cash = parseFloat(cashInput.value);
    const buyerEmail = document.getElementById('ch-buyer-email').value.trim();
    const customerType = document.getElementById('ch-customer-type').value;
    const discountPercent = parseFloat(document.getElementById('bill-grand-total').getAttribute('data-discount-percent'));

    if (confirm(`CHECKOUT CONFIRMATION?\n\nProceed to authorize this order transaction invoice?\n\nGrand Total Bill: ₱${grandTotal.toFixed(2)}\nCash Received: ₱${cash.toFixed(2)}\nChange Due: ₱${(cash-grandTotal).toFixed(2)}\nSimulated E-Receipt: ${buyerEmail || 'None requested'}`)) {
      
      const payload = {
        cart: cart,
        customer_type: customerType,
        discount_percent: discountPercent,
        buyer_email: buyerEmail
      };

      fetch('api.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(res => res.json())
        .then(res => {
          if (res.status === 'success') {
            // Close checkout modal
            document.getElementById('checkout-modal').classList.remove('open');
            
            // Build Dynamic Receipt Paper & SMTP Log Success Panels
            triggerReceiptSuccessDisplay(res, cash);
          } else {
            alert('CHECKOUT ERROR: ' + res.message);
          }
        })
        .catch(err => console.error('Checkout API error:', err));
    }
  });

  // --- SUCCESS INVOICE DISPLAY RENDERER ---
  function triggerReceiptSuccessDisplay(res, cashTendered) {
    const totalEl = document.getElementById('bill-grand-total');
    const sub = parseFloat(totalEl.getAttribute('data-subtotal'));
    const dp = parseFloat(totalEl.getAttribute('data-discount-percent'));
    const da = parseFloat(totalEl.getAttribute('data-discount-amount'));
    const tax = parseFloat(totalEl.getAttribute('data-tax'));
    const total = parseFloat(totalEl.getAttribute('data-total'));
    
    // BIND SMTP DIGITAL LOG
    const smtpBox = document.getElementById('success-smtp-log-box');
    const smtpText = document.getElementById('success-smtp-log-text');
    if (res.email_log && res.email_log !== '') {
      smtpBox.style.display = 'block';
      smtpText.textContent = res.email_log;
    } else {
      smtpBox.style.display = 'none';
    }

    // BUILD DIGITAL THERMAL RECEIPT
    const receiptContainer = document.getElementById('thermal-receipt-container');
    
    let itemsHtml = '';
    cart.forEach(item => {
      const lineSub = item.qty * item.price;
      itemsHtml += `
        <div class="tr-item-row">
          <div class="tr-item-left">
            <span>${escapeHTML(item.name)}</span>
            <small>${item.qty} pcs @ ₱${item.price.toFixed(2)} (${item.size})</small>
          </div>
          <span class="tr-item-right">₱${lineSub.toFixed(2)}</span>
        </div>
      `;
    });

    receiptContainer.innerHTML = `
      <div class="tr-header">
        <h2 class="tr-store-name">LANGKIT UNIVERSITY SHOP</h2>
        <p class="tr-store-sub">Mindanao State University - IIT</p>
        <p class="tr-store-address">Tibanga, Iligan City, Lanao del Norte</p>
      </div>

      <div class="tr-invoice-meta">
        <div><strong>Receipt SKU Ref:</strong> <span>${res.transaction_id}</span></div>
        <div><strong>Processed Timestamp:</strong> <span>${new Date().toLocaleString()}</span></div>
        <div><strong>Served Cashier:</strong> <span>${document.getElementById('pos-cashier-display-name').textContent}</span></div>
        <div><strong>Customer Class:</strong> <span>${document.getElementById('ch-customer-type').value}</span></div>
      </div>

      <div class="tr-dashed-line"></div>

      <div class="tr-items-list">
        ${itemsHtml}
      </div>

      <div class="tr-dashed-line"></div>

      <div class="tr-totals-block">
        <div class="tr-totals-row">
          <span>Subtotal:</span>
          <span>₱${sub.toFixed(2)}</span>
        </div>
        ${dp > 0 ? `
        <div class="tr-totals-row">
          <span>Applied Discount (${dp}%):</span>
          <span>-₱${da.toFixed(2)}</span>
        </div>` : ''}
        <div class="tr-totals-row">
          <span>Sales VAT (12%):</span>
          <span>₱${tax.toFixed(2)}</span>
        </div>
        <div class="tr-totals-row grand">
          <span>GRAND TOTAL BILL:</span>
          <span>₱${total.toFixed(2)}</span>
        </div>
        <div class="tr-dashed-line" style="margin: 6px 0;"></div>
        <div class="tr-totals-row">
          <span>Cash Tendered:</span>
          <span>₱${cashTendered.toFixed(2)}</span>
        </div>
        <div class="tr-totals-row green font-bold">
          <span>Change Due Back:</span>
          <span>₱${(cashTendered - total).toFixed(2)}</span>
        </div>
      </div>

      <div class="tr-dashed-line"></div>

      <div class="tr-footer">
        <p>Thank you for supporting MSU-IIT!</p>
        <p>This is your official university sales invoice.</p>
        <p>E-Receipt saved to local archive successfully.</p>
      </div>
    `;

    // Show Success screen modal
    document.getElementById('receipt-success-modal').classList.add('open');
  }

  // Click handler to exit success and reset POS cart
  document.getElementById('btn-close-receipt-success').addEventListener('click', () => {
    document.getElementById('receipt-success-modal').classList.remove('open');
    // Reset Cart array
    cart = [];
    renderCart();
    
    // Refresh catalog products stock
    loadPOSCatalog();
  });

  // --- MODALS REGISTRATION ---
  function setupModalHandlers() {
    // Dynamic close button triggers
    document.querySelectorAll('[data-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-close');
        document.getElementById(id).classList.remove('open');
      });
    });

    // Close on overlay clicks
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        // block closing active open shift or checkout success forced screens
        if (overlay.id === 'shift-open-modal' || overlay.id === 'receipt-success-modal') return;
        if (e.target === overlay) overlay.classList.remove('open');
      });
    });

    // Shift tools open drawer
    document.getElementById('btn-shift-tools').addEventListener('click', () => {
      if (activeShift) {
        document.getElementById('st-cashier').textContent = activeShift.cashier_name;
        document.getElementById('st-open-time').textContent = formatDateTime(activeShift.open_time);
        document.getElementById('st-opening-cash').textContent = formatPeso(activeShift.opening_cash);
        
        // Dynamically get shift metrics via api call
        fetch('api.php?action=get_active_shift')
          .then(res => res.json())
          .then(res => {
            if (res.status === 'success' && res.has_active_shift) {
              const currentShift = res.shift;
              document.getElementById('st-sales-total').textContent = formatPeso(currentShift.sales_total);
              
              const expected = parseFloat(currentShift.opening_cash) + parseFloat(currentShift.sales_total);
              document.getElementById('st-expected-cash').textContent = formatPeso(expected);
              
              // Reset counted input field
              document.getElementById('shift-closing-cash').value = '';

              // Open Shift Tools modal
              document.getElementById('shift-tools-modal').classList.add('open');
            }
          });
      }
    });
  }

  // --- REUSABLE FORMATTERS ---
  function formatPeso(val) {
    return '₱' + parseFloat(val).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function formatDateTime(sqlDate) {
    if (!sqlDate) return '—';
    const t = sqlDate.split(/[- :]/);
    if(t.length < 5) return sqlDate;
    const d = new Date(t[0], t[1]-1, t[2], t[3]||0, t[4]||0, t[5]||0);
    return d.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });
  }

  function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
  }
});
