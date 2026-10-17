
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify Email | LabTrack</title>

    <link rel="stylesheet" href="{{ asset('css/student-verify-otp.css') }}">
    <script src="{{ asset('js/student-verify-otp.js') }}" defer></script>
</head>

<body>
    <main class="verify-card">
        <div class="logo">LabTrack</div>

        <h1>Verify Your Email</h1>

        <p class="description">
            Enter the six-digit verification code sent to your email.
            The code expires after 10 minutes.
        </p>

        @if (session('success'))
            <div class="message success" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="message error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            id="otpForm"
            method="POST"
            action="{{ route('student.register.verify') }}"
        >
            @csrf

            <label for="otp">Verification Code</label>

            <input
                type="text"
                id="otp"
                name="otp"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]{6}"
                minlength="6"
                maxlength="6"
                placeholder="000000"
                value="{{ old('otp') }}"
                aria-describedby="otpHelp"
                required
                autofocus
            >

            <small id="otpHelp">Enter all six digits.</small>

            <button type="submit" id="verifyButton">
                Verify Email
            </button>
        </form>

        <form
            class="resend"
            method="POST"
            action="{{ route('student.register.resend') }}"
        >
            @csrf

            <button type="submit" class="resend-button">
                Resend Verification Code
            </button>
        </form>

        <a class="back" href="{{ route('student.signup') }}">
            Back to Sign Up
        </a>
    </main>
</body>
</html>
