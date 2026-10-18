
document.addEventListener('DOMContentLoaded', function () {
    const otpInput = document.getElementById('otp');
    const otpForm = document.getElementById('otpForm');
    const verifyButton = document.getElementById('verifyButton');

    if (!otpInput || !otpForm || !verifyButton) {
        return;
    }

    // Allow digits only and limit the input to six characters.
    otpInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });

    // Focus the OTP field when the page opens.
    otpInput.focus();

    // Prevent accidental submission with fewer than six digits.
    otpForm.addEventListener('submit', function (event) {
        if (!/^\d{6}$/.test(otpInput.value)) {
            event.preventDefault();

            otpInput.setCustomValidity(
                'Please enter the complete 6-digit OTP.'
            );

            otpInput.reportValidity();
            otpInput.focus();

            return;
        }

        otpInput.setCustomValidity('');

        // Show a loading state while Laravel processes the request.
        verifyButton.disabled = true;
        verifyButton.textContent = 'VERIFYING...';
    });

    otpInput.addEventListener('input', function () {
        otpInput.setCustomValidity('');
    });
});
