
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify QR Login OTP | LabTrack</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css copy/Scanner.css') }}">
    <link rel="stylesheet" href="{{ asset('css/student-qr-verify-otp.css') }}">
</head>

<body>
    <main class="login-box otp-box">

        <img
            src="{{ asset('images/logo.png') }}"
            class="logo"
            alt="LabTrack Logo"
        >

        <h1>VERIFY YOUR IDENTITY</h1>

        <p class="otp-description">
            Enter the 6-digit OTP sent to your registered email.
        </p>

        @if (session('success'))
            <p class="message success" role="status">
                {{ session('success') }}
            </p>
        @endif

        @if (session('error'))
            <p class="message error" role="alert">
                {{ session('error') }}
            </p>
        @endif

        @if ($errors->any())
            <div class="message error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form
            class="otp-form"
            id="otpForm"
            action="{{ route('student.qr.otp.verify') }}"
            method="POST"
        >
            @csrf

            <label for="otp">One-Time Password</label>

            <input
                type="text"
                id="otp"
                name="otp"
                inputmode="numeric"
                pattern="[0-9]{6}"
                minlength="6"
                maxlength="6"
                placeholder="000000"
                autocomplete="one-time-code"
                aria-label="Enter your six-digit OTP"
                required
            >

            <button type="submit" id="verifyButton">
                VERIFY OTP
            </button>
        </form>

        <form
            class="resend-form"
            action="{{ route('student.qr.otp.resend') }}"
            method="POST"
        >
            @csrf

            <button type="submit" class="resend-button">
                RESEND OTP
            </button>
        </form>

        <a class="back-link" href="{{ route('student.qr') }}">
            Back to QR Scanner
        </a>

    </main>

    <script src="{{ asset('js/student-qr-verify-otp.js') }}"></script>
</body>
</html>
