# Samrajya Mens Wear — Store Management & Billing Software

> **The Royal Destination for Men's Fashion**  
> Complete, functional, responsive retail clothing store management and billing software built with **PHP**, **Firebase Cloud Firestore**, and **Dompdf**.

---

## 1. System Overview

**Samrajya Mens Wear** is a full-stack, enterprise-grade garment inventory and Point-of-Sale (POS) management system designed for high-traffic menswear retail showrooms. It supports:
- **Instant POS Billing** with barcode/SKU search, live stock limits, and keyboard shortcuts (`F2` search, `F9` checkout).
- **Dual Invoicing Modes**:
  - **GST Tax Invoice** (Original for Recipient, Rule 46 CGST rules) with intra-state CGST & SGST or inter-state IGST breakdowns and HSN codes.
  - **Non-GST Sales Bill** (Retail cash memo) without tax representation.
- **Dompdf PDF Invoices**: High-definition, downloadable, and print-ready retail receipts with complete legal terms and shop branding.
- **Variant & Inventory Management**: Handles clothing variants (Sizes: S, M, L, XL, XXL, XXXL, 28–44; Colors; Brands) with independent SKUs and stock tracking.
- **Complete Returns & Exchanges**: Safe returns referencing original bills with return quantity eligibility checks and physical inventory restock ledger.
- **Sales Analytics & Gross Profit**: Accurate profit analytics calculated from **historical purchase-cost snapshots** stored at the time of sale.
- **Firebase Cloud Firestore** + **Resilient Offline Fallback**: Direct Cloud Firestore REST API v1 communication with zero heavy C-extensions, paired with a local ACID document store for offline resilience.
- **Server-Side Authorization**: Complete ID-token verification and admin whitelist enforcement on every protected endpoint.

---

## 2. Technical Stack

- **Backend**: PHP 8.1+ (Procedural + Object-Oriented with Composer autoloader).
- **Database**: Google Cloud Firestore (REST API v1) via pure PHP OAuth2 Service Account JWT + local resilient store.
- **Authentication**: Firebase Authentication (Email & Password) + server-side token verification and session management.
- **PDF Engine**: `dompdf/dompdf` ^3.1 with custom typography and Indian currency in words.
- **Security Rules**: Production-hardened `firestore.rules` (Version 2) with RBAC (Superadmin, Admin, Cashier) and zero-deletion constraints.
- **Frontend**: Custom responsive dashboard in Deep Forest Green (`#0f392b`), Crisp White, and Warm Neutral tones.

---

## 3. Project Directory Structure

```
samrajya/
├── index.php                 # Application entrypoint & session router
├── login.php                 # Admin login portal with Firebase Auth
├── dashboard.php             # Live analytics, sales trend, low stock alerts
├── billing.php               # POS terminal with real-time stock & cart
├── products.php              # Product catalog, variant & inventory CRUD
├── invoices.php              # Historical invoice archives & search
├── invoice-print.php         # Print-optimized invoice layout
├── customers.php             # Customer loyalty, directory & past purchase history
├── reports.php               # Date-range sales, profit, GST breakdown, CSV export
├── returns.php               # Return/exchange workflow & stock restock
├── settings.php              # Store profile, GSTIN, invoice prefixes, Firebase sync
├── firestore.rules           # Production-grade Firestore Security Rules (RBAC)
├── firebase.json             # Firebase CLI configuration
├── composer.json             # Composer dependencies (dompdf, php-jwt)
├── .gitignore                # Source control exclusions
├── config/
│   ├── config.example.php    # Clean template configuration
│   ├── config.php            # Active environment & credentials configuration
│   └── firebase-service-account.json # (Optional) Firebase private key
├── includes/
│   ├── bootstrap.php         # System bootstrapper, error handling, seed engine
│   ├── helpers.php           # Currency formatting, numbers to words, sanitization
│   ├── db.php                # DatabaseManager with Cloud Firestore & local driver
│   ├── firestore.php         # Pure PHP Firestore REST API v1 Client
│   ├── auth.php              # Session auth checks, token verification
│   ├── pdf-generator.php     # Dompdf retail invoice generator
│   ├── header.php            # Global responsive header & brand topbar
│   ├── sidebar.php           # Navigation sidebar with live DB status badge
│   └── footer.php            # Global footer, toast container, modals
├── api/
│   ├── auth.php              # Login, logout, session status
│   ├── products.php          # Product CRUD, search, stock adjustments
│   ├── billing.php           # Atomic checkout, stock deduction, profit snapshots
│   ├── invoices.php          # Invoice querying, detail inspection, PDF stream
│   ├── customers.php         # Customer lookups and profile editing
│   ├── returns.php           # Return lookup, eligibility validation, return execution
│   ├── reports.php           # Sales summaries, daily trends, CSV download
│   └── settings.php          # Store settings, connection testing, cloud sync
├── assets/
│   ├── css/
│   │   ├── style.css         # Theme styles: forest green, cards, tables, modals
│   │   └── pos.css           # POS billing split-screen layout
│   └── js/
│       ├── app.js            # Global UI, toast notifications, API wrapper
│       └── pos.js            # POS keyboard events, cart math, client validations
└── storage/
    ├── data/                 # Local database storage & token cache
    ├── invoices/             # Cached generated invoice documents
    └── logs/                 # Error logs and application telemetry
```

