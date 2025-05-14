/**
 * Main JavaScript file for Airport Management System
 * Version: 1.0.0
 */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }
    
    // Initialize popovers
    if (typeof $.fn.popover !== 'undefined') {
        $('[data-toggle="popover"]').popover();
    }
    
    // Auto-hide flash messages after 5 seconds
    const flashMessages = document.querySelectorAll('.alert:not(.alert-persistent)');
    if (flashMessages.length > 0) {
        setTimeout(function() {
            flashMessages.forEach(function(element) {
                // Create a fadeout effect
                let opacity = 1;
                const timer = setInterval(function() {
                    if (opacity <= 0.1) {
                        clearInterval(timer);
                        element.style.display = 'none';
                    }
                    element.style.opacity = opacity;
                    opacity -= opacity * 0.1;
                }, 50);
            });
        }, 5000);
    }
    
    // Password strength meter
    const passwordInput = document.getElementById('password');
    const passwordStrength = document.getElementById('password-strength');
    
    if (passwordInput && passwordStrength) {
        passwordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const strength = checkPasswordStrength(password);
            
            // Update the strength meter
            passwordStrength.className = 'progress-bar';
            passwordStrength.style.width = strength.score * 25 + '%';
            passwordStrength.textContent = strength.message;
            
            // Add appropriate class based on strength
            if (strength.score <= 1) {
                passwordStrength.classList.add('bg-danger');
            } else if (strength.score === 2) {
                passwordStrength.classList.add('bg-warning');
            } else if (strength.score === 3) {
                passwordStrength.classList.add('bg-info');
            } else {
                passwordStrength.classList.add('bg-success');
            }
        });
    }
    
    // Flight search form handling
    const flightSearchForm = document.getElementById('flight-search-form');
    
    if (flightSearchForm) {
        flightSearchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const departure = document.getElementById('departure').value;
            const arrival = document.getElementById('arrival').value;
            const date = document.getElementById('date').value;
            
            // Simple validation
            if (!departure || !arrival || !date) {
                showAlert('Please fill in all required fields.', 'danger');
                return;
            }
            
            if (departure === arrival) {
                showAlert('Departure and arrival airports cannot be the same.', 'danger');
                return;
            }
            
            // Display loading spinner
            const resultsContainer = document.getElementById('flight-search-results');
            resultsContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Searching for flights...</p></div>';
            
            // Simulate AJAX request with setTimeout
            setTimeout(function() {
                // In a real application, this would be an AJAX call to the server
                searchFlights(departure, arrival, date)
                    .then(function(response) {
                        displayFlightResults(response, resultsContainer);
                    })
                    .catch(function(error) {
                        showAlert('Error searching for flights: ' + error.message, 'danger');
                        resultsContainer.innerHTML = '';
                    });
            }, 1500);
        });
    }
    
    // Baggage tracking form handling
    const baggageTrackingForm = document.getElementById('baggage-tracking-form');
    
    if (baggageTrackingForm) {
        baggageTrackingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const trackingNumber = document.getElementById('tracking-number').value;
            
            // Simple validation
            if (!trackingNumber) {
                showAlert('Please enter a tracking number.', 'danger');
                return;
            }
            
            // Display loading spinner
            const resultsContainer = document.getElementById('baggage-tracking-results');
            resultsContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Tracking baggage...</p></div>';
            
            // Simulate AJAX request with setTimeout
            setTimeout(function() {
                // In a real application, this would be an AJAX call to the server
                trackBaggage(trackingNumber)
                    .then(function(response) {
                        displayBaggageResults(response, resultsContainer);
                    })
                    .catch(function(error) {
                        showAlert('Error tracking baggage: ' + error.message, 'danger');
                        resultsContainer.innerHTML = '';
                    });
            }, 1500);
        });
    }
    
    // Initialize date pickers
    if (typeof $.fn.datepicker !== 'undefined') {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true,
            startDate: new Date()
        });
    }
    
    // Initialize select2 for searchable dropdowns
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%'
        });
    }
    
    // AJAX form submissions
    const ajaxForms = document.querySelectorAll('.ajax-form');
    
    ajaxForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const submitButton = form.querySelector('[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Disable the submit button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            // Get the form action URL
            const action = form.getAttribute('action');
            
            // Perform AJAX request
            fetch(action, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    showAlert(data.message, 'success');
                    
                    // If there's a redirect URL, redirect after a short delay
                    if (data.redirect) {
                        setTimeout(function() {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                    
                    // If the form should be reset on success
                    if (form.dataset.resetOnSuccess === 'true') {
                        form.reset();
                    }
                    
                    // If there's a callback function defined
                    if (typeof window[form.dataset.successCallback] === 'function') {
                        window[form.dataset.successCallback](data);
                    }
                } else {
                    showAlert(data.message || 'An error occurred. Please try again.', 'danger');
                }
            })
            .catch(function(error) {
                showAlert('An error occurred. Please try again.', 'danger');
                console.error('Form submission error:', error);
            })
            .finally(function() {
                // Re-enable the submit button and restore original text
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });
    });
    
    // Dynamic form field addition
    const addFieldButtons = document.querySelectorAll('.add-field-btn');
    
    addFieldButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const container = document.getElementById(button.dataset.container);
            const template = document.getElementById(button.dataset.template).innerHTML;
            const index = container.querySelectorAll('.dynamic-field').length;
            
            // Replace template placeholder with actual index
            const newField = template.replace(/\{index\}/g, index);
            
            // Create a new div element and set its innerHTML to the new field
            const fieldContainer = document.createElement('div');
            fieldContainer.className = 'dynamic-field';
            fieldContainer.innerHTML = newField;
            
            // Append the new field to the container
            container.appendChild(fieldContainer);
            
            // Initialize any plugins in the new field
            if (typeof $.fn.datepicker !== 'undefined') {
                $(fieldContainer).find('.datepicker').datepicker({
                    format: 'yyyy-mm-dd',
                    autoclose: true,
                    todayHighlight: true,
                    startDate: new Date()
                });
            }
            
            if (typeof $.fn.select2 !== 'undefined') {
                $(fieldContainer).find('.select2').select2({
                    theme: 'bootstrap4',
                    width: '100%'
                });
            }
            
            // Add event listener to remove button
            const removeButton = fieldContainer.querySelector('.remove-field-btn');
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    fieldContainer.remove();
                });
            }
        });
    });
});

