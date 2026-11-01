// Langkit University Shop - Owner Dashboard Front-End Engine
// Interacts with api.php using JSON AJAX calls and binds charts via ChartJS

document.addEventListener('DOMContentLoaded', () => {
  // --- GLOBALS & STATE ---
  let products = [];
  let cashiers = [];
  let shifts = [];
  let transactions = [];
  let expenses = [];
  let activeTab = 'dashboard';
  
  // Charts instances
  let revenueChartInstance = null;
  let peakChartInstance = null;

  // --- CORE INITIALIZATION ---
  initClock();
  setupTabNavigation();
  setupModalControls();
  loadAllData();

  // Refresh data timer (every 30 seconds for live updates)
  setInterval(() => {
    if (activeTab === 'dashboard') {
      loadDashboardMetrics();
    }
  }, 30000);

  // --- CLOCK UTILITY ---
  function initClock() {
    const clock = document.getElementById('clock-display');
    const updateTime = () => {
      const now = new Date();
      const options = { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
      };
      clock.textContent = now.toLocaleDateString('en-US', options);
    };
    updateTime();
    setInterval(updateTime, 1000);
  }

  // --- TAB NAVIGATION ---
  function setupTabNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('.content-panel');
    const sectionTitleDisplay = document.getElementById('current-section-title');

    navLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const tab = link.getAttribute('data-tab');
        activeTab = tab;

        // Reset active classes on links
        navLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');

        // Show proper panel
        sections.forEach(sec => {
          sec.classList.remove('active');
          if (sec.id === `panel-${tab}`) {
            sec.classList.add('active');
          }
        });

        // Set Header Title
        sectionTitleDisplay.textContent = link.querySelector('span').textContent;

        // Render tab-specific loads
        renderTabContent(tab);
      });
    });

    // Mobile side-bar toggle
    const sidebar = document.querySelector('.admin-sidebar');
    const toggleBtn = document.querySelector('.menu-toggle-btn');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
      });
    }
  }

  function renderTabContent(tab) {
    switch (tab) {
      case 'dashboard':
        loadDashboardMetrics();
        break;
      case 'inventory':
        loadInventory();
        break;
      case 'employees':
        loadEmployeesAndShifts();
        break;
      case 'financials':
        loadFinancialsLedger();
        break;
      case 'audit':
        loadAuditTrails();
        break;
    }
  }

  // --- SERVICE ACTIONS: LOAD DATA ---
  function loadAllData() {
    loadDashboardMetrics();
  }

  // 1. DASHBOARD METRICS & CHARTS
  function loadDashboardMetrics() {
    fetch('api.php?action=get_owner_metrics')
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          bindDashboardMetrics(res.metrics);
        } else {
          console.error('Error fetching metrics:', res.message);
        }
      })
      .catch(err => console.error('Fetch error:', err));
  }

  function bindDashboardMetrics(m) {
    // Top Row Metrics (Peso Format ₱)
    document.getElementById('val-sales-today').textContent = formatPeso(m.sales_today);
    document.getElementById('val-sales-weekly').textContent = formatPeso(m.sales_weekly);
    document.getElementById('val-profit-estimate').textContent = formatPeso(m.profit_estimate);
    document.getElementById('val-inv-price').textContent = formatPeso(m.inv_val_price);
    document.getElementById('val-inv-cost').textContent = 'Cost basis: ' + formatPeso(m.inv_val_cost);

    // Low Stock Alert Banner
    const lowStockAlertBar = document.getElementById('low-stock-alert-bar');
    if (m.low_stock_count > 0 || m.out_of_stock_count > 0) {
      lowStockAlertBar.style.display = 'flex';
      lowStockAlertBar.querySelector('span').innerHTML = `<strong>Inventory Warning:</strong> There are <strong>${m.low_stock_count}</strong> low-stock products and <strong>${m.out_of_stock_count}</strong> items completely out of stock!`;
    } else {
      lowStockAlertBar.style.display = 'none';
    }

    // Dynamic Lists Binds
    // A. Best Sellers
    const bestSellersContainer = document.getElementById('best-sellers-list');
    bestSellersContainer.innerHTML = '';
    if (m.best_sellers && m.best_sellers.length > 0) {
      m.best_sellers.forEach(item => {
        const li = document.createElement('li');
        li.className = 'stats-item';
        li.innerHTML = `
          <div class="stats-item-left">
            <span class="stats-item-title">${escapeHTML(item.name)}</span>
            <span class="stats-item-sub">Total Units Sold: <strong>${item.units_sold} pcs</strong></span>
          </div>
          <div class="stats-item-right text-green">
            ${formatPeso(item.revenue)}
          </div>
        `;
        bestSellersContainer.appendChild(li);
      });
    } else {
      bestSellersContainer.innerHTML = '<li class="empty-list-placeholder">No checkout transactions recorded yet.</li>';
    }

    // B. Slow Movers
    const slowMoversContainer = document.getElementById('slow-movers-list');
    slowMoversContainer.innerHTML = '';
    if (m.slow_movers && m.slow_movers.length > 0) {
      m.slow_movers.forEach(item => {
        const li = document.createElement('li');
        li.className = 'stats-item';
        // Stock status
        const isOos = parseInt(item.stock) === 0;
        const stockClass = isOos ? 'text-red font-bold' : (parseInt(item.stock) <= 15 ? 'text-gold' : '');
        
        li.innerHTML = `
          <div class="stats-item-left">
            <span class="stats-item-title">${escapeHTML(item.name)}</span>
            <span class="stats-item-sub">SKU: ${item.sku} | Stock: <strong class="${stockClass}">${item.stock}</strong></span>
          </div>
          <div class="stats-item-right text-red font-bold">
            ${item.units_sold} sold
          </div>
        `;
        slowMoversContainer.appendChild(li);
      });
    } else {
      slowMoversContainer.innerHTML = '<li class="empty-list-placeholder">All products healthy.</li>';
    }

    // C. Cashier Performance
    const cashierContainer = document.getElementById('cashier-rankings-list');
    cashierContainer.innerHTML = '';
    if (m.cashier_rankings && m.cashier_rankings.length > 0) {
      m.cashier_rankings.forEach((cashier, index) => {
        const li = document.createElement('li');
        li.className = 'stats-item';
        
        // Custom crown icon for top cashier
        const crown = index === 0 ? '<i class="fa-solid fa-crown text-gold" style="margin-right: 5px;"></i>' : '';
        
        li.innerHTML = `
          <div class="stats-item-left">
            <span class="stats-item-title">${crown}${escapeHTML(cashier.cashier_name)}</span>
            <span class="stats-item-sub">Transactions Count: <strong>${cashier.transactions_count}</strong></span>
          </div>
          <div class="stats-item-right text-gold">
            ${formatPeso(cashier.total_revenue)}
          </div>
        `;
        cashierContainer.appendChild(li);
      });
    } else {
      cashierContainer.innerHTML = '<li class="empty-list-placeholder">No active sales logged.</li>';
    }

    // Render Charts
    renderRevenueChart(m.chart_data);
    renderPeakSalesChart(m.peak_hours);
  }

  // --- CHART GENERATORS (ChartJS) ---
  function renderRevenueChart(chartData) {
    const ctx = document.getElementById('revenueTrendChart').getContext('2d');
    
    // Process labels and totals
    const labels = [];
    const dataset = [];

    // Pre-fill last 7 days if empty
    if (chartData && chartData.length > 0) {
      chartData.forEach(d => {
        // Format YYYY-MM-DD to Mon DD
        const dateObj = new Date(d.day);
        const dayLabel = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        labels.push(dayLabel);
        dataset.push(parseFloat(d.total));
      });
    } else {
      // Seed placeholders
      for (let i = 6; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        labels.push(d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        dataset.push(0);
      }
    }

    if (revenueChartInstance) {
      revenueChartInstance.destroy();
    }

    revenueChartInstance = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Net Sales Volume (₱)',
          data: dataset,
          borderColor: '#8B0000',
          backgroundColor: 'rgba(139, 0, 0, 0.1)',
          borderWidth: 3,
          fill: true,
          tension: 0.3,
          pointBackgroundColor: '#D4AF37',
          pointBorderColor: '#FFF',
          pointRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            grid: { color: 'rgba(45, 45, 56, 0.1)' },
            ticks: {
              color: '#555',
              callback: function(value) { return '₱' + value; }
            }
          },
          x: {
            grid: { display: false },
            ticks: { color: '#555' }
          }
        }
      }
    });
  }

  function renderPeakSalesChart(peakHours) {
    const ctx = document.getElementById('peakSalesChart').getContext('2d');
    
    const labels = [];
    const dataset = [];

    if (peakHours && peakHours.length > 0) {
      peakHours.forEach(h => {
        const ampm = h.hour >= 12 ? 'PM' : 'AM';
        const displayHour = (h.hour % 12 || 12) + ' ' + ampm;
        labels.push(displayHour);
        dataset.push(parseInt(h.tx_count));
      });
    } else {
      labels.push('9 AM', '11 AM', '1 PM', '3 PM', '5 PM');
      dataset.push(0, 0, 0, 0, 0);
    }

    if (peakChartInstance) {
      peakChartInstance.destroy();
    }

    peakChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Customer Checkouts',
          data: dataset,
          backgroundColor: '#D4AF37',
          borderColor: '#B3922E',
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            grid: { color: 'rgba(45, 45, 56, 0.1)' },
            ticks: { stepSize: 1, color: '#555' }
          },
          x: {
            grid: { display: false },
            ticks: { color: '#555' }
          }
        }
      }
    });
  }

  // 2. PRODUCT INVENTORY MANAGEMENT
  function loadInventory() {
    fetch('api.php?action=get_products')
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          products = res.data;
          renderInventoryTable();
        }
      })
      .catch(err => console.error(err));
  }

  function renderInventoryTable() {
    const tbody = document.getElementById('inventory-table-body');
    const searchVal = document.getElementById('inventory-search').value.toLowerCase();
    const catVal = document.getElementById('inventory-category-filter').value;
    
    tbody.innerHTML = '';
    
    // Filter
    let filtered = products.filter(p => {
      const matchSearch = p.name.toLowerCase().includes(searchVal) || p.sku.toLowerCase().includes(searchVal);
      const matchCat = catVal === 'ALL' || p.category === catVal;
      return matchSearch && matchCat;
    });

    // Update Pills
    document.getElementById('inv-unique-count').textContent = products.length;
    
    const lowStock = products.filter(p => p.stock <= p.threshold && p.stock > 0).length;
    document.getElementById('inv-low-stock-count').textContent = lowStock;
    document.getElementById('inv-low-stock-count').className = lowStock > 0 ? 'pill-value text-red font-bold animate-pulse' : 'pill-value';
    
    const oos = products.filter(p => p.stock === 0).length;
    document.getElementById('inv-oos-count').textContent = oos;
    document.getElementById('inv-oos-count').className = oos > 0 ? 'pill-value text-dark-red font-bold' : 'pill-value';

    if (filtered.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center empty-table-placeholder">No matching products found in the shop inventory catalog.</td></tr>';
      return;
    }

    filtered.forEach(p => {
      const tr = document.createElement('tr');
      
      // Stock Status badges
      let stockBadgeClass = 'badge green';
      let stockBadgeText = `${p.stock} units`;
      if (p.stock === 0) {
        stockBadgeClass = 'badge red';
        stockBadgeText = 'OUT OF STOCK';
      } else if (p.stock <= p.threshold) {
        stockBadgeClass = 'badge gold';
        stockBadgeText = `LOW STOCK (${p.stock})`;
      }

      tr.innerHTML = `
        <td>
          <div class="table-product-details">
            <div class="table-prod-img-wrapper">
              <img src="${p.image_path}" alt="${escapeHTML(p.name)}" class="table-prod-img" onerror="this.src='logo.svg';">
            </div>
            <div class="table-prod-info">
              <span class="prod-name-bold">${escapeHTML(p.name)}</span>
              <span class="prod-id-sub">Ref ID: ${p.id}</span>
            </div>
          </div>
        </td>
        <td><code>${p.sku}</code></td>
        <td><span class="category-tag">${p.category}</span></td>
        <td class="text-right font-medium">${formatPeso(p.cost)}</td>
        <td class="text-right font-semibold text-red">${formatPeso(p.price)}</td>
        <td class="text-center"><span class="${stockBadgeClass}">${stockBadgeText}</span></td>
        <td class="text-center font-medium">${p.threshold} units</td>
        <td class="text-center">
          <div class="table-action-btns">
            <button class="btn-action-edit btn-edit-product" data-id="${p.id}"><i class="fa-solid fa-pen-to-square"></i></button>
            <button class="btn-action-delete btn-delete-product" data-id="${p.id}"><i class="fa-solid fa-trash-can"></i></button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    // Bind item edits/deletes
    document.querySelectorAll('.btn-edit-product').forEach(btn => {
      btn.addEventListener('click', () => {
        const prod = products.find(p => p.id === btn.getAttribute('data-id'));
        if (prod) openProductModal(prod);
      });
    });

    document.querySelectorAll('.btn-delete-product').forEach(btn => {
      btn.addEventListener('click', () => {
        const prod = products.find(p => p.id === btn.getAttribute('data-id'));
        if (prod) deleteProduct(prod);
      });
    });
  }

  // Bind inventory search & filters
  document.getElementById('inventory-search').addEventListener('input', renderInventoryTable);
  document.getElementById('inventory-category-filter').addEventListener('change', renderInventoryTable);

  // 3. EMPLOYEE & SHIFTS MANAGEMENT
  function loadEmployeesAndShifts() {
    // Fetch Cashiers
    fetch('api.php?action=get_cashiers')
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          cashiers = res.data;
          renderCashiersTable();
        }
      })
      .catch(err => console.error(err));

    // Fetch Shifts
    fetch('api.php?action=get_all_shifts')
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          shifts = res.data;
          renderShiftsTable();
        }
      })
      .catch(err => console.error(err));
  }

  function renderCashiersTable() {
    const tbody = document.getElementById('cashier-table-body');
    tbody.innerHTML = '';

    if (cashiers.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center empty-table-placeholder">No cashier accounts registered.</td></tr>';
      return;
    }

    cashiers.forEach(c => {
      const tr = document.createElement('tr');
      const statusBadge = c.status === 'active' ? '<span class="badge green">Active</span>' : '<span class="badge red">Inactive</span>';
      
      tr.innerHTML = `
        <td>
          <div class="user-details-cell">
            <div class="user-cell-avatar"><i class="fa-solid fa-user-gear"></i></div>
            <span class="user-cell-name">${escapeHTML(c.name)}</span>
          </div>
        </td>
        <td><code>${escapeHTML(c.username)}</code></td>
        <td>${statusBadge}</td>
        <td class="text-center">
          <div class="table-action-btns">
            <button class="btn-action-edit btn-edit-cashier" data-id="${c.id}"><i class="fa-solid fa-user-pen"></i></button>
            <button class="btn-action-delete btn-delete-cashier" data-id="${c.id}"><i class="fa-solid fa-user-minus"></i></button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    document.querySelectorAll('.btn-edit-cashier').forEach(btn => {
      btn.addEventListener('click', () => {
        const cashier = cashiers.find(c => c.id === btn.getAttribute('data-id'));
        if (cashier) openCashierModal(cashier);
      });
    });

    document.querySelectorAll('.btn-delete-cashier').forEach(btn => {
      btn.addEventListener('click', () => {
        const cashier = cashiers.find(c => c.id === btn.getAttribute('data-id'));
        if (cashier) deleteCashier(cashier);
      });
    });
  }

  function renderShiftsTable() {
    const tbody = document.getElementById('shifts-table-body');
    tbody.innerHTML = '';

    if (shifts.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center empty-table-placeholder">No shift accountability records logged.</td></tr>';
      return;
    }

    shifts.forEach(s => {
      const tr = document.createElement('tr');
      const closeTimeText = s.close_time ? formatDateTime(s.close_time) : '<em>Active In-Progress</em>';
      const statusBadge = s.status === 'open' ? '<span class="badge green animate-pulse">ACTIVE SHIFT</span>' : '<span class="badge gray">CLOSED DRAWER</span>';
      
      let expectedText = '₱0.00';
      let countedText = '₱0.00';
      let discrepancyText = '₱0.00';
      let discrepancyClass = 'font-semibold text-center';

      if (s.status === 'closed') {
        const expected = parseFloat(s.opening_cash) + parseFloat(s.sales_total);
        const counted = parseFloat(s.actual_cash);
        const diff = counted - expected;
        
        expectedText = formatPeso(expected);
        countedText = formatPeso(counted);
        discrepancyText = formatPeso(diff);

        if (diff < -1.00) {
          discrepancyClass = 'font-bold text-red text-center';
        } else if (diff > 1.00) {
          discrepancyClass = 'font-bold text-gold text-center';
        } else {
          discrepancyClass = 'font-semibold text-green text-center';
        }
      } else {
        expectedText = '<em>Recalculating...</em>';
        countedText = '<em>Pending close</em>';
        discrepancyText = '—';
      }

      tr.innerHTML = `
        <td>
          <div class="user-details-cell">
            <div class="user-cell-avatar" style="background: var(--bg-border);"><i class="fa-solid fa-cash-register"></i></div>
            <span class="user-cell-name">${escapeHTML(s.cashier_name)}</span>
          </div>
        </td>
        <td><small>${formatDateTime(s.open_time)}</small></td>
        <td><small>${closeTimeText}</small></td>
        <td class="text-right font-medium">${formatPeso(s.opening_cash)}</td>
        <td class="text-right font-medium">${expectedText}</td>
        <td class="text-right font-semibold">${countedText}</td>
        <td class="${discrepancyClass}">${discrepancyText}</td>
        <td class="text-center">${statusBadge}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  // 4. FINANCIALS & EXPENSES
  function loadFinancialsLedger() {
    // Fetch Expenses
    fetch('api.php?action=get_expenses')
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          expenses = res.data;
          renderExpensesTable();
        }
      })
      .catch(err => console.error(err));

    // Fetch Completed Sales Logs for Voiding and Totals calculation
    fetch('api.php?action=get_transactions')
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          transactions = res.data;
          renderFinancialTotalsAndTransactions();
        }
      })
      .catch(err => console.error(err));
  }

  function renderExpensesTable() {
    const tbody = document.getElementById('expenses-table-body');
    tbody.innerHTML = '';

    let totalExp = 0.00;
    expenses.forEach(ex => {
      totalExp += parseFloat(ex.amount);
    });
    document.getElementById('fin-total-expenses').textContent = formatPeso(totalExp);

    if (expenses.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center empty-table-placeholder">No outlays or shop expenses recorded in ledger.</td></tr>';
      return;
    }

    expenses.forEach(ex => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><small>${formatDateTime(ex.timestamp)}</small></td>
        <td><span class="font-medium">${escapeHTML(ex.description)}</span></td>
        <td><span class="category-tag" style="background: rgba(139,0,0,0.06); border: 1px solid rgba(139,0,0,0.15); color: var(--primary-red);">${ex.category}</span></td>
        <td class="text-right font-bold text-red">${formatPeso(ex.amount)}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  function renderFinancialTotalsAndTransactions() {
    let grossSubtotal = 0.00;
    let discountsTotal = 0.00;
    let taxTotal = 0.00;
    let grandTotal = 0.00;
    let voidsTotal = 0.00;

    const tbody = document.getElementById('sales-history-table-body');
    tbody.innerHTML = '';

    transactions.forEach(t => {
      if (t.status === 'completed') {
        grossSubtotal += parseFloat(t.subtotal);
        discountsTotal += parseFloat(t.discount_amount);
        taxTotal += parseFloat(t.tax);
        grandTotal += parseFloat(t.grand_total);
      } else {
        voidsTotal += parseFloat(t.grand_total);
      }
    });

    const taxableGross = grossSubtotal - discountsTotal;

    // Bind ledger displays
    document.getElementById('fin-gross-subtotal').textContent = formatPeso(grossSubtotal);
    document.getElementById('fin-gross-discount').textContent = '-' + formatPeso(discountsTotal);
    document.getElementById('fin-taxable-gross').textContent = formatPeso(taxableGross);
    document.getElementById('fin-vat-tax').textContent = formatPeso(taxTotal);
    document.getElementById('fin-net-intake').textContent = formatPeso(grandTotal);
    document.getElementById('fin-voids-total').textContent = formatPeso(voidsTotal);

    if (transactions.length === 0) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center empty-table-placeholder">No receipt sales invoices logged in database.</td></tr>';
      return;
    }

    transactions.forEach(t => {
      const tr = document.createElement('tr');
      const statusBadge = t.status === 'completed' 
        ? '<span class="badge green">Paid Completed</span>' 
        : '<span class="badge red strike">Voided Transaction</span>';
      
      const voidBtn = t.status === 'completed'
        ? `<button class="btn-action-delete btn-void-sale" data-id="${t.id}" title="Void Order & Return Stock"><i class="fa-solid fa-ban"></i> Void</button>`
        : `<span class="text-muted font-bold"><i class="fa-solid fa-lock"></i> Locked</span>`;

      const emailText = t.buyer_email ? `<code>${escapeHTML(t.buyer_email)}</code>` : '<span class="text-muted">No Receipt Sent</span>';

      tr.innerHTML = `
        <td><strong class="text-gold">${t.id}</strong></td>
        <td><small>${formatDateTime(t.timestamp)}</small></td>
        <td><span class="font-medium">${escapeHTML(t.cashier_name)}</span></td>
        <td><span class="category-tag">${t.customer_type}</span></td>
        <td>${emailText}</td>
        <td class="text-right font-medium text-green">${t.discount_percent > 0 ? `-${t.discount_percent}%` : '0%'}</td>
        <td class="text-right font-medium">${formatPeso(t.tax)}</td>
        <td class="text-right font-bold text-red">${formatPeso(t.grand_total)}</td>
        <td class="text-center">${statusBadge}</td>
        <td class="text-center">${voidBtn}</td>
      `;
      tbody.appendChild(tr);
    });

    document.querySelectorAll('.btn-void-sale').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        voidTransaction(id);
      });
    });
  }

  function voidTransaction(id) {
    if (confirm(`CRITICAL OVERRIDE:\nAre you sure you want to VOID invoice ${id}?\n\nThis will completely cancel the sales ledger balance and restock all item quantities in the catalog database!`)) {
      fetch(`api.php?action=void_transaction&id=${id}`)
        .then(res => res.json())
        .then(res => {
          if (res.status === 'success') {
            alert(`SUCCESS: Transaction ${id} voided and catalog items replenished.`);
            loadFinancialsLedger();
          } else {
            alert(`ERROR: ${res.message}`);
          }
        })
        .catch(err => console.error(err));
    }
  }

  // 5. SECURITY AUDIT TRAIL LOGS
  function loadAuditTrails() {
    fetch('api.php?action=get_audit_trail')
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          renderAuditTable(res.data);
        }
      })
      .catch(err => console.error(err));
  }

  function renderAuditTable(logs) {
    const tbody = document.getElementById('audit-table-body');
    tbody.innerHTML = '';

    if (logs.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center empty-table-placeholder">Security operations audit log is currently empty.</td></tr>';
      return;
    }

    logs.forEach(log => {
      const tr = document.createElement('tr');
      
      // Give action columns aesthetic badges
      let actionClass = 'audit-action';
      if (log.action.includes('void') || log.action.includes('delete') || log.action.includes('purge') || log.action.includes('suspicious')) {
        actionClass += ' text-red font-bold';
      } else if (log.action.includes('create') || log.action.includes('add')) {
        actionClass += ' text-gold font-bold';
      } else if (log.action.includes('login') || log.action.includes('checkout')) {
        actionClass += ' text-green';
      }

      tr.innerHTML = `
        <td><small class="font-medium">${formatDateTime(log.timestamp)}</small></td>
        <td><code class="audit-user">${escapeHTML(log.username)}</code></td>
        <td><span class="${actionClass}">${log.action.toUpperCase()}</span></td>
        <td><span class="audit-details-text">${escapeHTML(log.details)}</span></td>
      `;
      tbody.appendChild(tr);
    });
  }

  // --- CRUD MODALS LOGIC ---
  function setupModalControls() {
    // Dynamic Close buttons
    document.querySelectorAll('[data-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        const modalId = btn.getAttribute('data-close');
        document.getElementById(modalId).classList.remove('open');
      });
    });

    // Close on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('open');
      });
    });

    // Products Forms
    document.getElementById('btn-add-product').addEventListener('click', () => openProductModal());
    document.getElementById('product-form').addEventListener('submit', handleProductSubmit);

    // Cashiers Forms
    document.getElementById('btn-add-cashier').addEventListener('click', () => openCashierModal());
    document.getElementById('cashier-form').addEventListener('submit', handleCashierSubmit);

    // Expense Forms
    document.getElementById('btn-add-expense').addEventListener('click', () => {
      document.getElementById('expense-form').reset();
      document.getElementById('expense-modal').classList.add('open');
    });
    document.getElementById('expense-form').addEventListener('submit', handleExpenseSubmit);

    // Purge historical records action
    document.getElementById('purge-data-btn').addEventListener('click', handleDatabasePurge);
  }

  // PRODUCTS SUBMIT CRUD
  function openProductModal(prod = null) {
    const modal = document.getElementById('product-modal');
    const title = document.getElementById('product-modal-title');
    const form = document.getElementById('product-form');

    form.reset();

    if (prod) {
      title.textContent = 'Edit Product Catalog Details';
      document.getElementById('prod-id').value = prod.id;
      document.getElementById('prod-name').value = prod.name;
      document.getElementById('prod-sku').value = prod.sku;
      document.getElementById('prod-category').value = prod.category;
      document.getElementById('prod-stock').value = prod.stock;
      document.getElementById('prod-cost').value = prod.cost;
      document.getElementById('prod-price').value = prod.price;
      document.getElementById('prod-threshold').value = prod.threshold;
    } else {
      title.textContent = 'Add New Product to Catalog';
      document.getElementById('prod-id').value = '';
    }

    modal.classList.add('open');
  }

  function handleProductSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('prod-id').value;
    const isEdit = id !== '';

    const payload = {
      id: id,
      name: document.getElementById('prod-name').value,
      sku: document.getElementById('prod-sku').value,
      category: document.getElementById('prod-category').value,
      stock: parseInt(document.getElementById('prod-stock').value),
      cost: parseFloat(document.getElementById('prod-cost').value),
      price: parseFloat(document.getElementById('prod-price').value),
      threshold: parseInt(document.getElementById('prod-threshold').value)
    };

    const action = isEdit ? 'edit_product' : 'add_product';

    fetch(`api.php?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          alert(res.message);
          document.getElementById('product-modal').classList.remove('open');
          loadInventory();
        } else {
          alert('PRODUCT ERROR: ' + res.message);
        }
      })
      .catch(err => console.error(err));
  }

  function deleteProduct(prod) {
    if (confirm(`CRITICAL WARNING:\nAre you sure you want to DELETE product '${prod.name}'?\nSKU ID: ${prod.sku}\n\nThis will permanently delete this product catalog listing!`)) {
      fetch(`api.php?action=delete_product&id=${prod.id}`)
        .then(res => res.json())
        .then(res => {
          if (res.status === 'success') {
            alert(res.message);
            loadInventory();
          } else {
            alert('DELETE ERROR: ' + res.message);
          }
        })
        .catch(err => console.error(err));
    }
  }

  // CASHIERS SUBMIT CRUD
  function openCashierModal(c = null) {
    const modal = document.getElementById('cashier-modal');
    const title = document.getElementById('cashier-modal-title');
    const form = document.getElementById('cashier-form');

    form.reset();

    const passField = document.getElementById('cashier-password');
    const passLabel = document.getElementById('label-cashier-pass');
    const statusContainer = document.getElementById('cashier-status-container');

    if (c) {
      title.textContent = 'Modify Cashier Account';
      document.getElementById('cashier-id').value = c.id;
      document.getElementById('cashier-name').value = c.name;
      document.getElementById('cashier-username').value = c.username;
      
      // Password optional in edit mode
      passLabel.textContent = 'New Access Password (Leave blank to keep current)';
      passField.removeAttribute('required');
      
      statusContainer.style.display = 'block';
      document.getElementById('cashier-status').value = c.status;
    } else {
      title.textContent = 'Register Cashier Operator';
      document.getElementById('cashier-id').value = '';
      
      passLabel.textContent = 'Access Password *';
      passField.setAttribute('required', 'required');
      
      statusContainer.style.display = 'none';
    }

    modal.classList.add('open');
  }

  function handleCashierSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('cashier-id').value;
    const isEdit = id !== '';

    const payload = {
      id: id,
      name: document.getElementById('cashier-name').value,
      username: document.getElementById('cashier-username').value,
      password: document.getElementById('cashier-password').value,
      status: isEdit ? document.getElementById('cashier-status').value : 'active'
    };

    const action = isEdit ? 'edit_cashier' : 'add_cashier';

    fetch(`api.php?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          alert(res.message);
          document.getElementById('cashier-modal').classList.remove('open');
          loadEmployeesAndShifts();
        } else {
          alert('CASHIER ERROR: ' + res.message);
        }
      })
      .catch(err => console.error(err));
  }

  function deleteCashier(c) {
    if (confirm(`Are you sure you want to completely REMOVE employee cashier account for '${c.name}'?\nUsername: ${c.username}`)) {
      fetch(`api.php?action=delete_cashier&id=${c.id}`)
        .then(res => res.json())
        .then(res => {
          if (res.status === 'success') {
            alert(res.message);
            loadEmployeesAndShifts();
          } else {
            alert('ERROR: ' + res.message);
          }
        })
        .catch(err => console.error(err));
    }
  }

  // EXPENSES SUBMIT
  function handleExpenseSubmit(e) {
    e.preventDefault();
    const payload = {
      description: document.getElementById('exp-desc').value,
      category: document.getElementById('exp-category').value,
      amount: parseFloat(document.getElementById('exp-amount').value)
    };

    fetch('api.php?action=add_expense', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => res.json())
      .then(res => {
        if (res.status === 'success') {
          alert(res.message);
          document.getElementById('expense-modal').classList.remove('open');
          loadFinancialsLedger();
        } else {
          alert('EXPENSE ERROR: ' + res.message);
        }
      })
      .catch(err => console.error(err));
  }

  // DATA SYSTEM RESET / PURGE
  function handleDatabasePurge() {
    if (confirm("🚨 CRITICAL STORE OPERATIONS WARNING! 🚨\n\nAre you absolutely sure you want to PURGE ALL TRANSACTIONAL DATA AND HISTORY?\n\nThis action will:\n1. Delete ALL completed transaction records & invoices.\n2. Delete ALL items detail records.\n3. Clear ALL employee shifts and attendance histories.\n4. Delete ALL logged shop expenses.\n5. Reset ALL product inventories back to default university counts.\n\nTHIS CANNOT BE UNDONE! Do you wish to proceed?")) {
      if (confirm("SECOND AND FINAL SECURITY CONFIRMATION:\nVerify that you are authorized. Type 'CONFIRM' inside the next popup box if you wish to reset local databases.")) {
        const text = prompt("Type 'PURGE_LANGKIT_SYSTEM' in the field below to finalize:");
        if (text === 'PURGE_LANGKIT_SYSTEM') {
          fetch('api.php?action=purge_history')
            .then(res => res.json())
            .then(res => {
              if (res.status === 'success') {
                alert("SUCCESS: The system database has been purged and seed products reset to default values. Reloading panel.");
                location.reload();
              } else {
                alert("PURGE SYSTEM ERROR: " + res.message);
              }
            })
            .catch(err => console.error(err));
        } else {
          alert("ABORTED: Match text failed. System safety shields preserved historical databases.");
        }
      }
    }
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
    // Handle SQL format string
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
