/**
 * FRONOTE - Theme JavaScript
 * Helper functions for interactive UI elements
 */

// Wait until DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dropdown menus
    initializeDropdowns();
    
    // Initialize alert message auto-dismiss
    initializeAlerts();
    
    // Initialize mobile menu toggle
    initializeMobileMenu();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize tooltips
    initTooltips();
});

/**
 * Initializes dropdown menus
 */
function initializeDropdowns() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Find the dropdown menu
            const dropdown = this.nextElementSibling;
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('show');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.matches('.dropdown-toggle') && !e.target.closest('.dropdown-menu')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
}

/**
 * Initializes auto-dismiss for alert messages
 */
function initializeAlerts() {
    const alerts = document.querySelectorAll('.alert-banner');
    
    alerts.forEach(alert => {
        if (!alert.classList.contains('alert-persistent')) {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        }
        
        // Add close button functionality
        const closeBtn = alert.querySelector('.alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            });
        }
    });
}

/**
 * Initializes mobile menu toggle
 */
function initializeMobileMenu() {
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            this.classList.toggle('active');
            
            // Add overlay when menu is open
            if (sidebar.classList.contains('mobile-open')) {
                const overlay = document.createElement('div');
                overlay.classList.add('mobile-overlay');
                document.body.appendChild(overlay);
                
                // Close menu when overlay is clicked
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    menuToggle.classList.remove('active');
                    this.remove();
                });
            } else {
                const overlay = document.querySelector('.mobile-overlay');
                if (overlay) {
                    overlay.remove();
                }
            }
        });
    }
}

/**
 * Basic form validation
 */
function initializeFormValidation() {
    const forms = document.querySelectorAll('form.validated-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let hasError = false;
            
            // Check required fields
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    e.preventDefault();
                    field.classList.add('input-error');
                    hasError = true;
                    
                    // Add error message if not exists
                    if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('error-message')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.classList.add('error-message');
                        errorMsg.textContent = 'Ce champ est obligatoire';
                        field.parentNode.insertBefore(errorMsg, field.nextSibling);
                    }
                } else {
                    field.classList.remove('input-error');
                    if (field.nextElementSibling && field.nextElementSibling.classList.contains('error-message')) {
                        field.nextElementSibling.remove();
                    }
                }
            });
            
            return !hasError;
        });
    });
}

/**
 * Simple tooltip functionality
 */
function initTooltips() {
  const tooltipElements = document.querySelectorAll('[data-tooltip]');
  
  tooltipElements.forEach(element => {
    element.addEventListener('mouseenter', function() {
      const tooltipText = this.getAttribute('data-tooltip');
      
      const tooltip = document.createElement('div');
      tooltip.className = 'tooltip';
      tooltip.textContent = tooltipText;
      
      document.body.appendChild(tooltip);
      
      // Position the tooltip
      const rect = this.getBoundingClientRect();
      tooltip.style.top = `${rect.top - tooltip.offsetHeight - 5}px`;
      tooltip.style.left = `${rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)}px`;
      
      // Add visible class for animation
      setTimeout(() => {
        tooltip.classList.add('visible');
      }, 10);
    });
    
    element.addEventListener('mouseleave', function() {
      const tooltip = document.querySelector('.tooltip');
      if (tooltip) {
        tooltip.classList.remove('visible');
        setTimeout(() => {
          tooltip.remove();
        }, 200);
      }
    });
  });
}

/**
 * Allow dismissing of alert messages
 */
function initAlertDismiss() {
  const alerts = document.querySelectorAll('.alert');
  
  alerts.forEach(alert => {
    // Only add dismiss button if it doesn't already have one
    if (!alert.querySelector('.alert-dismiss')) {
      const dismissButton = document.createElement('button');
      dismissButton.className = 'alert-dismiss';
      dismissButton.innerHTML = '&times;';
      dismissButton.setAttribute('aria-label', 'Fermer');
      alert.appendChild(dismissButton);
      
      dismissButton.addEventListener('click', function() {
        alert.classList.add('alert-dismissing');
        setTimeout(() => {
          alert.remove();
        }, 300);
      });
    }
    
    // Auto dismiss non-error alerts after 5 seconds
    if (!alert.classList.contains('alert-error') && !alert.classList.contains('alert-warning')) {
      setTimeout(() => {
        alert.classList.add('alert-dismissing');
        setTimeout(() => {
          alert.remove();
        }, 300);
      }, 5000);
    }
  });
}