---

## 4. Setup & Installation on Windows / XAMPP

### Step 1: Clone or Copy Project Files
Ensure the project is placed in your desired directory (e.g. `F:\samrajya` or `C:\xampp\htdocs\samrajya`).

### Step 2: Configure Apache (XAMPP)
If serving through XAMPP Apache, ensure `E:\xampp\apache\conf\httpd.conf` has an alias or DocumentRoot pointing to `F:\samrajya`:
```apache
Alias /samrajya "F:/samrajya"
<Directory "F:/samrajya">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```
Or simply run PHP's built-in web server:
```powershell
& "E:\xampp\php\php.exe" -S localhost:8000 -t "F:\samrajya"
```

### Step 3: Install Composer Dependencies
The composer dependencies (`dompdf/dompdf`, `firebase/php-jwt`) are already installed in `vendor/`. To reinstall or update:
```powershell
& "E:\xampp\php\php.exe" "E:\xampp\php\composer.phar" install
```

### Step 4: Initial Login Credentials
The system automatically seeds an initial administrator and sample men's wear catalog on first run:
- **URL**: `http://localhost:8000/login.php` (or `http://localhost/samrajya/login.php`)
- **Admin Email**: `admin@samrajyamenswear.com`
- **Password**: `Samrajya@123`

---

## 5. Connecting Google Cloud Firestore

1. Open the [Firebase Console](https://console.firebase.google.com).
2. Create or select your Firebase project: `samrajya-mens-wear`.
3. Enable **Cloud Firestore** in Production mode.
4. Go to **Project Settings &rarr; Service Accounts**.
5. Click **Generate New Private Key** to download your JSON credentials.
6. Rename or copy this JSON file to:
   ```
   F:\samrajya\config\firebase-service-account.json
   ```
7. Go to **Settings & Firebase** in the admin dashboard and click **Test Firebase Connection**.
8. Click **Sync Local Data to Cloud** to upload all opening inventory items, customers, and seed records to Cloud Firestore.
9. Deploy the security rules using Firebase CLI:
   ```bash
   firebase deploy --only firestore:rules
   ```

---

## 6. GST & Legal Invoicing Compliance

> **Important**: Before using for real-world legal invoicing, the store owner or accountant must verify the following in the **Settings** page:
1. **GSTIN**: Must match the official 15-digit GST identification number of Samrajya Mens Wear.
2. **State & State Code**: Default is `27` (Maharashtra). Intra-state sales automatically apply equal halves of CGST and SGST (e.g. 2.5% + 2.5% for a 5% garment).
3. **HSN Codes**: Men's shirts and trousers default to HSN `6205` and `6203`.
4. **GST Rates**: In Indian retail apparel:
   - Garments with selling price &le; ₹1,000 typically attract 5% GST.
   - Garments with selling price > ₹1,000 attract 12% GST.
   - Accessories (e.g. leather belts) attract 18% GST.
   Each product variant allows configuring its exact GST rate independently.

---

## 7. Quality & Security Checklist

- [x] **No Hardcoded Figures**: All dashboard metrics, sales figures, and inventory levels are live aggregations.
- [x] **Server-Side Recalculation**: POS browser submissions are never trusted; every price, discount, tax, and total is recalculated independently on the PHP backend.
- [x] **Snapshot Cost Architecture**: Purchase prices are snapshotted in invoice records at the exact moment of sale so later supplier price fluctuations do not corrupt historical profit reports.
- [x] **Atomic Concurrency Counters**: Sequential invoice numbering (`SMW-GST-YYYYMM-XXXX`) prevents race conditions.
- [x] **Zero Partial Deductions**: Stock is validated and deducted only when the invoice transaction succeeds.
- [x] **Non-Destructive Returns**: Original invoices are never deleted; returns generate formal return credit notes with physical stock restoration flags.
- [x] **Print & Downloadable PDFs**: Dompdf generates standalone A4 and thermal-friendly invoices with number-to-words currency formatting.
