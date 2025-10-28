// Add these Firebase configuration and phone verification functions
// Firebase configuration (replace with your actual config)
const firebaseConfig = {
    apiKey: "AIzaSyABJ9WfFaMJM5cwviMUY1AGwbCKB2-pnWA",
    authDomain: "obgecom.firebaseapp.com",
    projectId: "obgecom",
    storageBucket: "obgecom.firebasestorage.app",
    messagingSenderId: "119148075907",
    appId: "1:119148075907:web:ac2952fd22f0a118f074b6",
    measurementId: "G-4PRL16BGN7"
  };

// Initialize Firebase
firebase.initializeApp(firebaseConfig);

// Global variables for phone verification
let confirmationResult = null;
let verificationId = null;
let resendTimer = null;
let countdownTime = 60;
let isPhoneVerified = false;

// Phone verification functions
async function verifyPhoneNumber() {
    if (isLoading) return;
    
    // Validate personal information first
    const validationErrors = validatePersonalInfo();
    if (validationErrors.length > 0) {
        validationErrors.forEach(({ field, message }) => showError(field, message));
        return;
    }
    
    setLoadingState('verifyPhoneButton', true);
    
    try {
        const phoneNumber = '+212' + document.getElementById('signupPhone').value;
        
        // Show the phone number in verification step
        document.getElementById('verificationPhoneNumber').textContent = phoneNumber;
        
        // Initialize Firebase auth
        const auth = firebase.auth();
        
        // Set up reCAPTCHA verifier
        window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier('verifyPhoneButton', {
            'size': 'invisible',
            'callback': function(response) {
                // reCAPTCHA solved, allow signInWithPhoneNumber
                console.log('reCAPTCHA solved');
            }
        });
        
        // Send verification code
        confirmationResult = await auth.signInWithPhoneNumber(phoneNumber, window.recaptchaVerifier);
        
        // Switch to verification step
        switchVerificationStep('phoneVerificationStep');
        
        // Start countdown for resend
        startResendCountdown();
        
        showNotification('Verification code sent to your phone!', 'success');
        
    } catch (error) {
        console.error('Phone verification error:', error);
        
        let errorMessage = 'Failed to send verification code. ';
        
        switch (error.code) {
            case 'auth/invalid-phone-number':
                errorMessage += 'Invalid phone number format.';
                break;
            case 'auth/quota-exceeded':
                errorMessage += 'Too many attempts. Please try again later.';
                break;
            case 'auth/user-disabled':
                errorMessage += 'This phone number has been disabled.';
                break;
            default:
                errorMessage += 'Please check your phone number and try again.';
        }
        
        showNotification(errorMessage, 'error');
        
        // Reset reCAPTCHA if it exists
        if (window.recaptchaVerifier) {
            window.recaptchaVerifier.clear();
            window.recaptchaVerifier = null;
        }
    } finally {
        setLoadingState('verifyPhoneButton', false);
    }
}

async function verifyCode() {
    const code = getVerificationCode();
    
    if (code.length !== 6) {
        showVerificationError('Please enter the 6-digit code');
        return;
    }
    
    setLoadingState('completeSignupButton', true);
    hideVerificationError();
    
    try {
        // Verify the code
        const result = await confirmationResult.confirm(code);
        
        // Phone number verified successfully
        isPhoneVerified = true;
        showVerificationSuccess();
        
        // Enable complete signup button
        document.getElementById('completeSignupButton').disabled = false;
        
        // Show terms and conditions
        document.getElementById('termsSection').style.display = 'block';
        
        showNotification('Phone number verified successfully!', 'success');
        
    } catch (error) {
        console.error('Code verification error:', error);
        showVerificationError('Invalid verification code. Please try again.');
    } finally {
        setLoadingState('completeSignupButton', false);
    }
}

async function resendVerificationCode() {
    if (isLoading) return;
    
    setLoadingState('resendButton', true);
    
    try {
        // Resend verification code
        confirmationResult = await firebase.auth().signInWithPhoneNumber(
            '+212' + document.getElementById('signupPhone').value, 
            window.recaptchaVerifier
        );
        
        // Clear previous code inputs
        clearCodeInputs();
        
        // Restart countdown
        startResendCountdown();
        
        showNotification('Verification code resent!', 'success');
        
    } catch (error) {
        console.error('Resend error:', error);
        showNotification('Failed to resend code. Please try again.', 'error');
    } finally {
        setLoadingState('resendButton', false);
    }
}

function startResendCountdown() {
    const resendButton = document.getElementById('resendButton');
    const countdownElement = document.getElementById('countdown');
    const countdownTimer = document.getElementById('countdownTimer');
    
    resendButton.disabled = true;
    countdownElement.style.display = 'block';
    countdownTime = 60;
    
    resendTimer = setInterval(() => {
        countdownTime--;
        countdownTimer.textContent = countdownTime;
        
        if (countdownTime <= 0) {
            clearInterval(resendTimer);
            resendButton.disabled = false;
            countdownElement.style.display = 'none';
        }
    }, 1000);
}

