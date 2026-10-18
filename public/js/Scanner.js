let qrScanner = null;
let isProcessingQR = false;


// ==========================================
// OPEN QR SCANNER
// ==========================================
function openQRScanner() {

    const modal = document.getElementById("qrScannerModal");

    if (!modal) {
        console.error("QR modal not found.");
        return;
    }

    modal.classList.add("open");

    const status = document.getElementById("qrStatus");

    if (status) {
        status.textContent = "Starting QR scanner...";
        status.className = "qr-status";
    }

    // Prevent duplicate scanner
    if (qrScanner) {
        console.log("QR scanner already running.");
        return;
    }

    const reader = document.getElementById("qr-reader");

    if (!reader) {
        console.error("QR reader element not found.");
        return;
    }

    isProcessingQR = false;

    qrScanner = new Html5Qrcode("qr-reader");



const config = {
    fps: 10,
    aspectRatio: 1.0,
    disableFlip: false,
    formatsToSupport: [
        Html5QrcodeSupportedFormats.QR_CODE
    ]
};

qrScanner.start(
    {
        facingMode: "environment"
    },

    config,

    // ==============================
    // QR DETECTED
    // ==============================

function (decodedText) {
    if (isProcessingQR) return;

    const qrData = parseStudentQR(decodedText);
    const status = document.getElementById("qrStatus");

    if (!qrData) {
        if (status) {
            status.textContent = "Invalid student QR format.";
            status.className = "qr-status error";
        }
        return;
    }

    isProcessingQR = true;

    if (status) {
       status.textContent =
    "✓ QR verified. Sending you to OTP verification...";
        status.className = "qr-status verified";
    }

    const scanner = qrScanner;

    if (!scanner) {
        isProcessingQR = false;
        return;
    }

    scanner.stop()
        .then(function () {
            scanner.clear();
            qrScanner = null;

            // Call the actual Laravel QR-login verification.
            verifyStudentByQR(qrData);
        })
        .catch(function (error) {
            console.error("Unable to stop QR scanner:", error);

            qrScanner = null;
            isProcessingQR = false;

            if (status) {
                status.textContent =
                    "Unable to process QR code. Please try again.";
                status.className = "qr-status error";
            }
        });
},


    // ==============================
    // SCANNING ERROR
    // ==============================
    function(errorMessage) {
    if (
        errorMessage &&
        !String(errorMessage).toLowerCase().includes("notfound")
    ) {
        console.warn("QR SCANNER ERROR:", errorMessage);
    }
}
)
.then(function() {

    console.log("QR CAMERA STARTED");

    if (status) {
        status.textContent =
            "● SCANNING — Looking for QR code...";

        status.className =
            "qr-status scanning";
    }

})
.catch(function(error) {

    console.error(
        "QR CAMERA ERROR:",
        error
    );

    qrScanner = null;

    if (status) {
        status.textContent =
            "Unable to start camera.";

        status.className =
            "qr-status error";
    }

});
}


