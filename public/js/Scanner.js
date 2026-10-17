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
    
function(decodedText) {
    if (isProcessingQR) return;

    console.log("QR DETECTED:", decodedText);

    const qrData = parseStudentQR(decodedText);

    if (!qrData) {
        const status = document.getElementById("qrStatus");

        if (status) {
            status.textContent =
                "Invalid student QR format. Please scan again.";
            status.className = "qr-status error";
        }

        return;
    }

    isProcessingQR = true;

    const status = document.getElementById("qrStatus");

    if (status) {
        status.textContent =
            "QR detected. Enter your password to continue.";
        status.className = "qr-status verified";
    }

    // Stop scanning before asking for the password.
    const scanner = qrScanner;

    if (scanner) {
        scanner.stop()
            .catch(function(error) {
                console.warn("Scanner stop warning:", error);
            })
            .finally(function() {
                showQRPasswordPrompt(qrData);
            });
    } else {
        showQRPasswordPrompt(qrData);
    }
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


// ==========================================
// VERIFY STUDENT
// ==========================================
function verifyStudentByQR(qrData, password) {
console.log("VERIFY FUNCTION CALLED:", qrData);
    console.log(
        "Verifying Student ID:",
        qrData.date_of_birth
    );


    const csrf =
        document.querySelector(
            'meta[name="csrf-token"]'
        );


    if (!csrf) {

        console.error(
            "CSRF token is missing."
        );

        restartQRScanner();

        return;
    }


    fetch("/student/qr-login", {

        method: "POST",

        headers: {

            "Content-Type":
                "application/json",

            "Accept":
                "application/json",

            "X-CSRF-TOKEN":
                csrf.getAttribute("content")

        },

        
        body: JSON.stringify({
            date_of_birth: qrData.date_of_birth,
            contact: qrData.contact,
            address: qrData.address,
            password: document.getElementById("qrPassword").value
        })


    })

.then(async function(response) {
    const body = await response.text();

    console.log("QR LOGIN HTTP STATUS:", response.status);
console.log("QR LOGIN CONTENT TYPE:", response.headers.get("content-type"));
console.log("QR LOGIN RESPONSE BODY:", body);

const qrStatus = document.getElementById("qrStatus");

if (qrStatus) {
    qrStatus.textContent =
        "HTTP " + response.status + ": " + body.slice(0, 250);
}

    try {
        return JSON.parse(body);
    } catch {
        throw new Error(
            `HTTP ${response.status}: Server returned a non-JSON response.`
        );
    }
})


    .then(function(result) {

        console.log(
            "QR LOGIN RESULT:",
            result
        );


        const status =
            document.getElementById("qrStatus");


        // ======================================
        // SUCCESS
        // ======================================
        if (result.success) {

            console.log(
                "QR LOGIN SUCCESS"
            );


            if (status) {

                status.textContent =
                    "✓ Student verified! Redirecting...";

                status.className =
                    "qr-status verified";
            }


            setTimeout(function() {

                window.location.href =
                    result.redirect;

            }, 700);

        }


        // ======================================
        // INVALID STUDENT
        // ======================================
        else {

            console.error(
                "QR LOGIN FAILED:",
                result.message
            );


            if (status) {

                status.textContent =
                    result.message ||
                    "Student ID not found.";

                status.className =
                    "qr-status error";
            }


            isProcessingQR = false;

            setTimeout(function() {

                restartQRScanner();

            }, 1500);
        }

    })


    .catch(function(error) {

        console.error(
            "QR LOGIN ERROR:",
            error
        );


        const status =
            document.getElementById("qrStatus");


        if (status) {

            status.textContent =
                "Unable to verify Student ID.";

            status.className =
                "qr-status error";
        }


        isProcessingQR = false;


        setTimeout(function() {

            restartQRScanner();

        }, 1500);

    });
}



function parseStudentQR(decodedText) {
    const birthdayMatch = decodedText.match(
        /BIRTHDAY:\s*(\d{1,2}\/\d{1,2}\/\d{4})(?=\s+CONTACT NUMBER:)/i
    );

    const contactMatch = decodedText.match(
        /CONTACT NUMBER:\s*(\S+)(?=\s+ADDRESS:)/i
    );

    const addressMatch = decodedText.match(
        /ADDRESS:\s*(.+)$/i
    );

    if (!birthdayMatch || !contactMatch || !addressMatch) {
        return null;
    }

    const birthday = birthdayMatch[1].trim();
    const contact = contactMatch[1].trim();
    const address = addressMatch[1].trim();

    const dateMatch = birthday.match(
        /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/
    );

    if (!dateMatch || !contact || !address) {
        return null;
    }

    const month = Number(dateMatch[1]);
    const day = Number(dateMatch[2]);
    const year = Number(dateMatch[3]);

    const date = new Date(Date.UTC(year, month - 1, day));

    if (
        date.getUTCFullYear() !== year ||
        date.getUTCMonth() !== month - 1 ||
        date.getUTCDate() !== day
    ) {
        return null;
    }

    return {
        date_of_birth:
            `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`,
        contact: contact,
        address: address
    };
}


function showQRPasswordPrompt(qrData) {
    const status = document.getElementById("qrStatus");
    const reader = document.getElementById("qr-reader");

    if (!reader) return;

    let wrapper = document.getElementById("qrPasswordWrapper");

    if (!wrapper) {
        wrapper = document.createElement("div");
        wrapper.id = "qrPasswordWrapper";

        wrapper.innerHTML = `
            <label for="qrPassword">Student Password</label>
            <input
                type="password"
                id="qrPassword"
                placeholder="Enter your password"
                autocomplete="current-password"
            >
            <button type="button" id="qrVerifyButton">
                Verify and Login
            </button>
            <button type="button" id="qrRescanButton">
                Scan Again
            </button>
        `;

        reader.insertAdjacentElement("afterend", wrapper);
    }

    const passwordInput = document.getElementById("qrPassword");
    const verifyButton = document.getElementById("qrVerifyButton");
    const rescanButton = document.getElementById("qrRescanButton");

    passwordInput.value = "";

    verifyButton.onclick = function() {
        if (!passwordInput.value) {
            if (status) {
                status.textContent = "Please enter your password.";
                status.className = "qr-status error";
            }
            return;
        }

        verifyStudentByQR(qrData, passwordInput.value);
    };

    rescanButton.onclick = async function() {
        wrapper.remove();
        isProcessingQR = false;
        await restartQRScanner();
    };

    if (status) {
        status.textContent = "Enter your password to verify your account.";
        status.className = "qr-status";
    }

    passwordInput.focus();
}




// ==========================================
// RESTART SCANNER
// ==========================================

function restartQRScanner() {
    console.log("Restarting QR scanner...");

    const scanner = qrScanner;

    if (!scanner) {
        isProcessingQR = false;

        const modal = document.getElementById("qrScannerModal");

        if (modal && modal.classList.contains("open")) {
            openQRScanner();
        }

        return;
    }

    // Invalidate the current scanner reference first
    qrScanner = null;

    // Stop only if the scanner is actually running
    const stopPromise = scanner.getState() === 2
        ? scanner.stop().catch(function(error) {
            console.warn("Camera stop warning:", error);
        })
        : Promise.resolve();

    stopPromise
        .then(function() {
            try {
                scanner.clear();
            } catch (error) {
                console.warn("Scanner clear warning:", error);
            }
        })
        .finally(function() {
            const reader = document.getElementById("qr-reader");

            if (reader) {
                reader.innerHTML = "";
            }

            isProcessingQR = false;

            const modal = document.getElementById("qrScannerModal");

            if (modal && modal.classList.contains("open")) {
                openQRScanner();
            }
        });
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