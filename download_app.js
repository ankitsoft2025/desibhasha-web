/**
 * Download App Page JavaScript
 * Handles form validation, submission, and API communication
 */

document.addEventListener('DOMContentLoaded', function () {
    initializeDownloadForm();
});

function initializeDownloadForm() {
    // Get refercode from URL parameters
    const refercode = getReferCodeFromURL();
    document.getElementById('refercode').value = refercode;

    // Form submission
    const downloadForm = document.getElementById('downloadForm');
    downloadForm.addEventListener('submit', handleFormSubmit);

    // Real-time validation
    document.getElementById('email').addEventListener('blur', validateEmail);
}

/**
 * Extract refercode from URL query parameters
 */
function getReferCodeFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('refercode') || '';
}

/**
 * Handle form submission
 */
async function handleFormSubmit(e) {
    e.preventDefault();

    // Clear previous messages
    clearMessages();

    // Validate email
    if (!validateEmail()) {
        return;
    }

    // Disable submit button and show loader
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending...';
    document.getElementById('loader').classList.remove('hidden');

    try {
        // Prepare form data
        const formData = new FormData();
        formData.append('email', document.getElementById('email').value.trim());
        formData.append('refercode', document.getElementById('refercode').value);

        // Send request to API
        const response = await fetch('download_app_api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (response.ok) {
            // Success
            showSuccessMessage("Go ahead and download the app! We’ve emailed you the links for iOS and Android as well.");
        } else {
            // Error
            showErrorMessage(result.error || 'An error occurred. Please try again.');
        }
    } catch (error) {
        console.error('Error:', error);
        showErrorMessage('Network error. Please check your connection and try again.');
    } finally {
        // Re-enable submit button and hide loader
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Download Links';
        document.getElementById('loader').classList.add('hidden');
    }
}

/**
 * Validate email field
 */
function validateEmail() {
    const email = document.getElementById('email').value.trim();
    const emailError = document.getElementById('emailError');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!email) {
        emailError.textContent = 'Email address is required';
        return false;
    }

    if (!emailRegex.test(email)) {
        emailError.textContent = 'Please enter a valid email address';
        return false;
    }

    emailError.textContent = '';
    return true;
}

/**
 * Clear all messages
 */
function clearMessages() {
    document.getElementById('successMessage').classList.add('hidden');
    document.getElementById('errorAlert').classList.add('hidden');
}

/**
 * Show success message
 */
function showSuccessMessage(message) {
    const successMessage = document.getElementById('successMessage');
    successMessage.querySelector('p').textContent = message;
    successMessage.classList.remove('hidden');
}

/**
 * Show error message
 */
function showErrorMessage(message) {
    const errorAlert = document.getElementById('errorAlert');
    document.getElementById('errorText').textContent = message;
    errorAlert.classList.remove('hidden');
}