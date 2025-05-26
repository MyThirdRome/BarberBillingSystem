/**
 * Global JavaScript functions for the Barber Shop Management System
 */

// Global application state
window.App = {
    initialized: false,
    csrfToken: '',
    currentUser: null,
    settings: {}
};

/**
 * Initialize the application
 */
document.addEventListener('DOMContentLoaded', function() {
    if (window.App.initialized) return;
    
    // Set CSRF token
    if (typeof window.csrfToken !== 'undefined') {
        window.App.csrfToken = window.csrfToken;
    }
    
    // Initialize components
    initializeTooltips();
    initializePopovers();
    initializeDataTables();
    initializeFormValidation();
    initializeDateInputs();
    initializeNumberInputs();
    initializeSearchFilters();
    initializeModals();
    
    // Mark as initialized
    window.App.initialized = true;
    
    console.log('Barber Shop Management System initialized');
});

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize Bootstrap popovers
 */
function initializePopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Initialize enhanced data tables
 */
function initializeDataTables() {
    const tables = document.querySelectorAll('.table[data-sortable="true"]');
    
    tables.forEach(table => {
        addTableSorting(table);
    });
}

/**
 * Add sorting functionality to tables
 */
function addTableSorting(table) {
    const headers = table.querySelectorAll('thead th[data-sort]');
    
    headers.forEach(header => {
        header.style.cursor = 'pointer';
        header.innerHTML += ' <i class="fas fa-sort text-muted"></i>';
        
        header.addEventListener('click', function() {
            const column = this.dataset.sort;
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Determine sort direction
            const currentDirection = this.dataset.direction || 'asc';
            const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            
            // Update all headers
            headers.forEach(h => {
                h.dataset.direction = '';
                const icon = h.querySelector('i');
                icon.className = 'fas fa-sort text-muted';
            });
            
            // Update current header
            this.dataset.direction = newDirection;
            const icon = this.querySelector('i');
            icon.className = `fas fa-sort-${newDirection === 'asc' ? 'up' : 'down'} text-primary`;
            
            // Sort rows
            rows.sort((a, b) => {
                const aValue = getCellValue(a, column);
                const bValue = getCellValue(b, column);
                
                if (newDirection === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
            
            // Reorder rows
            rows.forEach(row => tbody.appendChild(row));
        });
    });
}

/**
 * Get cell value for sorting
 */
function getCellValue(row, column) {
    const cell = row.querySelector(`td[data-sort="${column}"]`);
    if (!cell) return '';
    
    const value = cell.textContent.trim();
    
    // Try to parse as number
    const numValue = parseFloat(value.replace(/[^\d.-]/g, ''));
    if (!isNaN(numValue)) return numValue;
    
    // Try to parse as date
    const dateValue = new Date(value);
    if (!isNaN(dateValue.getTime())) return dateValue.getTime();
    
    // Return as string
    return value.toLowerCase();
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    const forms = document.querySelectorAll('form[data-validate="true"]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        });
    });
}

/**
 * Validate entire form
 */
function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

/**
 * Validate individual field
 */
function validateField(field) {
    let isValid = true;
    const value = field.value.trim();
    const rules = field.dataset.validate ? field.dataset.validate.split('|') : [];
    
    // Clear previous errors
    clearFieldError(field);
    
    rules.forEach(rule => {
        if (!isValid) return;
        
        const [ruleName, ruleValue] = rule.split(':');
        
        switch (ruleName) {
            case 'required':
                if (!value) {
                    showFieldError(field, 'Ce champ est obligatoire');
                    isValid = false;
                }
                break;
                
            case 'min':
                if (value.length < parseInt(ruleValue)) {
                    showFieldError(field, `Minimum ${ruleValue} caractères requis`);
                    isValid = false;
                }
                break;
                
            case 'max':
                if (value.length > parseInt(ruleValue)) {
                    showFieldError(field, `Maximum ${ruleValue} caractères autorisés`);
                    isValid = false;
                }
                break;
                
            case 'email':
                if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    showFieldError(field, 'Adresse email invalide');
                    isValid = false;
                }
                break;
                
            case 'numeric':
                if (value && !/^\d+(\.\d+)?$/.test(value)) {
                    showFieldError(field, 'Valeur numérique requise');
                    isValid = false;
                }
                break;
                
            case 'positive':
                if (value && parseFloat(value) <= 0) {
                    showFieldError(field, 'Valeur positive requise');
                    isValid = false;
                }
                break;
        }
    });
    
    return isValid;
}

/**
 * Show field error
 */
function showFieldError(field, message) {
    field.classList.add('is-invalid');
    
    let feedback = field.parentNode.querySelector('.invalid-feedback');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        field.parentNode.appendChild(feedback);
    }
    
    feedback.textContent = message;
}

/**
 * Clear field error
 */
function clearFieldError(field) {
    field.classList.remove('is-invalid');
    const feedback = field.parentNode.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.remove();
    }
}

/**
 * Initialize date inputs with proper formatting
 */
function initializeDateInputs() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    
    dateInputs.forEach(input => {
        // Set max date to today if not specified
        if (!input.max) {
            input.max = new Date().toISOString().split('T')[0];
        }
        
        // Add date validation
        input.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            
            if (selectedDate > today) {
                showFieldError(this, 'La date ne peut pas être dans le futur');
                this.value = '';
            } else {
                clearFieldError(this);
            }
        });
    });
    
    // Initialize datetime-local inputs
    const datetimeInputs = document.querySelectorAll('input[type="datetime-local"]');
    
    datetimeInputs.forEach(input => {
        if (!input.value) {
            // Set default to current datetime
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            input.value = now.toISOString().slice(0, 16);
        }
    });
}