function switchVerificationStep(step) {
    // Hide all verification steps
    document.querySelectorAll('.verification-step').forEach(step => {
        step.classList.remove('active');
    });
    
    // Show the target step
    document.getElementById(step).classList.add('active');
}

function backToPersonalInfo() {
    switchVerificationStep('personalInfoStep');
    hideVerificationError();
    hideVerificationSuccess();
}

// Code input handling functions
function moveToNext(input) {
    const value = input.value;
    const index = parseInt(input.getAttribute('data-index'));
    
    if (value && index < 5) {
        const nextInput = document.querySelector(`.code-input[data-index="${index + 1}"]`);
        if (nextInput) {
            nextInput.focus();
        }
    }
    
    // Update input styling
    input.classList.toggle('filled', value !== '');
    
    // Auto-verify when all digits are entered
    if (index === 5 && value) {
        const code = getVerificationCode();
        if (code.length === 6) {
            verifyCode();
        }
    }
}

function handleCodeInputKeydown(event, input) {
    const index = parseInt(input.getAttribute('data-index'));
    
    if (event.key === 'Backspace' && !input.value && index > 0) {
        const prevInput = document.querySelector(`.code-input[data-index="${index - 1}"]`);
        if (prevInput) {
            prevInput.focus();
        }
    }
}

function getVerificationCode() {
    let code = '';
    for (let i = 0; i < 6; i++) {
        const input = document.querySelector(`.code-input[data-index="${i}"]`);
        if (input) {
            code += input.value;
        }
    }
    return code;
}

function clearCodeInputs() {
    document.querySelectorAll('.code-input').forEach(input => {
        input.value = '';
        input.classList.remove('filled');
    });
    document.querySelector('.code-input[data-index="0"]').focus();
}

function showVerificationSuccess() {
    const messageElement = document.getElementById('verificationMessage');
    messageElement.style.display = 'block';
    
    // Hide error if shown
    hideVerificationError();
}

function hideVerificationSuccess() {
    document.getElementById('verificationMessage').style.display = 'none';
}

function showVerificationError(message) {
    const errorElement = document.getElementById('verificationError');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
}

function hideVerificationError() {
    document.getElementById('verificationError').style.display = 'none';
}

// Update the completeSignup function to require phone verification
async function completeSignup() {
    if (!isPhoneVerified) {
        showNotification('Please verify your phone number first', 'error');
        return;
    }
    
    // Check if terms are accepted
    const termsAccepted = document.getElementById('acceptTerms').checked;
    if (!termsAccepted) {
        showError('terms', 'You must accept the terms and conditions');
        return;
    }
    
    // Continue with the existing signup process
    await handleSignupStep2(new Event('submit'));
}

// Update validation to include phone
function validatePersonalInfo() {
    const errors = [];
    const phone = document.getElementById('signupPhone').value;
    
    // Existing validations...
    if (!document.getElementById('signupName').value.trim()) {
        errors.push({ field: 'signupName', message: 'Please enter your full name' });
    }
    
    if (!document.getElementById('signupEmail').value.trim()) {
        errors.push({ field: 'signupEmail', message: 'Please enter your email address' });
    } else if (!validateEmail(document.getElementById('signupEmail').value)) {
        errors.push({ field: 'signupEmail', message: 'Please enter a valid email address' });
    }
    
    // Phone validation
    if (!phone.trim()) {
        errors.push({ field: 'signupPhone', message: 'Please enter your phone number' });
    } else if (!/^[0-9]{9}$/.test(phone)) {
        errors.push({ field: 'signupPhone', message: 'Please enter a valid 9-digit phone number' });
    } else if (!/^[65][0-9]{8}$/.test(phone)) {
        errors.push({ field: 'signupPhone', message: 'Please enter a valid Moroccan phone number starting with 6 or 5' });
    }
    
    // Password validations...
    const password = document.getElementById('signupPassword').value;
    if (!password) {
        errors.push({ field: 'signupPassword', message: 'Please enter a password' });
    } else if (!validatePassword(password)) {
        errors.push({ field: 'signupPassword', message: 'Password must be at least 8 characters' });
    }
    
    const confirmPassword = document.getElementById('confirmPassword').value;
    if (!confirmPassword) {
        errors.push({ field: 'confirmPassword', message: 'Please confirm your password' });
    } else if (password !== confirmPassword) {
        errors.push({ field: 'confirmPassword', message: 'Passwords do not match' });
    }
    
    return errors;
}

function validatePhone(phone) {
    return /^[65][0-9]{8}$/.test(phone);
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (resendTimer) {
        clearInterval(resendTimer);
    }
    if (window.recaptchaVerifier) {
        window.recaptchaVerifier.clear();
    }
});