<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\DemoWorkspaceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicRequestController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\PublicRequestHeaders;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');
Route::view('/demo', 'demo')->name('demo');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::middleware('guest')->group(function () {
    Route::post('/demo/start', DemoWorkspaceController::class)->middleware('throttle:3,1')->name('demo.start');
    Route::post('/demo-login', DemoLoginController::class)->middleware('throttle:5,1')->name('demo.login');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::view('/forgot-password', 'auth.forgot')->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset', compact('token')))->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/email/verify', 'auth.verify')->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resend'])->middleware('throttle:3,1')->name('verification.send');
});
Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/requests/{clientRequest}/reminders', [RequestController::class, 'reminders'])->middleware('throttle:5,1')->name('requests.reminders');
    Route::get('/dashboard', [RequestController::class, 'index'])->name('dashboard');
    Route::get('/requests/create', [RequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [RequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{clientRequest}', [RequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{clientRequest}/share', [RequestController::class, 'share'])->name('requests.share');
    Route::post('/requests/{clientRequest}/email', [RequestController::class, 'email'])->middleware('throttle:3,1')->name('requests.email');
    Route::post('/requests/{clientRequest}/state', [RequestController::class, 'state'])->name('requests.state');
    Route::delete('/requests/{clientRequest}', [RequestController::class, 'destroy'])->name('requests.destroy');
    Route::get('/uploads/{upload}', [RequestController::class, 'download'])->name('uploads.download');
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::view('/clients/create', 'clients.form')->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
});

Route::prefix('r/{token}')->middleware([PublicRequestHeaders::class, 'throttle:120,1'])->group(function () {
    Route::get('/', [PublicRequestController::class, 'show'])->name('public-request.show');
    Route::post('/items/{item}', [PublicRequestController::class, 'save'])->whereNumber('item')->name('public-request.save');
    Route::get('/stop-reminders', [PublicRequestController::class, 'stop'])->name('public-request.stop');
    Route::post('/stop-reminders', [PublicRequestController::class, 'unsubscribe'])->name('public-request.unsubscribe');
    Route::post('/submit', [PublicRequestController::class, 'submit'])->name('public-request.submit');
});

Route::get('/admin', AdminController::class)->middleware(['auth', 'verified', EnsureAdmin::class])->name('admin.dashboard');
