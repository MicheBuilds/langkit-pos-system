# Langkit University Shop - Point of Sale (POS) & Store Management System

> **Course:** CCC151 - Information Management  
> **Institution:** Mindanao State University – Iligan Institute of Technology (MSU-IIT)  
> **Student / Developer:** MicheBuilds (<mikelouie300@gmail.com>)

---

## 📌 Project Overview

**Langkit University Shop POS & Store Management System** is a full-featured, 3-tier web application designed specifically for the official university merchandise store of MSU-IIT.

The system addresses the operational challenges of campus retail by delivering an automated, BIR-compliant sales terminal coupled with a real-time management dashboard. It bridges daily cashier operations, drawer cash accountability, stock inventory, operational expense tracking, and management reporting into an integrated, secure platform.

---

## 🚀 Key Features

### 🛒 1. Point of Sale (POS) Terminal (`cashier.php`)
* **Dynamic Catalog & Barcode Scanning:** Fast search by product name, SKU barcode, or category filtering (Apparel, Accessories, Drinkware).
* **Automated Billing Engine:** Real-time subtotal, discount, 12% VAT, and grand total calculations.
* **Legally Compliant Discount Modules:**
  * Student Promo (5%)
  * Faculty Discount (10%)
  * Loyalty Reward (12%)
  * Senior Citizen / PWD (20%)
  *(VAT is accurately computed on the net taxable amount after discount).*
* **Cash Tendered & Change Computation:** Quick-cash tender buttons (₱100, ₱200, ₱500, ₱1,000) with automatic change-due computation.
* **Thermal Paper Receipt Simulator:** Generates realistic printable physical receipt views and simulated SMTP HTML e-receipt logs.

### 💼 2. Shift & Cashier Drawer Accountability (`shifts`)
* **Mandatory Shift In:** Cashiers must register and declare their opening drawer seed petty cash before performing transactions.
* **Cash Variance Detection:** Compares expected drawer balance (`Opening Cash + Accumulated Cash Sales`) against actual counted physical cash upon shift close.
* **Audit Flagging:** Discrepancies exceeding ±₱1.00 trigger automated suspicious activity warnings in the audit log.

### 📊 3. Executive Store Owner Dashboard (`owner.php`)
* **Key Performance Indicators (KPIs):** Real-time cards displaying Today's Sales, Weekly Sales, Estimated Net Profit, and Inventory Retail/Cost Basis.
* **Visual Sales Analytics (Chart.js):**
  * 7-day revenue trend line chart with smoothed bezier curves.
  * Peak sales hours distribution bar chart.
* **Performance Lists:** Top 5 Best-Selling Products, Slow-Moving Stock Alerts, and Cashier Revenue Rankings.
* **Inventory Catalog CRUD:** Add, update, monitor low-stock thresholds, and delete catalog items.
* **Transaction Void Manager:** Authorized transaction cancellations with automatic stock replenishment.
* **Store Expense Ledger:** Track operating cash outlays by category (Supplier Payables, Store Operations, Utilities, Marketing, Staff Petty Cash).
* **System Audit Trail:** Real-time forensic logging of logins, edits, checkouts, and historical voids.

---

## 🏗️ System Architecture & Technology Stack

The project adheres to a clean **3-Tier Architecture**:

```
+--------------------------------------------------------------+
|                   1. Presentation Layer                      |
|  - HTML5, Modern CSS3 (Glassmorphism, CSS Grid, Flexbox)     |
|  - Vanilla JavaScript (Async/Await Fetch API, Chart.js)       |
+--------------------------------------------------------------+
                              │
                              ▼
+--------------------------------------------------------------+
|                  2. Application / Logic Layer                |
|  - PHP 8.x Unified JSON REST API (api.php)                   |
|  - Role-Based Access Control (RBAC: Owner vs. Cashier)       |
|  - ACID-Compliant Transaction Blocks                         |
|  - Bcrypt Password Hashing (`password_hash` / verify)        |
+--------------------------------------------------------------+
                              │
                              ▼
+--------------------------------------------------------------+
|                   3. Data Persistence Layer                  |
|  - MySQL Database Engine (langkit_shop)                      |
|  - PDO (PHP Data Objects) with Prepared Parameterized Queries |
|  - Self-Healing Auto-Migration & Seeding (db.php)            |
+--------------------------------------------------------------+
```