/**
 * Helper function to display alert messages
 * @param {string} message - The message to display
 * @param {string} type - The type of alert (success, danger, warning, info)
 */
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alert-container');
    
    if (!alertContainer) {
        // Create an alert container if it doesn't exist
        const container = document.createElement('div');
        container.id = 'alert-container';
        container.className = 'alert-container';
        document.body.appendChild(container);
    }
    
    // Create the alert element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    
    // Add the alert to the container
    const container = document.getElementById('alert-container');
    container.appendChild(alert);
    
    // Auto-hide the alert after 5 seconds
    setTimeout(function() {
        $(alert).alert('close');
    }, 5000);
}

/**
 * Check password strength
 * @param {string} password - The password to check
 * @returns {Object} - Object with score (0-4) and message
 */
function checkPasswordStrength(password) {
    let score = 0;
    let message = '';
    
    // Check length
    if (password.length < 8) {
        return {
            score: 0,
            message: 'Too short'
        };
    } else {
        score += 1;
    }
    
    // Check for lowercase letters
    if (/[a-z]/.test(password)) {
        score += 1;
    }
    
    // Check for uppercase letters
    if (/[A-Z]/.test(password)) {
        score += 1;
    }
    
    // Check for numbers
    if (/\d/.test(password)) {
        score += 1;
    }
    
    // Check for special characters
    if (/[^a-zA-Z0-9]/.test(password)) {
        score += 1;
    }
    
    // Determine message based on score
    switch (score) {
        case 1:
            message = 'Weak';
            break;
        case 2:
            message = 'Fair';
            break;
        case 3:
            message = 'Good';
            break;
        case 4:
            message = 'Strong';
            break;
        case 5:
            message = 'Very Strong';
            break;
        default:
            message = 'Very Weak';
    }
    
    return {
        score: Math.min(score, 4), // Cap score at 4 for progress bar
        message: message
    };
}

/**
 * Simulate a flight search AJAX request
 * @param {string} departure - Departure airport code
 * @param {string} arrival - Arrival airport code
 * @param {string} date - Departure date
 * @returns {Promise} - Promise that resolves with flight search results
 */
