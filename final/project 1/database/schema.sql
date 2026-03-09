-- GoInvoice Database Schema
-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    business_name VARCHAR(200),
    gst_number VARCHAR(15),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    pincode VARCHAR(10),
    email_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Customers/Vendors table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    mobile VARCHAR(15),
    gst_number VARCHAR(15),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    pincode VARCHAR(10),
    type ENUM('customer', 'vendor') NOT NULL,
    company_name VARCHAR(200),
    is_registered TINYINT DEFAULT 0,
    pan_number VARCHAR(15),
    opening_balance DECIMAL(10,2) DEFAULT 0,
    custom_fields JSON,
    fax VARCHAR(15),
    website VARCHAR(255),
    credit_limit DECIMAL(10,2) DEFAULT 0,
    credit_due_date DATE,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Products/Services table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    hsn_sac VARCHAR(20),
    unit VARCHAR(20) DEFAULT 'pcs',
    price DECIMAL(10,2) NOT NULL,
    gst_rate DECIMAL(5,2) DEFAULT 18.00,
    stock_quantity INT DEFAULT 0,
    type ENUM('product', 'service') DEFAULT 'product',
    product_code VARCHAR(50),
    sku VARCHAR(50),
    batch_no VARCHAR(100),
    tax_type ENUM('inclusive', 'exclusive') DEFAULT 'exclusive',
    eligible_itc TINYINT DEFAULT 0,
    stock_mode ENUM('normal', 'batch') DEFAULT 'normal',
    available_qty INT DEFAULT 0,
    mrp DECIMAL(10,2),
    purchase_price DECIMAL(10,2),
    purchase_price_incl_tax DECIMAL(10,2),
    sale_price DECIMAL(10,2),
    product_group VARCHAR(100),
    discount_type ENUM('none', 'percent', 'amount') DEFAULT 'none',
    attachment_path VARCHAR(255),
    visible_all_docs TINYINT DEFAULT 1,
    track_inventory TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sales Invoices table
CREATE TABLE sales_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    customer_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(10,2) NOT NULL,
    gst_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('pending', 'paid', 'partial') DEFAULT 'pending',
    payment_method ENUM('cash', 'credit', 'cheque', 'online') DEFAULT 'cash',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Sales Invoice Items table
CREATE TABLE sales_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    gst_rate DECIMAL(5,2) DEFAULT 18.00,
    gst_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Purchase Invoices table
CREATE TABLE purchase_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    vendor_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(10,2) NOT NULL,
    gst_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('pending', 'paid', 'partial') DEFAULT 'pending',
    payment_method ENUM('cash', 'credit', 'cheque', 'online') DEFAULT 'cash',
    notes TEXT,
    bank_name VARCHAR(255) NULL,
    account_number VARCHAR(100) NULL,
    ifsc_code VARCHAR(50) NULL,
    upi_id VARCHAR(255) NULL,
    qr_code_path VARCHAR(255) NULL,
    bank_branch VARCHAR(255) NULL,
    account_name VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Purchase Invoice Items table
CREATE TABLE purchase_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    gst_rate DECIMAL(5,2) DEFAULT 18.00,
    gst_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES purchase_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    invoice_id INT,
    customer_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'credit', 'cheque', 'online') NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- User Settings table
CREATE TABLE user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_setting (user_id, setting_key)
);

-- Insert default settings for new users
-- INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES 
-- (1, 'invoice_prefix', 'INV'),
-- (1, 'invoice_start_number', '1001'),
-- (1, 'currency', 'INR'),
-- (1, 'timezone', 'Asia/Kolkata');

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_customers_user_id ON customers(user_id);
CREATE INDEX idx_customers_type ON customers(type);

-- User profile: business logo
CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT PRIMARY KEY,
    logo_path VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User bank & UPI details
CREATE TABLE IF NOT EXISTS user_bank_details (
    user_id INT PRIMARY KEY,
    account_holder VARCHAR(100) DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    ifsc VARCHAR(20) DEFAULT NULL,
    upi_id VARCHAR(100) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dashboard snapshots (historical aggregates)
CREATE TABLE IF NOT EXISTS dashboard_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    period_type ENUM('day','week','month') NOT NULL,
    period_key VARCHAR(16) NOT NULL, -- e.g. '2025-09' or '2025-09-27'
    sales_total DECIMAL(12,2) DEFAULT 0,
    sales_gst DECIMAL(12,2) DEFAULT 0,
    purchases_total DECIMAL(12,2) DEFAULT 0,
    purchases_gst DECIMAL(12,2) DEFAULT 0,
    customers_count INT DEFAULT 0,
    products_count INT DEFAULT 0,
    top_customers JSON NULL,
    top_products JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_snap_user (user_id, period_type, period_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE INDEX idx_products_user_id ON products(user_id);
CREATE INDEX idx_sales_invoices_user_id ON sales_invoices(user_id);
CREATE INDEX idx_sales_invoices_customer_id ON sales_invoices(customer_id);
CREATE INDEX idx_sales_invoices_date ON sales_invoices(invoice_date);
CREATE INDEX idx_purchase_invoices_user_id ON purchase_invoices(user_id);
CREATE INDEX idx_purchase_invoices_vendor_id ON purchase_invoices(vendor_id);
CREATE INDEX idx_payments_user_id ON payments(user_id);
CREATE INDEX idx_payments_invoice_id ON payments(invoice_id);

-- Support inquiries from Partner and Contact forms
CREATE TABLE IF NOT EXISTS support_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    profession VARCHAR(100), -- Partner specific
    city VARCHAR(50), -- Partner specific
    subject VARCHAR(200), -- Contact specific
    message TEXT, -- Contact specific or Partner details
    source ENUM('partner', 'contact') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;