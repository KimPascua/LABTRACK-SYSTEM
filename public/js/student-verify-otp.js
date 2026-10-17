
document.addEventListener("DOMContentLoaded", () => {
    const otpForm = document.getElementById("otpForm");
    const otpInput = document.getElementById("otp");
    const verifyButton = document.getElementById("verifyButton");

    if (!otpForm || !otpInput || !verifyButton) {
        return;
    }

    // Allow numeric digits only.
    otpInput.addEventListener("input", () => {
        otpInput.value = otpInput.value
            .replace(/\D/g, "")
            .slice(0, 6);
    });

    // Validate before submitting.
    otpForm.addEventListener("submit", (event) => {
        const otp = otpInput.value.trim();

        if (!/^\d{6}$/.test(otp)) {
            event.preventDefault();

            otpInput.setCustomValidity(
                "Please enter a valid six-digit verification code."
            );

            otpInput.reportValidity();
            otpInput.focus();
            return;
        }

        otpInput.setCustomValidity("");
        verifyButton.disabled = true;
        verifyButton.textContent = "Verifying...";
    });

    otpInput.addEventListener("input", () => {
        otpInput.setCustomValidity("");
        verifyButton.disabled = false;
        verifyButton.textContent = "Verify Email";
    });
});
