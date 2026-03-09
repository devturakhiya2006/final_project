/**
 * Handles all API communications with proper error handling and fallbacks
 */

class APIHandler {
    constructor() {
        // Auto-detect environment and set API base URL accordingly
        // 1) When front-end is served via XAMPP (http://localhost/sem5goinvoice/project%201/front/...),
        //    we can use a relative path to ../api/
        // 2) When front-end is served via Live Server (http://127.0.0.1:5501/...),
        //    posting to ../api/ will hit the static server (no PHP) and return 405.
        //    In that case, route to the XAMPP backend explicitly.
        const isLiveServer = /^(127\.0\.0\.1|localhost):55\d{2}$/.test(window.location.host);
        const xamppBase = 'http://localhost/sem5goinvoice/project%201/api/';
        this.baseURL = isLiveServer ? xamppBase : '../api/';
        this.timeout = 10000; // 10 seconds
        this.retryAttempts = 3;
    }

    // Create fetch request with timeout
    async fetchWithTimeout(url, options = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.timeout);

        try {
            const response = await fetch(url, {
                ...options,
                credentials: 'include',
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            return response;
        } catch (error) {
            clearTimeout(timeoutId);
            throw error;
        }
    }

    // Upload using FormData (no JSON Content-Type)
    async uploadFormData(endpoint, formData) {
        const url = this.baseURL + endpoint;
        const options = {
            method: 'POST',
            body: formData
        };
        const response = await this.retryRequest(url, options);
        if (!response.ok) {
            let msg = '';
            try { msg = await response.text(); } catch (_) { }
            throw new Error(`HTTP ${response.status}: ${msg || 'Upload failed'}`);
        }
        const ct = (response.headers.get('Content-Type') || '').toLowerCase();
        if (!ct.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Server returned non-JSON response: ${text.substring(0, 300)}`);
        }
        return response.json();
    }

    // Retry mechanism for failed requests
    async retryRequest(url, options, attempt = 1) {
        try {
            return await this.fetchWithTimeout(url, options);
        } catch (error) {
            if (attempt < this.retryAttempts && (error.name === 'AbortError' || error.name === 'TypeError')) {
                console.log(`Retry attempt ${attempt} for ${url}`);
                await this.delay(1000 * attempt); // Progressive delay
                return this.retryRequest(url, options, attempt + 1);
            }
            throw error;
        }
    }

    // Delay utility
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Generic API call method
    async makeRequest(endpoint, method = 'GET', data = null) {
        const url = this.baseURL + endpoint;

        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await this.retryRequest(url, options);

            // If not OK, try to extract server error details for better diagnostics
            if (!response.ok) {
                let serverMessage = '';
                try {
                    const contentType = response.headers.get('Content-Type') || '';
                    if (contentType.includes('application/json')) {
                        const body = await response.json();
                        serverMessage = body?.error || body?.message || '';
                    } else {
                        // Fallback to text if not JSON
                        serverMessage = await response.text();
                    }
                } catch (_) {
                    // Ignore body parse errors
                }

                const statusText = response.statusText || 'Error';
                const detailed = serverMessage ? `: ${serverMessage}` : '';
                throw new Error(`HTTP ${response.status} ${statusText}${detailed}`);
            }

            const ct = (response.headers.get('Content-Type') || '').toLowerCase();
            if (!ct.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Server returned non-JSON response: ${text.substring(0, 300)}`);
            }
            const result = await response.json();
            return result;

        } catch (error) {
            console.error('API Request failed:', error);

            // Handle different types of errors
            if (error.name === 'AbortError') {
                throw new Error('Request timed out. Please check your internet connection.');
            } else if (error.name === 'TypeError') {
                throw new Error('Network error. Please check if the server is running.');
            } else if (error.message?.startsWith('HTTP')) {
                // Surface the detailed HTTP error constructed above
                throw new Error(error.message);
            } else {
                throw new Error('An unexpected error occurred. Please try again.');
            }
        }
    }

    // Authentication methods
    async signup(userData) {
        return this.makeRequest('auth.php?action=signup', 'POST', userData);
    }

    async login(credentials) {
        return this.makeRequest('auth.php?action=login', 'POST', credentials);
    }

    async logout() {
        return this.makeRequest('auth.php?action=logout', 'POST');
    }

    async checkSession() {
        return this.makeRequest('auth.php?action=check_session', 'GET');
    }

    // Customer methods
    async getCustomers() {
        return this.makeRequest('customers.php', 'GET');
    }

    async createCustomer(customerData) {
        return this.makeRequest('customers.php', 'POST', customerData);
    }

    async updateCustomer(id, customerData) {
        return this.makeRequest(`customers.php?id=${id}`, 'PUT', customerData);
    }

    async deleteCustomer(id) {
        return this.makeRequest(`customers.php?id=${id}`, 'DELETE');
    }

    // Product methods
    async getProducts() {
        return this.makeRequest('products.php', 'GET');
    }

    async createProduct(productData) {
        return this.makeRequest('products.php', 'POST', productData);
    }

    async updateProduct(id, productData) {
        return this.makeRequest(`products.php?id=${id}`, 'PUT', productData);
    }

    async deleteProduct(id) {
        return this.makeRequest(`products.php?id=${id}`, 'DELETE');
    }

    // Invoice methods
    async getInvoices() {
        return this.makeRequest('invoices.php', 'GET');
    }

    async createInvoice(invoiceData) {
        return this.makeRequest('invoices.php', 'POST', invoiceData);
    }

    async updateInvoice(id, invoiceData) {
        return this.makeRequest(`invoices.php?id=${id}`, 'PUT', invoiceData);
    }

    async deleteInvoice(id) {
        return this.makeRequest(`invoices.php?id=${id}`, 'DELETE');
    }

    // Dashboard methods
    async getDashboardData() {
        return this.makeRequest('dashboard.php', 'GET');
    }

    // Profile methods
    async getProfile() {
        return this.makeRequest('profile.php?action=get_profile', 'GET');
    }

    async updateBank(details) {
        return this.makeRequest('profile.php?action=update_bank', 'POST', details);
    }

    async uploadLogo(file) {
        const fd = new FormData();
        fd.append('logo', file);
        return this.uploadFormData('profile.php?action=upload_logo', fd);
    }

    // Dashboard snapshots
    async saveSnapshot(payload) {
        return this.makeRequest('dashboard.php?action=save_snapshot', 'POST', payload);
    }

    async getSnapshots(params = {}) {
        const pt = encodeURIComponent(params.period_type || 'month');
        const limit = encodeURIComponent(params.limit || 12);
        return this.makeRequest(`dashboard.php?action=get_snapshots&period_type=${pt}&limit=${limit}`, 'GET');
    }
}

