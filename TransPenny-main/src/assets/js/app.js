/**
 * TransPenny - Main Application JavaScript
 * Handles AJAX requests for user management
 */

// DOM Elements
const userForm = document.getElementById('userForm');
const formMessage = document.getElementById('formMessage');
const loadUsersBtn = document.getElementById('loadUsers');
const usersList = document.getElementById('usersList');

// API Endpoints
const API_BASE = '/api';
const ENDPOINTS = {
    addUser: `${API_BASE}/add_user.php`,
    getUsers: `${API_BASE}/get_users.php`
};

/**
 * Display message to user
 * @param {string} message - Message text
 * @param {string} type - Message type (success/error)
 */
function showMessage(message, type = 'success') {
    formMessage.textContent = message;
    formMessage.className = `message ${type}`;
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        formMessage.style.display = 'none';
    }, 5000);
}

/**
 * Make AJAX request
 * @param {string} url - Request URL
 * @param {string} method - HTTP method
 * @param {Object|null} data - Request data
 * @returns {Promise} Response promise
 */
function makeRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    reject(new Error('Invalid JSON response'));
                }
            } else {
                reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error occurred'));
        };
        
        xhr.ontimeout = function() {
            reject(new Error('Request timeout'));
        };
        
        xhr.timeout = 10000; // 10 second timeout
        
        if (data) {
            xhr.send(JSON.stringify(data));
        } else {
            xhr.send();
        }
    });
}

/**
 * Handle user form submission
 */
userForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        name: document.getElementById('name').value.trim(),
        email: document.getElementById('email').value.trim()
    };
    
    // Basic validation
    if (!formData.name || !formData.email) {
        showMessage('Please fill in all fields', 'error');
        return;
    }
    
    try {
        const response = await makeRequest(ENDPOINTS.addUser, 'POST', formData);
        
        if (response.success) {
            showMessage(response.message, 'success');
            userForm.reset();
            // Automatically reload users list
            loadUsers();
        } else {
            showMessage(response.message || 'Failed to add user', 'error');
        }
    } catch (error) {
        showMessage(`Error: ${error.message}`, 'error');
        console.error('Add user error:', error);
    }
});

/**
 * Load and display users
 */
async function loadUsers() {
    usersList.innerHTML = '<div class="loading">Loading users...</div>';
    
    try {
        const response = await makeRequest(ENDPOINTS.getUsers, 'GET');
        
        if (response.success) {
            displayUsers(response.data);
        } else {
            usersList.innerHTML = `<p class="error">${response.message}</p>`;
        }
    } catch (error) {
        usersList.innerHTML = `<p class="error">Error loading users: ${error.message}</p>`;
        console.error('Load users error:', error);
    }
}

/**
 * Display users in the DOM
 * @param {Array} users - Array of user objects
 */
function displayUsers(users) {
    if (!users || users.length === 0) {
        usersList.innerHTML = '<p>No users found. Add some users to get started!</p>';
        return;
    }
    
    const usersHTML = users.map(user => `
        <div class="user-card">
            <h3>${escapeHtml(user.name)}</h3>
            <p><strong>Email:</strong> ${escapeHtml(user.email)}</p>
            <small><strong>ID:</strong> ${user.id} | <strong>Joined:</strong> ${formatDate(user.created_at)}</small>
        </div>
    `).join('');
    
    usersList.innerHTML = usersHTML;
}

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format date string
 * @param {string} dateString - Date string
 * @returns {string} Formatted date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Handle user logout
 */
function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('/api/logout.php').then(() => {
            window.location.href = '/index.html';
        }).catch(error => {
            console.error('Logout error:', error);
            alert('Error logging out. Please try again.');
        });
    }
}

// Event Listeners
if (loadUsersBtn) {
    loadUsersBtn.addEventListener('click', loadUsers);
}

// Load users on page load
document.addEventListener('DOMContentLoaded', function() {
    if (loadUsersBtn) {
        loadUsers();
    }
});
