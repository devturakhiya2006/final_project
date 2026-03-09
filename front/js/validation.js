/**
 * GoInvoice Form Validation Library
 * Provides comprehensive client-side validation for all forms
 */

class FormValidator {
    constructor() {
        this.rules = {
            email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
            mobile: /^[6-9]\d{9}$/,
            gst: /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
            password: /^.{8,}$/,
            name: /^[a-zA-Z\s]{2,50}$/,
            pincode: /^[1-9][0-9]{5}$/
        };
        
        this.messages = {
            required: 'This field is required',
            email: 'Please enter a valid email address',
            mobile: 'Please enter a valid 10-digit mobile number',
            gst: 'Please enter a valid GST number',
            password: 'Password must be at least 8 characters',
            name: 'Name should contain only letters and spaces (2-50 characters)',
            pincode: 'Please enter a valid 6-digit pincode',
            passwordMatch: 'Passwords do not match'
        };
    }

    // Sanitize an Indian mobile number: remove non-digits and keep last 10 digits
    sanitizeMobile(raw) {
        if (typeof raw !== 'string') return raw;
        const digits = raw.replace(/\D/g, '');
        // Common prefixes like 0, 91, 091, 0091, +91 all get removed by keeping last 10
        if (digits.length >= 10) {
            return digits.slice(-10);
        }
        return digits;
    }

    // Validate individual field
    validateField(field, value, type = null) {
        const errors = [];
        
        // Check if required field is empty
        if (field.hasAttribute('required') && (!value || value.trim() === '')) {
            errors.push(this.messages.required);
            return errors;
        }
        
        // Skip validation if field is empty and not required
        if (!value || value.trim() === '') {
            return errors;
        }
        
        // Determine validation type
        const validationType = type || field.type || field.getAttribute('data-validate');
        
        switch (validationType) {
            case 'email':
                if (!this.rules.email.test(value)) {
                    errors.push(this.messages.email);
                }
                break;
                
            case 'tel':
            case 'mobile':
                // Normalize value first and write it back to the field
                const normalized = this.sanitizeMobile(value);
                if (normalized !== value) {
                    field.value = normalized;
                }
                if (!this.rules.mobile.test(normalized)) {
                    errors.push(this.messages.mobile);
                }
                break;
                
            case 'gst':
                if (!this.rules.gst.test(value.toUpperCase())) {
                    errors.push(this.messages.gst);
                }
                break;
                
            case 'password':
                if (!this.rules.password.test(value)) {
                    errors.push(this.messages.password);
                }
                break;
                
            case 'name':
                if (!this.rules.name.test(value)) {
                    errors.push(this.messages.name);
                }
                break;
                
            case 'pincode':
                if (!this.rules.pincode.test(value)) {
                    errors.push(this.messages.pincode);
                }
                break;
        }
        
        return errors;
    }

    // Validate password confirmation
    validatePasswordMatch(password, confirmPassword) {
        if (password !== confirmPassword) {
            return [this.messages.passwordMatch];
        }
        return [];
    }

    // Show field error
    showFieldError(field, errors) {
        this.clearFieldError(field);
        
        if (errors.length > 0) {
            field.classList.add('is-invalid');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.textContent = errors[0];
            
            field.parentNode.appendChild(errorDiv);
            return false;
        } else {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            return true;
        }
    }

    // Clear field error
    clearFieldError(field) {
        field.classList.remove('is-invalid', 'is-valid');
        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    // Validate entire form
    validateForm(form) {
        let isValid = true;
        const formData = {};
        
        // Get all form fields
        const fields = form.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            const value = field.value.trim();
            const errors = this.validateField(field, value);
            
            // Special case for password confirmation
            if (field.name === 'confirmPassword') {
                const passwordField = form.querySelector('input[name="password"]');
                if (passwordField) {
                    const matchErrors = this.validatePasswordMatch(passwordField.value, value);
                    errors.push(...matchErrors);
                }
            }
            
            const fieldValid = this.showFieldError(field, errors);
            if (!fieldValid) {
                isValid = false;
            }
            
            formData[field.name] = value;
        });
        
        return { isValid, formData };
    }

    // Real-time validation setup
    setupRealTimeValidation(form) {
        const fields = form.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            // Validate on blur
            field.addEventListener('blur', () => {
                const value = field.value.trim();
                const errors = this.validateField(field, value);
                
                // Special case for password confirmation
                if (field.name === 'confirmPassword') {
                    const passwordField = form.querySelector('input[name="password"]');
                    if (passwordField) {
                        const matchErrors = this.validatePasswordMatch(passwordField.value, value);
                        errors.push(...matchErrors);
                    }
                }
                
                this.showFieldError(field, errors);
            });
            
            // Clear errors on focus
            field.addEventListener('focus', () => {
                this.clearFieldError(field);
            });
            
            // Real-time validation for password match
            if (field.name === 'password') {
                field.addEventListener('input', () => {
                    const confirmField = form.querySelector('input[name="confirmPassword"]');
                    if (confirmField && confirmField.value) {
                        const matchErrors = this.validatePasswordMatch(field.value, confirmField.value);
                        this.showFieldError(confirmField, matchErrors);
                    }
                });
            }
        });
    }
}

// Export for use in other files
window.FormValidator = FormValidator;
