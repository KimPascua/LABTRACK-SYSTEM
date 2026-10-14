<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Attendance Monitoring System</title>

    <script src="https://kit.fontawesome.com/96bdbf5a1c.js" crossorigin="anonymous"></script>

    <link rel="stylesheet" href="{{ asset('css copy/ForgetPassword.css') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

<div class="login-box">

    <img src="{{ asset('images/logo.png') }}" class="logo">

    <h1>FORGOT PASSWORD</h1>

    <p>Enter your Student ID to reset your password.</p>

    <form action="{{ route('student.reset-password') }}" method="POST">
        @csrf

        <div class="input-box">
            <i class="fa-solid fa-user"></i>

            <input
                type="text"
                name="student_id"
                placeholder="Student ID"
                autocomplete="off"
                required>
        </div>

        <div class="input-box">
            <i class="fa-solid fa-lock"></i>

            <input
                type="password"
                name="new_password"
                placeholder="New Password"
                autocomplete="new-password"
                required>
        </div>

        <div class="input-box">
            <i class="fa-solid fa-lock"></i>

            <input
                type="password"
                name="new_password_confirmation"
                placeholder="Confirm Password"
                autocomplete="new-password"
                required>
        </div>

        <button id="btn1" type="submit">
            SUBMIT
        </button>

    </form>

    <br>

    <div class="signup">

        <a id="back" href="{{ route('student.login') }}">
            <span>
                <i class="fa-solid fa-arrow-left"></i>
            </span>
        </a>

        Back to Login Page

    </div>

</div>


<!-- =====================================================
     PASSWORD RESET POPUP
====================================================== -->

@if(session('success') || session('error'))

<div
    id="passwordResetPopup"
    class="password-popup-overlay"
>

    <div
        class="password-popup
        {{ session('success') ? 'success' : 'error' }}"
    >

        <div class="password-popup-icon">

            @if(session('success'))
                <i class="fa-solid fa-check"></i>
            @else
                <i class="fa-solid fa-xmark"></i>
            @endif

        </div>

        <h2>
            @if(session('success'))
                Password Reset Successful!
            @else
                Password Reset Failed
            @endif
        </h2>

        <p>
            {{ session('success') ?? session('error') }}
        </p>

        <button
            type="button"
            id="passwordPopupButton"
            onclick="closePasswordResetPopup()"
        >
            OK
        </button>

    </div>

</div>

@endif


<script>

function closePasswordResetPopup() {

    const popup =
        document.getElementById("passwordResetPopup");

    if (!popup) return;

    popup.classList.remove("show");

    setTimeout(() => {
        popup.remove();
    }, 250);
}


/* Automatically show popup after page loads */

document.addEventListener("DOMContentLoaded", function () {

    const popup =
        document.getElementById("passwordResetPopup");

    if (!popup) return;

    setTimeout(() => {
        popup.classList.add("show");
    }, 50);

});

</script>

</body>
</html>