function searchFlights(departure, arrival, date) {
    return new Promise(function(resolve, reject) {
        // In a real application, this would be an actual AJAX call
        // For demonstration, we'll return some mock data
        
        // Simulate some network delay
        setTimeout(function() {
            // Sample flight data
            const flights = [
                {
                    id: 'FL123',
                    airline: 'SkyWings Airlines',
                    departure: {
                        airport: departure,
                        city: getAirportCity(departure),
                        time: '08:30',
                        date: date
                    },
                    arrival: {
                        airport: arrival,
                        city: getAirportCity(arrival),
                        time: '10:45',
                        date: date
                    },
                    duration: '2h 15m',
                    price: 249.99,
                    available_seats: 45,
                    aircraft: 'Boeing 737-800',
                    status: 'On Schedule'
                },
                {
                    id: 'FL456',
                    airline: 'Global Airways',
                    departure: {
                        airport: departure,
                        city: getAirportCity(departure),
                        time: '12:15',
                        date: date
                    },
                    arrival: {
                        airport: arrival,
                        city: getAirportCity(arrival),
                        time: '14:30',
                        date: date
                    },
                    duration: '2h 15m',
                    price: 199.99,
                    available_seats: 12,
                    aircraft: 'Airbus A320',
                    status: 'On Schedule'
                },
                {
                    id: 'FL789',
                    airline: 'Eagle Express',
                    departure: {
                        airport: departure,
                        city: getAirportCity(departure),
                        time: '16:45',
                        date: date
                    },
                    arrival: {
                        airport: arrival,
                        city: getAirportCity(arrival),
                        time: '19:00',
                        date: date
                    },
                    duration: '2h 15m',
                    price: 299.99,
                    available_seats: 28,
                    aircraft: 'Boeing 787 Dreamliner',
                    status: 'On Schedule'
                }
            ];
            
            resolve({
                success: true,
                total: flights.length,
                flights: flights
            });
        }, 1000);
    });
}

/**
 * Helper function to get city name from airport code
 * @param {string} code - Airport code
 * @returns {string} - City name
 */
function getAirportCity(code) {
    const airports = {
        'JFK': 'New York',
        'LAX': 'Los Angeles',
        'ORD': 'Chicago',
        'LHR': 'London',
        'CDG': 'Paris',
        'DXB': 'Dubai',
        'HKG': 'Hong Kong',
        'SYD': 'Sydney',
        'SIN': 'Singapore',
        'DEL': 'New Delhi',
        'DAC': 'Dhaka'
    };
    
    return airports[code] || code;
}

/**
 * Display flight search results
 * @param {Object} response - Flight search response
 * @param {HTMLElement} container - Container element to display results
 */
