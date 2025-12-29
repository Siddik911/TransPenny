/**
 * TransPenny - Donor Registration Application
 * Handles donor registration and display
 */

// DOM Elements
const donorForm = document.getElementById('donorForm');
const formMessage = document.getElementById('formMessage');
const loadDonorsBtn = document.getElementById('loadDonors');
const donorsList = document.getElementById('donorsList');

// API Endpoints
const API_BASE = 'api';
const ENDPOINTS = {
    signUpDonor: `${API_BASE}/SignUpAsDonor.php`,
    getDonors: `${API_BASE}/GetDonors.php`
};

/**
 * Display message to user
 * @param {string} message - Message text
 * @param {string} type - Message type (success/error/info)
 */
function showMessage(message, type = 'success') {
    formMessage.textContent = message;
    formMessage.className = `message ${type}`;
    formMessage.style.display = 'block';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        formMessage.style.display = 'none';
    }, 5000);
}

/**
 * Make AJAX request using fetch API
 * @param {string} url - Request URL
 * @param {string} method - HTTP method
 * @param {Object|null} data - Request data
 * @returns {Promise} Response promise
 */
async function makeRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Request failed');
        }
        
        return result;
    } catch (error) {
        throw error;
    }
}

/**
 * Validate password match
 */
function validatePasswords() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (password !== confirmPassword) {
        showMessage('Passwords do not match!', 'error');
        return false;
    }
    return true;
}

/**
 * Handle donor registration form submission
 */
async function handleDonorRegistration(event) {
    event.preventDefault();
    
    // Validate passwords
    if (!validatePasswords()) {
        return;
    }
    
    // Get form data
    const formData = {
        name: document.getElementById('name').value.trim(),
        email: document.getElementById('email').value.trim(),
        phoneNumber: document.getElementById('phoneNumber').value.trim(),
        password: document.getElementById('password').value,
        isAnonymous: document.getElementById('isAnonymous').checked
    };
    
    try {
        showMessage('Registering...', 'info');
        
        const response = await makeRequest(ENDPOINTS.signUpDonor, 'POST', formData);
        
        if (response.success) {
            showMessage('Registration successful! Welcome ' + formData.name, 'success');
            donorForm.reset();
            
            // Auto-load donors list after successful registration
            setTimeout(() => {
                loadDonors();
            }, 1000);
        }
    } catch (error) {
        showMessage(error.message || 'Registration failed. Please try again.', 'error');
    }
}

/**
 * Load and display donors list
 */
async function loadDonors() {
    try {
        donorsList.innerHTML = '<p class="loading">Loading donors...</p>';
        
        const response = await makeRequest(ENDPOINTS.getDonors, 'GET');
        
        if (response.success && response.data) {
            displayDonors(response.data);
        }
    } catch (error) {
        donorsList.innerHTML = `<p class="error">Failed to load donors: ${error.message}</p>`;
    }
}

/**
 * Display donors in a table
 * @param {Array} donors - Array of donor objects
 */
function displayDonors(donors) {
    if (!donors || donors.length === 0) {
        donorsList.innerHTML = '<p class="no-data">No donors registered yet.</p>';
        return;
    }
    
    const table = `
        <table class="donors-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Total Donated</th>
                    <th>Donations</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
                ${donors.map((donor, index) => `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${donor.name}${donor.isAnonymous ? ' <span class="badge">Anonymous</span>' : ''}</td>
                        <td>${donor.email}</td>
                        <td>${donor.phoneNumber}</td>
                        <td class="amount">$${donor.totalDonated}</td>
                        <td>${donor.donationCount}</td>
                        <td>${new Date(donor.registeredAt).toLocaleDateString()}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    donorsList.innerHTML = table;
}

// Event Listeners
donorForm.addEventListener('submit', handleDonorRegistration);
loadDonorsBtn.addEventListener('click', loadDonors);

// Load donors on page load
document.addEventListener('DOMContentLoaded', () => {
    loadDonors();
});