// Offline detection and handling
class OfflineHandler {
    constructor() {
        this.isOnline = navigator.onLine;
        this.setupEventListeners();
        this.queuedRequests = [];
    }

    setupEventListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.showConnectionStatus('Connected', 'success');
            this.processQueuedRequests();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showConnectionStatus('No internet connection', 'error');
        });
    }

    showConnectionStatus(message, type) {
        // Remove existing status messages
        const existingStatus = document.querySelector('.connection-status');
        if (existingStatus) {
            existingStatus.remove();
        }

        // Create status message
        const statusDiv = document.createElement('div');
        statusDiv.className = `connection-status alert alert-${type === 'success' ? 'success' : 'danger'}`;
        statusDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        `;
        statusDiv.textContent = message;

        document.body.appendChild(statusDiv);

        // Auto remove after 3 seconds
        setTimeout(() => {
            if (statusDiv.parentNode) {
                statusDiv.remove();
            }
        }, 3000);
    }

    queueRequest(requestFunction) {
        if (this.isOnline) {
            return requestFunction();
        } else {
            return new Promise((resolve, reject) => {
                this.queuedRequests.push({ requestFunction, resolve, reject });
                this.showConnectionStatus('Request queued - will retry when online', 'warning');
            });
        }
    }

    async processQueuedRequests() {
        while (this.queuedRequests.length > 0) {
            const { requestFunction, resolve, reject } = this.queuedRequests.shift();
            try {
                const result = await requestFunction();
                resolve(result);
            } catch (error) {
                reject(error);
            }
        }
    }
}

// Initialize global instances
window.apiHandler = new APIHandler();
window.offlineHandler = new OfflineHandler();