/**
 * Creates and displays a toast notification
 * @param {string} message - Message to display
 * @param {string} type - Success, error, warning, or info
 * @param {number} duration - Time in milliseconds before dismissal (default 3000)
 */
function showToast(message, type = 'info', duration = 3000) {
    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.classList.add('toast-container');
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.classList.add('toast', `toast-${type}`);
    
    // Add icon based on type
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="toast-content">${message}</div>
        <button class="toast-close">×</button>
    `;
    
    // Add to container
    toastContainer.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Set up auto dismiss
    const dismissTimeout = setTimeout(() => {
        dismissToast(toast);
    }, duration);
    
    // Setup close button
    const closeButton = toast.querySelector('.toast-close');
    closeButton.addEventListener('click', () => {
        clearTimeout(dismissTimeout);
        dismissToast(toast);
    });
}

/**
 * Dismisses a toast with animation
 * @param {HTMLElement} toast - The toast element to dismiss
 */
function dismissToast(toast) {
    toast.classList.add('hiding');
    setTimeout(() => {
        toast.remove();
        
        // Remove container if empty
        const toastContainer = document.querySelector('.toast-container');
        if (toastContainer && toastContainer.children.length === 0) {
            toastContainer.remove();
        }
    }, 300);
}

/**
 * Confirms an action with a custom dialog
 * @param {string} message - Confirmation message
 * @param {Function} confirmCallback - Function to call if confirmed
 * @param {Function} cancelCallback - Function to call if canceled
 */
function confirmAction(message, confirmCallback, cancelCallback = null) {
    // Create modal backdrop
    const backdrop = document.createElement('div');
    backdrop.classList.add('modal-backdrop');
    document.body.appendChild(backdrop);
    
    // Create modal dialog
    const modal = document.createElement('div');
    modal.classList.add('modal-dialog');
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirmation</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p>${message}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-cancel">Annuler</button>
                <button type="button" class="btn btn-primary modal-confirm">Confirmer</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Add animation
    setTimeout(() => {
        backdrop.classList.add('show');
        modal.classList.add('show');
    }, 10);
    
    // Close function
    const closeModal = () => {
        backdrop.classList.remove('show');
        modal.classList.remove('show');
        setTimeout(() => {
            backdrop.remove();
            modal.remove();
        }, 300);
    };
    
    // Event listeners
    modal.querySelector('.modal-close').addEventListener('click', () => {
        closeModal();
        if (cancelCallback) cancelCallback();
    });
    
    modal.querySelector('.modal-cancel').addEventListener('click', () => {
        closeModal();
        if (cancelCallback) cancelCallback();
    });
    
    modal.querySelector('.modal-confirm').addEventListener('click', () => {
        closeModal();
        confirmCallback();
    });
    
    backdrop.addEventListener('click', () => {
        closeModal();
        if (cancelCallback) cancelCallback();
    });
}

// Initialize modal close buttons
document.addEventListener('click', function(event) {
  if (event.target.classList.contains('modal-close') || 
      event.target.classList.contains('modal-cancel')) {
    
    const modal = event.target.closest('.modal');
    if (modal) {
      modal.classList.remove('modal-visible');
      
      setTimeout(() => {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
      }, 300);
    }
  }
  
  // Close if clicking outside modal content
  if (event.target.classList.contains('modal')) {
    event.target.classList.remove('modal-visible');
    
    setTimeout(() => {
      event.target.style.display = 'none';
      document.body.classList.remove('modal-open');
    }, 300);
  }
});
