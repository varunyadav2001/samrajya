/**
 * Samrajya Mens Wear - Core Application JavaScript
 */

const App = {
    // Show Toast Notification
    toast(message, type = 'success', duration = 3500) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const iconSvg = type === 'error'
            ? `<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
            : type === 'warning'
            ? `<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`
            : `<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>`;

        toast.innerHTML = `
            <div style="color: ${type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#10b981'}; display:flex; align-items:center;">
                ${iconSvg}
            </div>
            <div style="flex:1; font-weight:600;">${message}</div>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    // Format INR Currency
    formatINR(amount) {
        const num = parseFloat(amount || 0);
        return '₹ ' + num.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    // Escape HTML to prevent XSS
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    // Open Modal
    openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },

    // Close Modal
    closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    },

    // Confirm Dialog Helper
    confirm(title, message, onConfirm) {
        const modalId = 'globalConfirmModal';
        let modal = document.getElementById(modalId);
        if (!modal) {
            modal = document.createElement('div');
            modal.id = modalId;
            modal.className = 'modal-backdrop';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-header">
                        <div class="modal-title" id="confirmTitle">${title}</div>
                        <button class="modal-close" onclick="App.closeModal('${modalId}')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p id="confirmMessage" style="color:var(--slate-600); font-size:14px;">${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="App.closeModal('${modalId}')">Cancel</button>
                        <button class="btn btn-danger" id="confirmOkBtn">Proceed</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        } else {
            document.getElementById('confirmTitle').innerText = title;
            document.getElementById('confirmMessage').innerText = message;
        }

        const okBtn = document.getElementById('confirmOkBtn');
        const newOkBtn = okBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(newOkBtn, okBtn);

        newOkBtn.addEventListener('click', () => {
            App.closeModal(modalId);
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });

        App.openModal(modalId);
    },

    // API Fetch Wrapper
    async api(url, options = {}) {
        try {
            const defaults = {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            };
            const config = Object.assign({}, defaults, options);
            if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
                config.body = JSON.stringify(options.body);
            }
            if (options.body instanceof FormData) {
                delete config.headers['Content-Type'];
            }

            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok || !data.success) {
                const error = (data && data.error) ? data.error : `Request failed (HTTP ${response.status})`;
                throw new Error(error);
            }

            return data;
        } catch (err) {
            console.error('API Error:', err);
            App.toast(err.message, 'error');
            throw err;
        }
    },

    // Initialize Global Listeners
    init() {
        // Close modal on backdrop click
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    backdrop.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Mobile sidebar toggle with backdrop & close button
        const toggleBtn = document.querySelector('.mobile-toggle');
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const closeBtn = document.getElementById('sidebarCloseBtn');

        function openSidebar() {
            if (sidebar) sidebar.classList.add('open');
            if (backdrop) backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (sidebar && sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        if (backdrop) {
            backdrop.addEventListener('click', closeSidebar);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});
