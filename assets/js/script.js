/**
 * Main JavaScript file for the Movie Booking System
 */

document.addEventListener('DOMContentLoaded', function() {
    
    /**
     * Seat Selection Functionality
     */
    const initializeSeatSelection = () => {
        const availableSeats = document.querySelectorAll('.seat.available');
        const bookedSeats = document.querySelectorAll('.seat.booked');
        const selectedSeatsText = document.getElementById('selectedSeatsText');
        const seatCount = document.getElementById('seatCount');
        const totalAmount = document.getElementById('totalAmount');
        const proceedBtn = document.getElementById('proceedBtn');
        
        if (!availableSeats.length && !bookedSeats.length) return; // If no seats found, exit
        
        // Get ticket price from data attribute or page
        const pricePerSeat = parseFloat(document.querySelector('input[name="price"]').value) || 0;
        
        // Initialize tooltips
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Add click event listeners to available seats
        availableSeats.forEach(seat => {
            seat.addEventListener('click', function(e) {
                // Don't react if the click was directly on the checkbox
                if (e.target.tagName === 'INPUT') {
                    return;
                }
                
                const checkbox = this.querySelector('input[type="checkbox"]');
                
                if (!checkbox.disabled) {
                    // Toggle checked state
                    checkbox.checked = !checkbox.checked;
                    
                    // Toggle selected class
                    this.classList.toggle('selected', checkbox.checked);
                    
                    // Trigger change event to ensure proper event handling
                    const event = new Event('change');
                    checkbox.dispatchEvent(event);
                    
                    // Update summary
                    updateBookingSummary();
                }
            });
        });
        
        // Add click event for booked seats to show feedback
        bookedSeats.forEach(seat => {
            seat.addEventListener('click', function(e) {
                // Visual feedback that the seat is already booked
                const originalColor = this.style.backgroundColor;
                this.style.backgroundColor = '#ff6666'; // bright red flash
                
                setTimeout(() => {
                    this.style.backgroundColor = originalColor;
                }, 300);
                
                // Show tooltip programmatically if not already visible
                if (typeof bootstrap !== 'undefined') {
                    const tooltip = bootstrap.Tooltip.getInstance(this);
                    if (tooltip) {
                        tooltip.show();
                        
                        // Auto-hide after a delay
                        setTimeout(() => {
                            tooltip.hide();
                        }, 1500);
                    }
                }
            });
        });
        
        // Add event listeners to checkboxes to handle direct interaction
        document.querySelectorAll('.seat-checkbox:not([disabled])').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const seat = this.closest('.seat');
                seat.classList.toggle('selected', this.checked);
                updateBookingSummary();
            });
        });
        
        // Update booking summary when seats are selected
        function updateBookingSummary() {
            const selectedSeats = document.querySelectorAll('.seat input[type="checkbox"]:checked');
            const selectedSeatsArray = Array.from(selectedSeats).map(checkbox => {
                const seat = checkbox.closest('.seat');
                const row = seat.dataset.row;
                const col = seat.dataset.col;
                return String.fromCharCode(64 + parseInt(row)) + col;
            });
            
            // Update selected seats text
            selectedSeatsText.textContent = selectedSeatsArray.length ? selectedSeatsArray.join(', ') : 'None';
            
            // Update seat count
            seatCount.textContent = selectedSeatsArray.length;
            
            // Update total amount
            const total = selectedSeatsArray.length * pricePerSeat;
            totalAmount.textContent = '₹' + total.toFixed(2);
            
            // Enable/disable proceed button
            proceedBtn.disabled = selectedSeatsArray.length === 0;
        }
    };
    
    /**
     * Password Strength Meter
     */
    const initializePasswordStrengthMeter = () => {
        const passwordInput = document.getElementById('new_password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthMeter = document.getElementById('password-strength-meter');
        const strengthText = document.getElementById('password-strength-text');
        
        if (!passwordInput || !strengthMeter) return;
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Calculate password strength
            if (password.length >= 8) strength += 20;
            if (password.match(/[a-z]+/)) strength += 20;
            if (password.match(/[A-Z]+/)) strength += 20;
            if (password.match(/[0-9]+/)) strength += 20;
            if (password.match(/[^a-zA-Z0-9]+/)) strength += 20;
            
            // Update strength meter
            strengthMeter.style.width = strength + '%';
            
            // Set color class and text based on strength
            if (strength <= 20) {
                strengthMeter.className = 'progress-bar bg-danger';
                strengthText.innerHTML = 'Very Weak';
            } else if (strength <= 40) {
                strengthMeter.className = 'progress-bar bg-warning';
                strengthText.innerHTML = 'Weak';
            } else if (strength <= 60) {
                strengthMeter.className = 'progress-bar bg-info';
                strengthText.innerHTML = 'Medium';
            } else if (strength <= 80) {
                strengthMeter.className = 'progress-bar bg-primary';
                strengthText.innerHTML = 'Strong';
            } else {
                strengthMeter.className = 'progress-bar bg-success';
                strengthText.innerHTML = 'Very Strong';
            }
        });
        
        // Check if passwords match
        if (confirmInput) {
            confirmInput.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    };
    
    /**
     * Initialize DataTables for admin tables
     */
    const initializeDataTables = () => {
        if (typeof $.fn.DataTable !== 'undefined' && $('.datatable').length > 0) {
            $('.datatable').DataTable({
                responsive: true,
                "pageLength": 25,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });
        }
    };
    
    /**
     * Initialize Tooltips
     */
    const initializeTooltips = () => {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    };
    
    /**
     * Image Preview for File Uploads
     */
    const initializeImagePreview = () => {
        const fileInput = document.getElementById('poster');
        const previewImage = document.getElementById('poster-preview');
        
        if (!fileInput || !previewImage) return;
        
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                };
                
                reader.readAsDataURL(file);
            }
        });
    };
    
    /**
     * Show/Hide elements based on selections
     */
    const initializeConditionalDisplay = () => {
        // Example: Show different fields based on payment method selection
        const paymentMethodSelect = document.getElementById('payment_method');
        const creditCardFields = document.getElementById('credit_card_fields');
        
        if (paymentMethodSelect && creditCardFields) {
            paymentMethodSelect.addEventListener('change', function() {
                if (this.value === 'credit_card') {
                    creditCardFields.style.display = 'block';
                } else {
                    creditCardFields.style.display = 'none';
                }
            });
        }
    };
    
    /**
     * Auto-dismiss alerts after a delay
     */
    const initializeAutoDismissAlerts = () => {
        const alerts = document.querySelectorAll('.alert-auto-dismiss');
        
        alerts.forEach(alert => {
            setTimeout(() => {
                // Check if the alert has Bootstrap dismiss functionality
                const closeBtn = alert.querySelector('.btn-close');
                
                if (closeBtn) {
                    closeBtn.click();
                } else {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }
            }, 5000); // Dismiss after 5 seconds
        });
    };
    
    /**
     * Show confirmation dialog before dangerous actions
     */
    const initializeConfirmationDialogs = () => {
        const confirmForms = document.querySelectorAll('form[data-confirm]');
        
        confirmForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const message = this.dataset.confirm || 'Are you sure you want to perform this action?';
                
                if (confirm(message)) {
                    this.submit();
                }
            });
        });
    };
    
    /**
     * Validate seat selection form
     */
    function validateSeatSelection() {
        const selectedSeats = document.querySelectorAll('.seat input[type="checkbox"]:checked');
        const errorElement = document.getElementById('seatValidationError');
        
        if (selectedSeats.length === 0) {
            if (errorElement) {
                errorElement.classList.remove('d-none');
                // Scroll to error message
                errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Reset after a short delay
                setTimeout(() => {
                    errorElement.classList.add('d-none');
                }, 3000);
            }
            console.log("No seats selected");
            return false;
        }
        
        // Log for debugging
        console.log(`${selectedSeats.length} seats selected, submitting form`);
        return true;
    }
    
    // Make function globally available
    window.validateSeatSelection = validateSeatSelection;
    
    // Initialize form submission handler
    const seatSelectionForm = document.getElementById('seatSelectionForm');
    if (seatSelectionForm) {
        seatSelectionForm.addEventListener('submit', function(e) {
            // Prevent default form submission
            e.preventDefault();
            
            // Validate seats are selected
            if (validateSeatSelection()) {
                // If validation passes, submit the form
                this.submit();
            }
        });
    }
    
    // Call the initialization functions
    initializeSeatSelection();
    initializePasswordStrengthMeter();
    initializeDataTables();
    initializeTooltips();
    initializeImagePreview();
    initializeConditionalDisplay();
    initializeAutoDismissAlerts();
    initializeConfirmationDialogs();
    
    // Show "back to top" button when scrolled down
    window.addEventListener('scroll', function() {
        const backToTopBtn = document.getElementById('back-to-top');
        
        if (backToTopBtn) {
            if (window.pageYOffset > 300) {
                backToTopBtn.style.display = 'block';
            } else {
                backToTopBtn.style.display = 'none';
            }
        }
    });
});
