<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BookController;
use App\Http\Controllers\AdminBookController;
use App\Http\Controllers\SsoController;

// Keep the login route name for Laravel auth redirects, but never show a login portal.
Route::redirect('/login', '/books')->name('login');
Route::post('/login', fn () => redirect()->route('books.index'));
Route::post('/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('books.index');
})->name('logout');

// SSO Routes (no CSRF — server-to-server token issue + browser redirect)
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::post('/sso/token', [SsoController::class, 'issueToken'])->name('sso.token');
});
Route::get('/sso/login', [SsoController::class, 'loginWithToken'])->name('sso.login');

// Root redirect
Route::get('/', function () {
    return redirect()->route('books.index');
});

// SSO Debug route (remove after confirming SSO works)
Route::get('/sso-debug', function (\Illuminate\Http\Request $request) {
    $cookies = [];
    foreach (['amis_admin_session','amis_student_session','amis_teacher_session'] as $name) {
        $val = $request->cookie($name);
        $raw = $request->cookies->get($name);
        $session = $val ? \Illuminate\Support\Facades\DB::table('sessions')->where('id', $val)->first() : null;
        $cookies[$name] = [
            'decrypted_value' => $val ? substr($val, 0, 20).'...' : null,
            'raw_present'     => !empty($raw),
            'session_found'   => $session ? true : false,
            'user_id'         => $session?->user_id ?? null,
        ];
    }
    return response()->json([
        'auth_user'    => \Illuminate\Support\Facades\Auth::id(),
        'all_cookies'  => array_keys($request->cookies->all()),
        'sso_cookies'  => $cookies,
    ]);
});

// Public E-Book Catalog & Viewing
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// Secure private PDF streaming (requires signed temporary URL validation)
Route::get('/ebooks/stream/{ebook}', [BookController::class, 'stream'])
    ->name('ebooks.stream')
    ->middleware('signed');

// Admin Ebook Catalog & Management
Route::middleware('auth')->group(function () {
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/books', [AdminBookController::class, 'index'])->name('admin.books.index');
        Route::get('/books/create', [AdminBookController::class, 'create'])->name('admin.books.create');
        Route::post('/books', [AdminBookController::class, 'store'])->name('admin.books.store');
        Route::get('/books/{book}', [AdminBookController::class, 'show'])->name('admin.books.show');
        Route::get('/books/{book}/edit', [AdminBookController::class, 'edit'])->name('admin.books.edit');
        Route::put('/books/{book}', [AdminBookController::class, 'update'])->name('admin.books.update');
        Route::delete('/books/{book}', [AdminBookController::class, 'destroy'])->name('admin.books.destroy');
    });
});
