/**
 * Samrajya Mens Wear - POS Billing Interactive Engine
 */

const POS = {
    products: [],
    cart: [],
    invoiceType: 'gst', // 'gst' or 'nongst'
    isInterstate: false,
    selectedCategory: 'all',
    searchQuery: '',
    billDiscountType: 'fixed',
    billDiscountValue: 0,
    paymentMethod: 'cash',
    currentCustomer: {
        id: '',
        name: '',
        phone: '',
        email: '',
        address: '',
        gstin: ''
    },
    customerSearchTimer: null,

    init() {
        this.loadProducts();
        this.setupEventListeners();
        this.setupKeyboardShortcuts();
    },

    async loadProducts() {
        try {
            const res = await App.api('api/products.php?action=list&status=active');
            this.products = res.products || [];
            this.renderCategoryPills();
            this.renderProducts();
        } catch (err) {
            console.error('Failed to load products for POS:', err);
        }
    },

    renderCategoryPills() {
        const container = document.getElementById('posCategoryPills');
        if (!container) return;

        const cats = [...new Set(this.products.map(p => (p.category || '').trim()).filter(Boolean))].sort();

        if (this.selectedCategory !== 'all' && !cats.some(c => c.toLowerCase() === this.selectedCategory.toLowerCase())) {
            this.selectedCategory = 'all';
        }

        container.innerHTML = `
            <div class="category-pill ${this.selectedCategory === 'all' ? 'active' : ''}" data-category="all">
                All Items (${this.products.length})
            </div>
            ${cats.map(c => `
                <div class="category-pill ${this.selectedCategory.toLowerCase() === c.toLowerCase() ? 'active' : ''}" data-category="${App.escapeHtml(c)}">
                    ${App.escapeHtml(c)}
                </div>
            `).join('')}
        `;

        container.querySelectorAll('.category-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                container.querySelectorAll('.category-pill').forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                this.selectedCategory = pill.dataset.category || 'all';
                this.renderProducts();
            });
        });
    },

    setupEventListeners() {
        // Search input
        const searchInput = document.getElementById('posSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchQuery = e.target.value.toLowerCase().trim();
                this.renderProducts();
            });
        }

        // Category pills
        document.querySelectorAll('.category-pill').forEach(pill => {
            pill.addEventListener('click', (e) => {
                document.querySelectorAll('.category-pill').forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                this.selectedCategory = pill.dataset.category || 'all';
                this.renderProducts();
            });
        });

        // Invoice type buttons
        const gstBtn = document.getElementById('typeGstBtn');
        const nongstBtn = document.getElementById('typeNongstBtn');
        if (gstBtn && nongstBtn) {
            gstBtn.addEventListener('click', () => {
                this.invoiceType = 'gst';
                gstBtn.classList.add('active');
                nongstBtn.classList.remove('active');
                this.recalculateCart();
            });
            nongstBtn.addEventListener('click', () => {
                this.invoiceType = 'nongst';
                nongstBtn.classList.add('active');
                gstBtn.classList.remove('active');
                this.recalculateCart();
            });
        }

        // Customer phone input
        const phoneInput = document.getElementById('customerPhone');
        if (phoneInput) {
            phoneInput.addEventListener('input', (e) => {
                const phone = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = phone;
                this.currentCustomer.phone = phone;

                if (phone.length === 10) {
                    this.lookupCustomerByPhone(phone);
                } else {
                    const badge = document.getElementById('customerBadge');
                    if (badge && phone.length < 10) badge.style.display = 'none';

                    if (phone.length >= 2) {
                        this.handleCustomerSearch(phone);
                    } else {
                        const dd = document.getElementById('customerSuggestions');
                        if (dd) dd.style.display = 'none';
                    }
                }
            });

            phoneInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const nameInput = document.getElementById('customerName');
                    if (nameInput) nameInput.focus();
                }
            });
        }

        // Customer name input
        const nameInput = document.getElementById('customerName');
        if (nameInput) {
            nameInput.addEventListener('input', (e) => {
                const val = e.target.value;
                this.currentCustomer.name = val;
                if (val.trim().length >= 2) {
                    this.handleCustomerSearch(val.trim());
                } else {
                    const dd = document.getElementById('customerSuggestions');
                    if (dd) dd.style.display = 'none';
                }
            });

            nameInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const search = document.getElementById('posSearchInput');
                    if (search) search.focus();
                }
            });
        }

        // Walk-in Reset button
        const resetBtn = document.getElementById('btnWalkinReset');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetCustomer();
                App.toast('Reset to Walk-in Customer (किरकोळ ग्राहक)', 'info', 1500);
            });
        }

        // Close customer dropdown on outside click
        document.addEventListener('click', (e) => {
            const container = document.querySelector('.customer-inputs-container');
            const dropdown = document.getElementById('customerSuggestions');
            if (dropdown && container && !container.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Payment buttons
        document.querySelectorAll('.pay-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.paymentMethod = btn.dataset.method || 'cash';
            });
        });

        // Bill discount
        const billDiscInput = document.getElementById('billDiscountInput');
        const billDiscTypeSelect = document.getElementById('billDiscountType');
        if (billDiscInput) {
            billDiscInput.addEventListener('input', (e) => {
                this.billDiscountValue = Math.max(0, parseFloat(e.target.value || 0));
                this.recalculateCart();
            });
        }
        if (billDiscTypeSelect) {
            billDiscTypeSelect.addEventListener('change', (e) => {
                this.billDiscountType = e.target.value;
                this.recalculateCart();
            });
        }
    },

    setupKeyboardShortcuts() {
        window.addEventListener('keydown', (e) => {
            // F2: Focus search bar
            if (e.key === 'F2') {
                e.preventDefault();
                const search = document.getElementById('posSearchInput');
                if (search) { search.focus(); search.select(); }
            }
            // F9: Complete checkout
            if (e.key === 'F9') {
                e.preventDefault();
                this.checkout();
            }
            // Esc: Clear search or modal
            if (e.key === 'Escape') {
                const search = document.getElementById('posSearchInput');
                if (search && document.activeElement === search) {
                    search.value = '';
                    this.searchQuery = '';
                    this.renderProducts();
                }
            }
        });
    },

    async lookupCustomerByPhone(phone) {
        const badge = document.getElementById('customerBadge');
        const nameInput = document.getElementById('customerName');
        const dropdown = document.getElementById('customerSuggestions');
        if (dropdown) dropdown.style.display = 'none';

        try {
            const res = await App.api(`api/customers.php?action=search&q=${phone}`);
            const list = res.customers || [];
            const exact = list.find(c => c.phone === phone) || (list.length > 0 ? list[0] : null);

            if (exact) {
                this.currentCustomer = {
                    id: exact.customerId || '',
                    name: exact.name || '',
                    phone: exact.phone || phone,
                    email: exact.email || '',
                    address: exact.address || '',
                    gstin: exact.gstin || ''
                };
                if (nameInput) nameInput.value = exact.name;
                if (badge) {
                    badge.className = 'customer-status-badge existing';
                    badge.innerHTML = `✓ ${App.escapeHtml(exact.name)}`;
                    badge.style.display = 'inline-flex';
                }
                App.toast(`Customer identified: ${exact.name}`, 'info', 2000);
            } else {
                this.currentCustomer.id = '';
                this.currentCustomer.phone = phone;
                if (badge) {
                    badge.className = 'customer-status-badge new';
                    badge.innerHTML = `+ New Customer`;
                    badge.style.display = 'inline-flex';
                }
                if (nameInput && !nameInput.value.trim()) {
                    nameInput.focus();
                }
            }
        } catch (err) {
            console.error('Customer lookup error:', err);
        }
    },

    handleCustomerSearch(query) {
        clearTimeout(this.customerSearchTimer);
        const dropdown = document.getElementById('customerSuggestions');
        if (!dropdown) return;

        const clean = query.trim();
        if (clean.length < 2) {
            dropdown.style.display = 'none';
            return;
        }

        this.customerSearchTimer = setTimeout(async () => {
            try {
                const res = await App.api(`api/customers.php?action=search&q=${encodeURIComponent(clean)}`);
                const list = res.customers || [];
                if (list.length === 0) {
                    dropdown.style.display = 'none';
                    return;
                }

                dropdown.innerHTML = list.slice(0, 6).map(c => `
                    <div class="customer-suggestion-item">
                        <div class="cust-name">${App.escapeHtml(c.name)}</div>
                        <div class="cust-meta">
                            <span>📞 ${c.phone || 'No Phone'}</span>
                            <span>🛍️ ${c.totalInvoices || 0} bills</span>
                            <span>💰 ₹${parseFloat(c.totalPurchases || 0).toLocaleString('en-IN')}</span>
                        </div>
                    </div>
                `).join('');

                dropdown.querySelectorAll('.customer-suggestion-item').forEach((item, idx) => {
                    item.addEventListener('click', () => {
                        this.selectCustomer(list[idx]);
                    });
                });

                dropdown.style.display = 'block';
            } catch (e) {
                dropdown.style.display = 'none';
            }
        }, 220);
    },

    selectCustomer(cust) {
        this.currentCustomer = {
            id: cust.customerId || '',
            name: cust.name || '',
            phone: cust.phone || '',
            email: cust.email || '',
            address: cust.address || '',
            gstin: cust.gstin || ''
        };
        const phoneInput = document.getElementById('customerPhone');
        const nameInput = document.getElementById('customerName');
        const badge = document.getElementById('customerBadge');
        const dropdown = document.getElementById('customerSuggestions');

        if (phoneInput) phoneInput.value = cust.phone || '';
        if (nameInput) nameInput.value = cust.name || '';
        if (dropdown) dropdown.style.display = 'none';

        if (badge) {
            badge.className = 'customer-status-badge existing';
            badge.innerHTML = `✓ ${App.escapeHtml(cust.name)}`;
            badge.style.display = 'inline-flex';
        }
        App.toast(`Customer Selected: ${cust.name}`, 'info', 1500);
    },

    resetCustomer() {
        this.currentCustomer = {
            id: '',
            name: '',
            phone: '',
            email: '',
            address: '',
            gstin: ''
        };
        const phoneInput = document.getElementById('customerPhone');
        const nameInput = document.getElementById('customerName');
        const badge = document.getElementById('customerBadge');
        const dropdown = document.getElementById('customerSuggestions');

        if (phoneInput) phoneInput.value = '';
        if (nameInput) nameInput.value = '';
        if (badge) badge.style.display = 'none';
        if (dropdown) dropdown.style.display = 'none';
    },

    renderProducts() {
        const grid = document.getElementById('posProductsGrid');
        if (!grid) return;

        const filtered = this.products.filter(p => {
            if (this.selectedCategory !== 'all' && (p.category || '').toLowerCase() !== this.selectedCategory.toLowerCase()) {
                return false;
            }
            if (this.searchQuery) {
                const name = (p.name || '').toLowerCase();
                const sku = (p.sku || '').toLowerCase();
                const barcode = (p.barcode || '').toLowerCase();
                const color = (p.color || '').toLowerCase();
                if (!name.includes(this.searchQuery) && 
                    !sku.includes(this.searchQuery) && 
                    !barcode.includes(this.searchQuery) &&
                    !color.includes(this.searchQuery)) {
                    return false;
                }
            }
            return true;
        });

        if (filtered.length === 0) {
            grid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align:center; padding: 40px; color:var(--slate-400);">
                    <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 10px; color:var(--slate-300);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <div style="font-weight:600;">No items found matching filter</div>
                </div>
            `;
            return;
        }

        grid.innerHTML = filtered.map(p => {
            const stock = parseInt(p.stockQuantity || 0);
            const threshold = parseInt(p.lowStockThreshold || 5);
            let stockClass = 'stock-in';
            let stockLabel = `${stock} in stock`;

            if (stock <= 0) {
                stockClass = 'stock-out';
                stockLabel = 'Out of Stock';
            } else if (stock <= threshold) {
                stockClass = 'stock-low';
                stockLabel = `Only ${stock} left`;
            }

            return `
                <div class="pos-item-card" onclick="POS.addToCart('${p.productId}')">
                    <div class="pos-item-header">
                        <span class="pos-item-category">${App.escapeHtml(p.category || '')}</span>
                        <span class="pos-item-stock ${stockClass}">${stockLabel}</span>
                    </div>
                    <div class="pos-item-name">${App.escapeHtml(p.name)}</div>
                    <div class="pos-item-meta">
                        <span class="pos-badge-size">${App.escapeHtml(p.size || '-')}</span>
                        ${p.color ? `<span class="pos-badge-color">${App.escapeHtml(p.color)}</span>` : ''}
                    </div>
                    <div class="pos-item-footer">
                        <div class="pos-item-price">${App.formatINR(p.sellingPrice)}</div>
                        <div class="pos-item-sku">${App.escapeHtml(p.sku)}</div>
                    </div>
                </div>
            `;
        }).join('');
    },

    addToCart(productId) {
        const product = this.products.find(p => p.productId === productId);
        if (!product) return;

        const availableStock = parseInt(product.stockQuantity || 0);
        if (availableStock <= 0) {
            App.toast(`'${product.name}' is out of stock!`, 'warning');
            return;
        }

        const existing = this.cart.find(item => item.productId === productId);
        if (existing) {
            if (existing.quantity >= availableStock) {
                App.toast(`Cannot add more. Max stock available: ${availableStock}`, 'warning');
                return;
            }
            existing.quantity++;
        } else {
            this.cart.push({
                productId: product.productId,
                name: product.name,
                sku: product.sku,
                size: product.size || '-',
                color: product.color || '-',
                category: product.category || '',
                sellingPrice: parseFloat(product.sellingPrice || 0),
                purchasePrice: parseFloat(product.purchasePrice || 0),
                gstRate: parseFloat(product.gstRate || 0),
                hsnCode: product.hsnCode || '',
                quantity: 1,
                discountAmount: 0.00,
                availableStock: availableStock
            });
        }

        this.renderCart();
    },

    updateCartQty(productId, delta) {
        const item = this.cart.find(i => i.productId === productId);
        if (!item) return;

        const newQty = item.quantity + delta;
        if (newQty <= 0) {
            this.removeFromCart(productId);
            return;
        }

        if (newQty > item.availableStock) {
            App.toast(`Maximum available stock reached (${item.availableStock} pcs)`, 'warning');
            return;
        }

        item.quantity = newQty;
        this.renderCart();
    },

    setCartQty(productId, value) {
        const item = this.cart.find(i => i.productId === productId);
        if (!item) return;

        let qty = parseInt(value || 1);
        if (isNaN(qty) || qty <= 0) qty = 1;
        if (qty > item.availableStock) {
            qty = item.availableStock;
            App.toast(`Capped to available stock (${item.availableStock} pcs)`, 'warning');
        }

        item.quantity = qty;
        this.renderCart();
    },

    setCartItemDiscount(productId, discount) {
        const item = this.cart.find(i => i.productId === productId);
        if (!item) return;

        let disc = Math.max(0, parseFloat(discount || 0));
        if (disc > item.sellingPrice) disc = item.sellingPrice;
        item.discountAmount = disc;
        this.recalculateCart();
    },

    removeFromCart(productId) {
        this.cart = this.cart.filter(i => i.productId !== productId);
        this.renderCart();
    },

    clearCart() {
        if (this.cart.length === 0) return;
        App.confirm('Clear Cart', 'Are you sure you want to remove all items from the current bill?', () => {
            this.cart = [];
            this.billDiscountValue = 0;
            document.getElementById('billDiscountInput').value = '';
            this.renderCart();
            App.toast('Cart cleared', 'info');
        });
    },

    renderCart() {
        const container = document.getElementById('posCartItems');
        if (!container) return;

        if (this.cart.length === 0) {
            container.innerHTML = `
                <div class="cart-empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <div style="font-weight:700; color:var(--slate-600); font-size:14px;">Cart is Empty</div>
                    <div style="font-size:12px; margin-top:2px;">Click products from the catalog to add to bill</div>
                </div>
            `;
            this.recalculateCart();
            return;
        }

        container.innerHTML = this.cart.map(item => {
            const lineGross = item.sellingPrice * item.quantity;
            const lineDisc = (item.discountAmount || 0) * item.quantity;
            const lineNet = Math.max(0, lineGross - lineDisc);

            return `
                <div class="cart-row">
                    <div class="cart-row-top">
                        <div class="cart-item-info">
                            <div class="cart-item-title">${item.name}</div>
                            <div class="cart-item-tags">
                                SKU: ${item.sku} &bull; Size: <strong>${item.size}</strong> &bull; Color: ${item.color}
                            </div>
                        </div>
                        <button class="cart-item-del" onclick="POS.removeFromCart('${item.productId}')" title="Remove item">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>

                    <div class="cart-row-bottom">
                        <div class="qty-control">
                            <button class="qty-btn" onclick="POS.updateCartQty('${item.productId}', -1)">-</button>
                            <input type="text" class="qty-val" value="${item.quantity}" onchange="POS.setCartQty('${item.productId}', this.value)">
                            <button class="qty-btn" onclick="POS.updateCartQty('${item.productId}', 1)">+</button>
                        </div>

                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="font-size:10px; color:var(--slate-500);">Disc/pc:</div>
                            <input type="number" step="10" min="0" value="${item.discountAmount || ''}" placeholder="0" 
                                style="width:50px; font-size:11px; padding:3px 4px; border:1px solid var(--slate-300); border-radius:3px;"
                                onchange="POS.setCartItemDiscount('${item.productId}', this.value)">
                        </div>

                        <div class="cart-item-pricing">
                            <div class="cart-line-total">${App.formatINR(lineNet)}</div>
                            <div class="cart-unit-rate">@ ${App.formatINR(item.sellingPrice)}</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        this.recalculateCart();
    },

    recalculateCart() {
        let subtotal = 0;
        let itemDiscountTotal = 0;
        let totalPcs = 0;

        this.cart.forEach(item => {
            const gross = item.sellingPrice * item.quantity;
            const disc = (item.discountAmount || 0) * item.quantity;
            subtotal += gross;
            itemDiscountTotal += disc;
            totalPcs += item.quantity;
        });

        // Bill discount
        let billDiscount = 0;
        if (this.billDiscountType === 'percentage') {
            billDiscount = Math.round((subtotal - itemDiscountTotal) * (this.billDiscountValue / 100));
        } else {
            billDiscount = this.billDiscountValue;
        }

        const totalDiscount = itemDiscountTotal + billDiscount;
        const taxableBase = Math.max(0, subtotal - totalDiscount);

        // Tax estimation for display
        let totalTax = 0;
        let cgst = 0;
        let sgst = 0;
        let igst = 0;

        if (this.invoiceType === 'gst' && this.cart.length > 0) {
            // Apply proportional tax
            const sumNet = Math.max(1, subtotal - itemDiscountTotal);
            this.cart.forEach(item => {
                const itemNet = (item.sellingPrice - (item.discountAmount || 0)) * item.quantity;
                const ratio = itemNet / sumNet;
                const effBase = Math.max(0, itemNet - (billDiscount * ratio));
                const tax = Math.round(effBase * (item.gstRate / 100) * 100) / 100;
                totalTax += tax;
            });

            if (this.isInterstate) {
                igst = totalTax;
            } else {
                cgst = Math.round((totalTax / 2) * 100) / 100;
                sgst = Math.round((totalTax - cgst) * 100) / 100;
            }
        }

        const exactTotal = taxableBase + totalTax;
        const roundedTotal = Math.round(exactTotal);
        const roundOff = Math.round((roundedTotal - exactTotal) * 100) / 100;

        // Update DOM elements
        document.getElementById('cartItemsCount').innerText = `${this.cart.length} items (${totalPcs} pcs)`;
        document.getElementById('cartSubtotal').innerText = App.formatINR(subtotal);
        document.getElementById('cartDiscountTotal').innerText = `- ${App.formatINR(totalDiscount)}`;
        
        const gstBox = document.getElementById('gstBreakupBox');
        if (this.invoiceType === 'gst') {
            gstBox.style.display = 'block';
            document.getElementById('cartTaxableValue').innerText = App.formatINR(taxableBase);
            document.getElementById('cartCgst').innerText = App.formatINR(cgst);
            document.getElementById('cartSgst').innerText = App.formatINR(sgst);
        } else {
            gstBox.style.display = 'none';
        }

        document.getElementById('cartRoundOff').innerText = (roundOff >= 0 ? '+ ' : '') + App.formatINR(roundOff);
        document.getElementById('cartGrandTotal').innerText = App.formatINR(roundedTotal);

        const checkoutBtn = document.getElementById('checkoutBtn');
        if (checkoutBtn) {
            checkoutBtn.disabled = (this.cart.length === 0);
        }
    },

    async checkout() {
        if (this.cart.length === 0) {
            App.toast('Cannot complete sale: Cart is empty!', 'warning');
            return;
        }

        const checkoutBtn = document.getElementById('checkoutBtn');
        checkoutBtn.disabled = true;
        checkoutBtn.innerHTML = `<svg class="animate-spin" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Processing Sale & Generating Invoice...`;

        const rawPhone = document.getElementById('customerPhone') ? document.getElementById('customerPhone').value.trim() : '';
        const rawName = document.getElementById('customerName') ? document.getElementById('customerName').value.trim() : '';
        const finalName = rawName || (rawPhone ? 'Customer' : 'Walk-in Customer');

        const payload = {
            invoiceType: this.invoiceType,
            paymentMethod: this.paymentMethod,
            isInterstate: this.isInterstate,
            billDiscountType: this.billDiscountType,
            billDiscountValue: this.billDiscountValue,
            notes: document.getElementById('posNotes') ? document.getElementById('posNotes').value : '',
            customer: {
                customerId: this.currentCustomer.id || '',
                name: finalName,
                phone: rawPhone,
                email: this.currentCustomer.email || '',
                address: this.currentCustomer.address || '',
                gstin: this.currentCustomer.gstin || ''
            },
            items: this.cart.map(item => ({
                productId: item.productId,
                quantity: item.quantity,
                discountAmount: item.discountAmount || 0
            }))
        };

        try {
            const res = await App.api('api/billing.php?action=checkout', {
                method: 'POST',
                body: payload
            });

            // Show Success Modal
            document.getElementById('successInvoiceNo').innerText = res.invoiceNumber;
            document.getElementById('successGrandTotal').innerText = App.formatINR(res.grandTotal);
            document.getElementById('successPrintBtn').href = res.printUrl;
            document.getElementById('successPdfBtn').href = res.pdfUrl;

            // Wire WhatsApp & Sharing Features
            const custDisplay = (res.customerName && res.customerName !== 'Walk-in Customer')
                ? `Customer: ${res.customerName} (${res.customerPhone || 'No Phone'})`
                : (res.customerPhone ? `Customer Phone: ${res.customerPhone}` : 'Walk-in Customer');
            const custElem = document.getElementById('successCustomerText');
            if (custElem) custElem.innerText = custDisplay;

            const waBtn = document.getElementById('successWhatsAppBtn');
            if (waBtn) {
                waBtn.href = res.whatsappUrl || '#';
            }

            const publicViewBtn = document.getElementById('successPublicViewBtn');
            if (publicViewBtn) {
                publicViewBtn.href = res.publicBillUrl || '#';
            }

            const copyBtn = document.getElementById('successCopyBtn');
            if (copyBtn) {
                copyBtn.onclick = () => {
                    const linkToCopy = res.publicBillUrl || window.location.origin + '/bill.php?id=' + res.invoiceId;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(linkToCopy).then(() => {
                            App.toast('Digital Bill Link copied to clipboard! 📋', 'success');
                        }).catch(() => {
                            prompt('Copy invoice link:', linkToCopy);
                        });
                    } else {
                        prompt('Copy invoice link:', linkToCopy);
                    }
                };
            }

            const shareBtn = document.getElementById('successShareBtn');
            if (shareBtn) {
                shareBtn.onclick = () => {
                    const linkToShare = res.publicBillUrl || window.location.origin + '/bill.php?id=' + res.invoiceId;
                    if (navigator.share) {
                        navigator.share({
                            title: `साम्राज्य.07 — Bill ${res.invoiceNumber}`,
                            text: res.shareText || `Receipt for Bill #${res.invoiceNumber} from साम्राज्य.07`,
                            url: linkToShare
                        }).catch(() => {});
                    } else {
                        if (copyBtn) copyBtn.click();
                    }
                };
            }

            App.openModal('saleSuccessModal');

            // Reset Cart & Customer
            this.cart = [];
            this.billDiscountValue = 0;
            document.getElementById('billDiscountInput').value = '';
            this.resetCustomer();
            this.renderCart();

            // Reload fresh stock balances in background
            this.loadProducts();

        } catch (err) {
            console.error('Checkout failed:', err);
        } finally {
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = `
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Complete Sale & Print Bill (F9)
            `;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    POS.init();
});