function verifyStudentByQR(qrData) {
    console.log("QR verification started:", {
        hasBirthday: Boolean(qrData.date_of_birth),
        hasContact: Boolean(qrData.contact),
        hasAddress: Boolean(qrData.address)
    });

    const csrf = document.querySelector(
        'meta[name="csrf-token"]'
    );

    if (!csrf) {
        console.error("CSRF token is missing.");
        restartQRScanner();
        return;
    }

    const payload = {
        date_of_birth: qrData.date_of_birth,
        contact: qrData.contact,
        address: qrData.address
    };

    // Safe diagnostics: do not print private student data.
    console.log("QR PAYLOAD CHECK:", {
        dateFormatOK:
            /^\d{4}-\d{2}-\d{2}$/.test(payload.date_of_birth || ""),
        contactPresent:
            Boolean(payload.contact && payload.contact.trim()),
        addressPresent:
            Boolean(payload.address && payload.address.trim()),
        contactLength:
            (payload.contact || "").length,
        addressLength:
            (payload.address || "").length
    });

    fetch("/student/qr-login", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-TOKEN": csrf.getAttribute("content")
        },
        body: JSON.stringify(payload)
    })
    .then(async function (response) {
        const body = await response.text();

        console.log("QR LOGIN HTTP STATUS:", response.status);

        let result;

        try {
            result = JSON.parse(body);
        } catch {
            throw new Error(
                `HTTP ${response.status}: Server returned a non-JSON response.`
            );
        }

        console.log("QR LOGIN RESULT:", {
            success: result.success,
            message: result.message,
            errors: result.errors || null
        });

        return {
            ok: response.ok,
            result: result
        };
    })
    .then(function (response) {
        const result = response.result;
        const status = document.getElementById("qrStatus");

        if (result.success && response.ok) {
            if (status) {
                status.textContent =
                    "Student verified. Redirecting...";
                status.className = "qr-status verified";
            }

            if (result.redirect) {
                window.location.href = result.redirect;
            } else {
                console.error(
                    "Login succeeded but no redirect was returned."
                );
            }

            return;
        }

        console.error(
            "QR LOGIN FAILED:",
            result.message || "Unknown error"
        );

        if (status) {
            status.textContent =
                result.message || "Student verification failed.";
            status.className = "qr-status error";
        }

        isProcessingQR = false;

        setTimeout(function () {
            restartQRScanner();
        }, 1500);
    })
    .catch(function (error) {
        console.error("QR LOGIN ERROR:", error);

        const status = document.getElementById("qrStatus");

        if (status) {
            status.textContent =
                "Unable to verify Student ID. Please try again.";
            status.className = "qr-status error";
        }

        isProcessingQR = false;

        setTimeout(function () {
            restartQRScanner();
        }, 1500);
    });
}



// ==========================================
// RESTART SCANNER
// ==========================================


function parseStudentQR(qrText) {
    const birthdayMatch = qrText.match(
        /BIRTHDAY:\s*(\d{1,2}[/-]\d{1,2}[/-]\d{4})\s+CONTACT NUMBER:/i
    );

    const contactMatch = qrText.match(
        /CONTACT NUMBER:\s*(.*?)\s+ADDRESS:/i
    );

    const addressMatch = qrText.match(
        /ADDRESS:\s*(.+)$/i
    );

    if (!birthdayMatch || !contactMatch || !addressMatch) {
        console.error("Missing or invalid student details.");
        return null;
    }

    const dateMatch = birthdayMatch[1].match(
    /^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/
);

    const contact = contactMatch[1].trim();
    const address = addressMatch[1].trim();

    if (!dateMatch || !contact || !address) {
        console.error("Missing or invalid student details.");
        return null;
    }

    const month = Number(dateMatch[1]);
    const day = Number(dateMatch[2]);
    const year = Number(dateMatch[3]);

    const date = new Date(year, month - 1, day);

    if (
        date.getFullYear() !== year ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        console.error("Invalid birthday.");
        return null;
    }

    return {
        date_of_birth: `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`,
        contact: contact,
        address: address
    };
}



function restartQRScanner() {
    isProcessingQR = false;

    const status = document.getElementById("qrStatus");

    if (status) {
        status.textContent = "Restarting QR scanner...";
        status.className = "qr-status";
    }

    // Reuse the existing scanner initialization.
    if (qrScanner) {
        qrScanner.stop()
            .catch(function () {
                // The scanner may already be stopped.
            })
            .then(function () {
                if (qrScanner) {
                    try {
                        qrScanner.clear();
                    } catch (error) {
                        console.warn("Scanner cleanup skipped.");
                    }

                    qrScanner = null;
                }

                openQRScanner();
            });
    } else {
        openQRScanner();
    }
}


// ==========================================
// CLOSE QR SCANNER
// ==========================================
function closeQRScanner() {

    const modal =
        document.getElementById(
            "qrScannerModal"
        );


    if (qrScanner) {

        qrScanner.stop()

            .then(function() {

                console.log(
                    "QR CAMERA STOPPED"
                );

                qrScanner.clear();

                qrScanner = null;

            })

            .catch(function(error) {

                console.error(
                    "Scanner close error:",
                    error
                );

                qrScanner = null;
            });
    }


    if (modal) {

        modal.classList.remove("open");
    }


    isProcessingQR = false;
}