/**
 * Initialize number inputs with proper formatting
 */
function initializeNumberInputs() {
    const numberInputs = document.querySelectorAll('input[type="number"]');
    
    numberInputs.forEach(input => {
        // Format on blur
        input.addEventListener('blur', function() {
            if (this.value && this.step === '0.01') {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
        
        // Prevent negative values if min is 0
        input.addEventListener('input', function() {
            if (this.min === '0' && parseFloat(this.value) < 0) {
                this.value = '0';
            }
        });
    });
}

/**
 * Initialize search and filter functionality
 */
function initializeSearchFilters() {
    const searchInputs = document.querySelectorAll('input[data-search]');
    
    searchInputs.forEach(input => {
        let timeout;
        
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const target = document.querySelector(this.dataset.search);
                if (target) {
                    filterTable(target, this.value);
                }
            }, 300);
        });
    });
}

/**
 * Filter table rows based on search term
 */
function filterTable(table, searchTerm) {
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const matches = text.includes(searchTerm.toLowerCase());
        
        row.style.display = matches ? '' : 'none';
    });
    
    // Show "no results" message if needed
    const visibleRows = tbody.querySelectorAll('tr[style=""]');
    let noResultsRow = tbody.querySelector('.no-results-row');
    
    if (visibleRows.length === 0 && searchTerm) {
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `<td colspan="100%" class="text-center text-muted py-4">Aucun résultat trouvé</td>`;
            tbody.appendChild(noResultsRow);
        }
        noResultsRow.style.display = '';
    } else if (noResultsRow) {
        noResultsRow.style.display = 'none';
    }
}

/**
 * Initialize modal enhancements
 */
function initializeModals() {
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            // Focus first input
            const firstInput = this.querySelector('input, select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        modal.addEventListener('hidden.bs.modal', function() {
            // Clear form data
            const form = this.querySelector('form');
            if (form) {
                form.reset();
                clearFormErrors(form);
            }
        });
    });
}

/**
 * Clear all form errors
 */
function clearFormErrors(form) {
    const invalidFields = form.querySelectorAll('.is-invalid');
    const feedbacks = form.querySelectorAll('.invalid-feedback');
    
    invalidFields.forEach(field => field.classList.remove('is-invalid'));
    feedbacks.forEach(feedback => feedback.remove());
}

/**
 * Show notification toast
 */
function showNotification(message, type = 'success', duration = 5000) {
    const toastContainer = getOrCreateToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${getToastIcon(type)} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, {
        delay: duration
    });
    
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        toastContainer.removeChild(toast);
    });
}

/**
 * Get or create toast container
 */
function getOrCreateToastContainer() {
    let container = document.querySelector('.toast-container');
    
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    return container;
}

/**
 * Get icon for toast type
 */
function getToastIcon(type) {
    const icons = {
        success: 'check-circle',
        danger: 'exclamation-triangle',
        warning: 'exclamation-circle',
        info: 'info-circle'
    };
    
    return icons[type] || 'info-circle';
}

/**
 * Confirm action with modal
 */
function confirmAction(title, message, callback, type = 'danger') {
    const confirmModal = document.createElement('div');
    confirmModal.className = 'modal fade';
    confirmModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">${title}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-${type}" id="confirmButton">Confirmer</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(confirmModal);
    
    const modal = new bootstrap.Modal(confirmModal);
    const confirmButton = confirmModal.querySelector('#confirmButton');
    
    confirmButton.addEventListener('click', () => {
        callback();
        modal.hide();
    });
    
    confirmModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(confirmModal);
    });
    
    modal.show();
}

/**
 * Format currency value
 */
function formatCurrency(amount, currency = '€') {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

/**
 * Format date for display
 */
function formatDate(dateString, options = {}) {
    const defaultOptions = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    };
    
    return new Date(dateString).toLocaleDateString('fr-FR', {...defaultOptions, ...options});
}

/**
 * Format datetime for display
 */
function formatDateTime(dateTimeString, options = {}) {
    const defaultOptions = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    };
    
    return new Date(dateTimeString).toLocaleDateString('fr-FR', {...defaultOptions, ...options});
}

/**
 * Calculate age from birthdate
 */
function calculateAge(birthdate) {
    const today = new Date();
    const birth = new Date(birthdate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    
    return age;
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * AJAX helper function
 */
function makeRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.App.csrfToken
        }
    };
    
    return fetch(url, {...defaultOptions, ...options})
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Request failed:', error);
            showNotification('Une erreur est survenue', 'danger');
            throw error;
        });
}

/**
 * Export data to CSV
 */
function exportToCSV(data, filename) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

/**
 * Convert data to CSV format
 */
function convertToCSV(data) {
    if (!data.length) return '';
    
    const headers = Object.keys(data[0]);
    const csvHeaders = headers.join(';');
    
    const csvRows = data.map(row => {
        return headers.map(header => {
            const value = row[header];
            return typeof value === 'string' ? `"${value.replace(/"/g, '""')}"` : value;
        }).join(';');
    });
    
    return [csvHeaders, ...csvRows].join('\n');
}

/**
 * Print current page
 */
function printPage() {
    window.print();
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copié dans le presse-papiers', 'success', 2000);
    }).catch(() => {
        showNotification('Erreur lors de la copie', 'danger', 3000);
    });
}

// Expose functions to global scope
window.showNotification = showNotification;
window.confirmAction = confirmAction;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.formatDateTime = formatDateTime;
window.calculateAge = calculateAge;
window.exportToCSV = exportToCSV;
window.printPage = printPage;
window.copyToClipboard = copyToClipboard;
window.makeRequest = makeRequest;
