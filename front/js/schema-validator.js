/**
 * JSON Schema Validator for GoInvoice
 * Validates data against predefined schemas
 */

class SchemaValidator {
    constructor() {
        this.schemas = {};
        this.loadSchemas();
    }

    async loadSchemas() {
        try {
            // Cache-bust to avoid stale schemas.json after updates
            const response = await fetch(`./js/schemas.json?v=${Date.now()}`);
            this.schemas = await response.json();
            // Ensure a minimal signup schema exists even if not present in file
            if (!this.schemas.signup) {
                this.schemas.signup = {
                    type: 'object',
                    properties: {
                        name: { type: 'string', minLength: 2, maxLength: 50 },
                        email: { type: 'string', format: 'email' },
                        mobile: { type: 'string', pattern: '^[0-9]{10}$' },
                        password: { type: 'string', minLength: 8 },
                        confirmPassword: { type: 'string', minLength: 8 }
                    },
                    required: ['name', 'email', 'mobile', 'password', 'confirmPassword']
                };
            }
        } catch (error) {
            console.error('Failed to load schemas:', error);
            // Fallback schemas
            this.schemas = this.getFallbackSchemas();
        }
    }

    getFallbackSchemas() {
        return {
            user: {
                type: "object",
                properties: {
                    name: { type: "string", minLength: 2, maxLength: 50 },
                    email: { type: "string", format: "email" },
                    mobile: { type: "string", pattern: "^[6-9]\\d{9}$" },
                    password: { type: "string", minLength: 8 }
                },
                required: ["name", "email", "mobile", "password"]
            },
            signup: {
                type: 'object',
                properties: {
                    name: { type: 'string', minLength: 2, maxLength: 50 },
                    email: { type: 'string', format: 'email' },
                    mobile: { type: 'string', pattern: '^[0-9]{10}$' },
                    password: { type: 'string', minLength: 8 },
                    confirmPassword: { type: 'string', minLength: 8 }
                },
                required: ['name', 'email', 'mobile', 'password', 'confirmPassword']
            },
            login: {
                type: "object",
                properties: {
                    email: { type: "string", format: "email" },
                    password: { type: "string", minLength: 1 }
                },
                required: ["email", "password"]
            }
        };
    }

    // Validate data against schema
    validate(data, schemaName) {
        const schema = this.schemas[schemaName];
        if (!schema) {
            return { valid: false, errors: [`Schema '${schemaName}' not found`] };
        }

        const errors = [];
        const result = this.validateObject(data, schema, '', errors);
        
        return {
            valid: errors.length === 0,
            errors: errors
        };
    }

    validateObject(data, schema, path, errors) {
        if (schema.type !== 'object') {
            return this.validateValue(data, schema, path, errors);
        }

        // Check required fields
        if (schema.required) {
            for (const field of schema.required) {
                if (!(field in data) || data[field] === null || data[field] === undefined || data[field] === '') {
                    errors.push(`${path}${field} is required`);
                }
            }
        }

        // Validate each property
        if (schema.properties) {
            for (const [key, propSchema] of Object.entries(schema.properties)) {
                if (key in data) {
                    this.validateValue(data[key], propSchema, `${path}${key}.`, errors);
                }
            }
        }

        return errors.length === 0;
    }

    validateValue(value, schema, path, errors) {
        // Type validation
        if (schema.type) {
            if (!this.validateType(value, schema.type)) {
                errors.push(`${path} must be of type ${schema.type}`);
                return false;
            }
        }

        // String validations
        if (schema.type === 'string' && typeof value === 'string') {
            if (schema.minLength && value.length < schema.minLength) {
                errors.push(`${path} must be at least ${schema.minLength} characters long`);
            }
            if (schema.maxLength && value.length > schema.maxLength) {
                errors.push(`${path} must be no more than ${schema.maxLength} characters long`);
            }
            if (schema.pattern && !new RegExp(schema.pattern).test(value)) {
                errors.push(`${path} format is invalid`);
            }
            if (schema.format === 'email' && !this.validateEmail(value)) {
                errors.push(`${path} must be a valid email address`);
            }
            if (schema.format === 'date' && !this.validateDate(value)) {
                errors.push(`${path} must be a valid date`);
            }
        }

        // Number validations
        if ((schema.type === 'number' || schema.type === 'integer') && typeof value === 'number') {
            if (schema.minimum !== undefined && value < schema.minimum) {
                errors.push(`${path} must be at least ${schema.minimum}`);
            }
            if (schema.maximum !== undefined && value > schema.maximum) {
                errors.push(`${path} must be no more than ${schema.maximum}`);
            }
        }

        // Array validations
        if (schema.type === 'array' && Array.isArray(value)) {
            if (schema.minItems && value.length < schema.minItems) {
                errors.push(`${path} must have at least ${schema.minItems} items`);
            }
            if (schema.maxItems && value.length > schema.maxItems) {
                errors.push(`${path} must have no more than ${schema.maxItems} items`);
            }
            if (schema.items) {
                value.forEach((item, index) => {
                    this.validateValue(item, schema.items, `${path}[${index}].`, errors);
                });
            }
        }

        // Enum validation
        if (schema.enum && !schema.enum.includes(value)) {
            errors.push(`${path} must be one of: ${schema.enum.join(', ')}`);
        }

        return errors.length === 0;
    }

    validateType(value, expectedType) {
        switch (expectedType) {
            case 'string':
                return typeof value === 'string';
            case 'number':
                return typeof value === 'number' && !isNaN(value);
            case 'integer':
                return typeof value === 'number' && Number.isInteger(value);
            case 'boolean':
                return typeof value === 'boolean';
            case 'array':
                return Array.isArray(value);
            case 'object':
                return typeof value === 'object' && value !== null && !Array.isArray(value);
            default:
                return true;
        }
    }

    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    validateDate(dateString) {
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date);
    }

    // Validate and sanitize data
    validateAndSanitize(data, schemaName) {
        const validation = this.validate(data, schemaName);
        if (!validation.valid) {
            return validation;
        }

        const sanitized = this.sanitizeData(data, this.schemas[schemaName]);
        return {
            valid: true,
            data: sanitized,
            errors: []
        };
    }

    sanitizeData(data, schema) {
        const sanitized = {};
        
        if (schema.properties) {
            for (const [key, propSchema] of Object.entries(schema.properties)) {
                if (key in data) {
                    let value = data[key];
                    
                    // Trim strings
                    if (propSchema.type === 'string' && typeof value === 'string') {
                        value = value.trim();
                    }
                    
                    // Convert numbers
                    if ((propSchema.type === 'number' || propSchema.type === 'integer') && typeof value === 'string') {
                        const num = propSchema.type === 'integer' ? parseInt(value) : parseFloat(value);
                        if (!isNaN(num)) {
                            value = num;
                        }
                    }
                    
                    sanitized[key] = value;
                }
            }
        }
        
        return sanitized;
    }
}

// Export for use in other files
window.SchemaValidator = SchemaValidator;
