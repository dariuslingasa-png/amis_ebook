<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMIS eBook - Login</title>
    <link rel="icon" type="image/png" href="{{ asset('images/AMIS_Logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Naskh+Arabic:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <main class="ebook-login">
        <section class="ebook-login-grid">
            <div class="ebook-login-identity">
                <div class="ebook-login-lockup">
                    <img src="{{ asset('images/AMIS_Logo.png') }}" alt="AMIS Logo">
                    <div class="ebook-login-wordmark">
                        <p class="ebook-arabic" lang="ar" dir="rtl">المدرسة المنورة الإسلامية</p>
                        <h1>AL MUNAWWARA ISLAMIC SCHOOL</h1>
                        <strong>eBook</strong>
                    </div>
                </div>
                <p>Read assigned digital books, access secure PDFs, and continue your AMIS learning materials in one focused library.</p>
            </div>

            <div class="ebook-panel ebook-login-panel">
                <h2>Sign in</h2>
                <p>Use your AMIS account to open the digital library.</p>

                @if($errors->any())
                    <div class="ebook-error">
                        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                        <div>
                            @foreach ($errors->all() as $error)
                                <p class="m-0">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="ebook-form">
                    @csrf

                    <div class="ebook-field">
                        <label for="email" class="ebook-label">Email Address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@amis.edu.ph" class="ebook-input">
                    </div>

                    <div class="ebook-field">
                        <label for="password" class="ebook-label">Password</label>
                        <input id="password" type="password" name="password" required placeholder="Password" class="ebook-input">
                    </div>

                    <label class="ebook-choice">
                        <input type="checkbox" name="remember" value="1" class="ebook-check">
                        Remember me
                    </label>

                    <button type="submit" class="ebook-btn ebook-btn-primary w-full">
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Sign In
                    </button>
                </form>
            </div>
        </section>
    </main>

    <script>window.lucide?.createIcons();</script>
</body>
</html>