function displayFlightResults(response, container) {
    if (response.success && response.flights.length > 0) {
        let html = `
            <div class="mb-3">
                <h3>Found ${response.total} Flights</h3>
            </div>
            <div class="flight-info-container">
        `;
        
        response.flights.forEach(function(flight) {
            html += `
                <div class="flight-card">
                    <div class="flight-card-header">
                        <div>
                            <h4>${flight.airline}</h4>
                            <span>Flight ${flight.id}</span>
                        </div>
                        <div>
                            <span class="status-badge badge-success">${flight.status}</span>
                        </div>
                    </div>
                    <div class="flight-card-body">
                        <div class="flight-route">
                            <div class="flight-city">
                                <div class="flight-city-code">${flight.departure.airport}</div>
                                <div class="flight-city-name">${flight.departure.city}</div>
                            </div>
                            <div class="flight-route-line">
                                <i class="fas fa-plane"></i>
                            </div>
                            <div class="flight-city">
                                <div class="flight-city-code">${flight.arrival.airport}</div>
                                <div class="flight-city-name">${flight.arrival.city}</div>
                            </div>
                        </div>
                        <div class="flight-time-info">
                            <div class="flight-time">
                                <div class="flight-time-value">${flight.departure.time}</div>
                                <div class="flight-time-label">Departure</div>
                            </div>
                            <div class="flight-duration">
                                <div class="flight-duration-value">${flight.duration}</div>
                                <div class="flight-duration-label">Duration</div>
                            </div>
                            <div class="flight-time">
                                <div class="flight-time-value">${flight.arrival.time}</div>
                                <div class="flight-time-label">Arrival</div>
                            </div>
                        </div>
                        <div class="flight-details">
                            <div>
                                <strong>Aircraft:</strong> ${flight.aircraft}
                            </div>
                            <div>
                                <strong>Available Seats:</strong> ${flight.available_seats}
                            </div>
                        </div>
                    </div>
                    <div class="flight-card-footer">
                        <div class="flight-price">${flight.price.toFixed(2)}</div>
                        <button class="btn btn-primary book-flight-btn" data-flight-id="${flight.id}">Book Now</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
        // Add event listeners to book buttons
        const bookButtons = container.querySelectorAll('.book-flight-btn');
        bookButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const flightId = button.dataset.flightId;
                window.location.href = 'purchase_tickets.php?flight_id=' + flightId;
            });
        });
    } else {
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No flights found for the selected criteria. Please try different dates or destinations.
            </div>
        `;
    }
}

/**
 * Simulate a baggage tracking AJAX request
 * @param {string} trackingNumber - Baggage tracking number
 * @returns {Promise} - Promise that resolves with baggage tracking results
 */
function trackBaggage(trackingNumber) {
    return new Promise(function(resolve, reject) {
        // In a real application, this would be an actual AJAX call
        // For demonstration, we'll return some mock data
        
        // Simulate some network delay
        setTimeout(function() {
            // For demonstration, we'll return different results based on the first character
            const firstChar = trackingNumber.charAt(0).toUpperCase();
            
            let data;
            
            switch (firstChar) {
                case 'A':
                    // Baggage in transit
                    data = {
                        success: true,
                        baggage: {
                            tracking_number: trackingNumber,
                            passenger_name: 'John Smith',
                            flight: 'FL123',
                            origin: 'JFK',
                            destination: 'LAX',
                            status: 'In Transit',
                            steps: [
                                {
                                    id: 1,
                                    title: 'Check-in',
                                    location: 'JFK Terminal 4',
                                    timestamp: '2023-05-05 08:15:22',
                                    status: 'completed'
                                },
                                {
                                    id: 2,
                                    title: 'Security Screening',
                                    location: 'JFK Terminal 4',
                                    timestamp: '2023-05-05 08:32:45',
                                    status: 'completed'
                                },
                                {
                                    id: 3,
                                    title: 'Loading onto Aircraft',
                                    location: 'JFK Terminal 4, Gate B12',
                                    timestamp: '2023-05-05 09:05:10',
                                    status: 'active'
                                },
                                {
                                    id: 4,
                                    title: 'In-flight',
                                    location: 'Flight FL123',
                                    timestamp: null,
                                    status: 'pending'
                                },
                                {
                                    id: 5,
                                    title: 'Unloading from Aircraft',
                                    location: 'LAX Terminal 5',
                                    timestamp: null,
                                    status: 'pending'
                                },
                                {
                                    id: 6,
                                    title: 'Available for Pickup',
                                    location: 'LAX Terminal 5, Carousel 3',
                                    timestamp: null,
                                    status: 'pending'
                                }
                            ]
                        }
                    };
                    break;
                    
                case 'B':
                    // Baggage at destination
                    data = {
                        success: true,
                        baggage: {
                            tracking_number: trackingNumber,
                            passenger_name: 'Jane Doe',
                            flight: 'FL456',
                            origin: 'CDG',
                            destination: 'JFK',
                            status: 'Arrived',
                            steps: [
                                {
                                    id: 1,
                                    title: 'Check-in',
                                    location: 'CDG Terminal 2E',
                                    timestamp: '2023-05-04 14:22:10',
                                    status: 'completed'
                                },
                                {
                                    id: 2,
                                    title: 'Security Screening',
                                    location: 'CDG Terminal 2E',
                                    timestamp: '2023-05-04 14:45:32',
                                    status: 'completed'
                                },
                                {
                                    id: 3,
                                    title: 'Loading onto Aircraft',
                                    location: 'CDG Terminal 2E, Gate 42',
                                    timestamp: '2023-05-04 15:30:15',
                                    status: 'completed'
                                },
                                {
                                    id: 4,
                                    title: 'In-flight',
                                    location: 'Flight FL456',
                                    timestamp: '2023-05-04 16:05:00',
                                    status: 'completed'
                                },
                                {
                                    id: 5,
                                    title: 'Unloading from Aircraft',
                                    location: 'JFK Terminal 4',
                                    timestamp: '2023-05-05 01:40:22',
                                    status: 'completed'
                                },
                                {
                                    id: 6,
                                    title: 'Available for Pickup',
                                    location: 'JFK Terminal 4, Carousel 8',
                                    timestamp: '2023-05-05 02:05:47',
                                    status: 'active'
                                }
                            ]
                        }
                    };
                    break;
                    
                case 'C':
                    // Baggage delayed
                    data = {
                        success: true,
                        baggage: {
                            tracking_number: trackingNumber,
                            passenger_name: 'Michael Brown',
                            flight: 'FL789',
                            origin: 'LHR',
                            destination: 'SIN',
                            status: 'Delayed',
                            delay_reason: 'Weather conditions at origin',
                            steps: [
                                {
                                    id: 1,
                                    title: 'Check-in',
                                    location: 'LHR Terminal 5',
                                    timestamp: '2023-05-04 20:15:33',
                                    status: 'completed'
                                },
                                {
                                    id: 2,
                                    title: 'Security Screening',
                                    location: 'LHR Terminal 5',
                                    timestamp: '2023-05-04 20:35:12',
                                    status: 'completed'
                                },
                                {
                                    id: 3,
                                    title: 'Delayed',
                                    location: 'LHR Terminal 5',
                                    timestamp: '2023-05-04 21:22:45',
                                    status: 'active',
                                    note: 'Flight delayed due to weather conditions. Baggage will remain secured until flight departure.'
                                },
                                {
                                    id: 4,
                                    title: 'Loading onto Aircraft',
                                    location: 'LHR Terminal 5',
                                    timestamp: null,
                                    status: 'pending'
                                },
                                {
                                    id: 5,
                                    title: 'In-flight',
                                    location: 'Flight FL789',
                                    timestamp: null,
                                    status: 'pending'
                                },
                                {
                                    id: 6,
                                    title: 'Unloading from Aircraft',
                                    location: 'SIN Terminal 3',
                                    timestamp: null,
                                    status: 'pending'
                                },
                                {
                                    id: 7,
                                    title: 'Available for Pickup',
                                    location: 'SIN Terminal 3, Carousel 12',
                                    timestamp: null,
                                    status: 'pending'
                                }
                            ]
                        }
                    };
                    break;
                    
                default:
                    // Invalid tracking number
                    data = {
                        success: false,
                        message: 'Invalid tracking number. Please check and try again.'
                    };
            }
            
            resolve(data);
        }, 1000);
    });
}

/**
 * Display baggage tracking results
 * @param {Object} response - Baggage tracking response
 * @param {HTMLElement} container - Container element to display results
 */
function displayBaggageResults(response, container) {
    if (response.success) {
        const baggage = response.baggage;
        
        let html = `
            <div class="card tracking-result">
                <div class="card-header">
                    <h3>Baggage Information</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Tracking Number:</strong> ${baggage.tracking_number}</p>
                            <p><strong>Passenger Name:</strong> ${baggage.passenger_name}</p>
                            <p><strong>Flight:</strong> ${baggage.flight}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Origin:</strong> ${baggage.origin}</p>
                            <p><strong>Destination:</strong> ${baggage.destination}</p>
                            <p>
                                <strong>Status:</strong> 
                                <span class="status-badge badge-${getStatusClass(baggage.status)}">${baggage.status}</span>
                            </p>
                        </div>
                    </div>
                    
                    ${baggage.delay_reason ? `<div class="alert alert-warning mb-4"><i class="fas fa-exclamation-triangle"></i> Delay Reason: ${baggage.delay_reason}</div>` : ''}
                    
                    <h4>Tracking Timeline</h4>
                    <div class="tracking-timeline">
        `;
        
        baggage.steps.forEach(function(step) {
            let stepClass = '';
            
            if (step.status === 'completed') {
                stepClass = 'completed';
            } else if (step.status === 'active') {
                stepClass = 'active';
            }
            
            html += `
                <div class="tracking-step ${stepClass}">
                    <div class="tracking-step-content">
                        <div class="tracking-step-title">${step.title}</div>
                        <div class="tracking-step-info">
                            <p>${step.location}</p>
                            ${step.timestamp ? `<p><i class="far fa-clock"></i> ${step.timestamp}</p>` : ''}
                            ${step.note ? `<p><i class="fas fa-info-circle"></i> ${step.note}</p>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    } else {
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> ${response.message}
            </div>
        `;
    }
}

/**
 * Helper function to get status badge class
 * @param {string} status - Status text
 * @returns {string} - Bootstrap class for badge
 */
function getStatusClass(status) {
    switch (status.toLowerCase()) {
        case 'on schedule':
        case 'arrived':
        case 'completed':
            return 'success';
        case 'delayed':
        case 'delayed':
            return 'warning';
        case 'cancelled':
            return 'danger';
        case 'in transit':
        case 'active':
            return 'info';
        default:
            return 'secondary';
    }
}