---

## 🗄️ Database Design (Relational Schema)

The database `langkit_shop` consists of 7 normalized relational tables:

1. **`users`** - Stores authenticated user credentials, roles (`owner`, `cashier`), and account status.
2. **`products`** - Product details, SKU barcodes, categories, retail prices, purchase costs, inventory quantities, and low-stock threshold levels.
3. **`transactions`** - Transaction headers recording invoice IDs, timestamps, cashier references, customer types, discount amounts, VAT tax, and grand totals.
4. **`transaction_items`** - Junction table mapping purchased items, quantities, and prices to individual transactions (with `ON DELETE CASCADE`).
5. **`shifts`** - Shift accountability logs containing opening times, closing times, seeded cash, actual counted cash, and variance.
6. **`expenses`** - Operating store expenditure logs categorized by business operational purpose.
7. **`audit_trail`** - Immutable chronological log of administrative and operational activities for security and transparency.

---

## 📐 Mathematical Formulas & Financial Models

1. **Subtotal ($S$):**
   $$S = \sum (Price_i \times Qty_i)$$

2. **Discount ($D$):**
   $$D = S \times \left(\frac{\text{Discount \%}}{100}\right)$$

3. **Taxable Base ($N$):**
   $$N = S - D$$

4. **12% VAT ($T$):**
   $$T = N \times 0.12$$

5. **Grand Total ($G$):**
   $$G = N + T = N \times 1.12$$

6. **Shift Expected Balance ($E$):**
   $$E = \text{Opening Cash} + \text{Total Cash Sales}$$

7. **Shift Cash Discrepancy ($\Delta$):**
   $$\Delta = \text{Actual Counted Cash} - E$$

8. **Estimated Net Profit ($P$):**
   $$P = \text{Gross Revenue} - \text{Cost of Goods Sold (COGS)} - \text{Total Expenses}$$

---

## 💻 Installation & Setup Guide

### Prerequisites
* **XAMPP** (or any LAMP/WAMP stack with PHP 7.4+ and MySQL 5.7+ / MariaDB 10.4+)
* Web Browser (Chrome, Firefox, Edge)

### Setup Steps
1. Clone or download this repository:
   ```bash
   git clone https://github.com/MicheBuilds/langkit-pos-system.git
   ```
2. Move the project folder to your local server root:
   * **XAMPP Windows:** `C:\xampp\htdocs\langkit`
3. Start the **Apache** and **MySQL** services in the XAMPP Control Panel.
4. Launch your web browser and navigate to:
   ```
   http://localhost/langkit/login.php
   ```
5. *Automatic Setup:* The built-in `db.php` script will automatically create the database `langkit_shop`, build all 7 tables, and seed the initial catalog and user accounts!
   *(Optional: If you prefer manual setup, you can import `database.sql` via phpMyAdmin).*

---

## 🔐 Default Access Credentials

| Role | Username | Password | Access Level |
| :--- | :--- | :--- | :--- |
| **Store Owner** | `owner` | `owner123` | Full Administrative & Financial Access |
| **Shift Cashier** | `cashier` | `cashier123` | Sales POS & Drawer Shift Terminal |

---

## 🛡️ Security Features
* **SQL Injection Protection:** Exclusively uses PHP Data Objects (PDO) with parameterized prepared statements.
* **Password Encryption:** Passwords are encrypted using PHP's native `password_hash()` implementing the modern Bcrypt algorithm.
* **Race Condition Prevention:** Stock check and deduction operations run inside an ACID transaction using MySQL `FOR UPDATE` row-level locking.
* **Session Guard Authentication:** Server-side role checks (`checkAuth()`) on all API endpoints prevent unauthorized access.

---

## 📄 License & Academic Attribution
Developed as an academic project for **CCC151 (Information Management)** at the **Mindanao State University – Iligan Institute of Technology**. All rights reserved.
