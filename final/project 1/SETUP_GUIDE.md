# GoInvoice Setup Guide

## Database Setup - COMPLETED ✓

Your database has been successfully created with all necessary tables and columns:
- **Test User Created:** Email: `test@example.com`, Password: `test@123`
- **Tables:** users, customers, products, sales_invoices, and more
- **All Required Columns:** Added missing columns to products and customers tables

## Getting Started - FOLLOW THESE STEPS:

### Step 1: Login First
1. Open your application in the browser
2. Navigate to the **Login** page
3. Enter credentials:
   - **Email:** `test@example.com`
   - **Password:** `test@123`
4. Click **Login**

### Step 2: Create Products
After logging in, you can now:
- Create new products with all options (product_code, SKU, pricing, etc.)
- Add customers/vendors
- Create invoices
- Manage payments

## Database Structure

### Customers Table
Has all fields including:
- Basic info: name, email, mobile, address
- Financial: credit_limit, credit_due_date, opening_balance
- Business: company_name, pan_number, gst_number
- Custom: is_registered, custom_fields, note

### Products Table
Has all fields including:
- Basic: name, description, unit, type (product/service)
- Pricing: price, mrp, sale_price, purchase_price
- Inventory: stock_quantity, available_qty, stock_mode
- Tax: gst_rate, tax_type, eligible_itc
- Classification: product_code, sku, batch_no, product_group
- Media: attachment_path, visible_all_docs, track_inventory

## Session Management

All APIs now properly handle:
- ✓ CORS headers with credentialed requests
- ✓ Session cookies across domains
- ✓ User id validation
- ✓ Better error messages for authentication issues

## If You Get Errors

1. **"Authentication required. Please login first"** → Login through the web app first
2. **"Invalid user session"** → Refresh the page and login again
3. **Foreign key constraint errors** → Make sure user is logged in before creating records

## Summary of Changes

1. Updated database schema to include all product and customer columns
2. Improved CORS handling in all API endpoints
3. Added proper session validation and error messages
4. Created test user in database for immediate testing
5. Fixed session cookie handling for credentialed AJAX requests

---
Last Updated: February 12, 2026
