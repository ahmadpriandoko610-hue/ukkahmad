<?php

use Illuminate\Support\Facades\Route;

// Import Controllers
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;


/*
|--------------------------------------------------------------------------
| HALAMAN AWAL / KALENDER PUBLIK
|--------------------------------------------------------------------------
| Bisa diakses tanpa login.
| Digunakan oleh Siswa/Tamu untuk melihat agenda sekolah.
|--------------------------------------------------------------------------
*/

Route::get('/', [EventController::class, 'publicCalendar'])
    ->name('home');

Route::get('/calendar', [EventController::class, 'publicCalendar'])
    ->name('calendar.public');


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'login'])
    ->name('login');

Route::post('/login', [AuthController::class, 'prosesLogin'])
    ->name('login.proses');

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');

Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])
    ->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
    ->middleware('throttle:5,1')
    ->name('password.email');
Route::get('/verify-password-code', [PasswordResetController::class, 'codeForm'])
    ->name('password.code.form');
Route::post('/verify-password-code', [PasswordResetController::class, 'verifyCode'])
    ->middleware('throttle:10,1')
    ->name('password.code.verify');
Route::get('/reset-password', [PasswordResetController::class, 'resetForm'])
    ->name('password.reset.form');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])
    ->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->name('password.update');


/*
|--------------------------------------------------------------------------
| NOTIFICATION
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::post('/notifications/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read');

});


/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard Admin
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard/admin', [DashboardController::class, 'admin'])
        ->name('dashboard.admin');

    Route::get('/admin/calendar', [EventController::class, 'adminCalendar'])
        ->name('admin.calendar');

    Route::get('/admin/reports/agendas', [EventController::class, 'adminReport'])
        ->name('admin.agenda.report');


    /*
    |--------------------------------------------------------------------------
    | Kelola User
    |--------------------------------------------------------------------------
    */

    Route::resource('users', UserController::class);


    /*
    |--------------------------------------------------------------------------
    | Kelola Ruangan
    |--------------------------------------------------------------------------
    */

    Route::resource('rooms', RoomController::class);

    // Route lama agar link ruangan yang masih digunakan tetap berjalan
    Route::get('/ruangan', [RoomController::class, 'index'])
        ->name('ruangan.index');

    Route::get('/ruangan/create', [RoomController::class, 'create'])
        ->name('ruangan.create');


    /*
    |--------------------------------------------------------------------------
    | Kelola Agenda / Event
    |--------------------------------------------------------------------------
    */

    Route::resource('events', EventController::class);

    // Route lama Agenda agar link lama tetap berjalan
    Route::get('/agenda', [EventController::class, 'index'])
        ->name('agenda.index');

    Route::get('/agenda/create', [EventController::class, 'create'])
        ->name('agenda.create');

    Route::post('/agenda', [EventController::class, 'store'])
        ->name('agenda.store');

    Route::get('/agenda/{event}/edit', [EventController::class, 'edit'])
        ->name('agenda.edit');

    Route::put('/agenda/{event}', [EventController::class, 'update'])
        ->name('agenda.update');

    Route::delete('/agenda/{event}', [EventController::class, 'destroy'])
        ->name('agenda.destroy');

    Route::patch('/agenda/{event}/cancel', [EventController::class, 'cancel'])
        ->name('agenda.cancel');

    Route::post('/agenda/{event}/approve', [EventController::class, 'adminApprove'])
        ->name('admin.agenda.approve');

    /*
    |--------------------------------------------------------------------------
    | Pengaduan Kerusakan - Admin
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/complaints', [ComplaintController::class, 'adminIndex'])
        ->name('admin.complaints');

    Route::get('/admin/agenda/{event}/complaint', [ComplaintController::class, 'createForEvent'])
        ->name('admin.complaints.create');

    Route::post('/admin/agenda/{event}/complaint', [ComplaintController::class, 'storeForEvent'])
        ->name('admin.complaints.store');


    /*
    |--------------------------------------------------------------------------
    | Pindah Ruangan Agenda
    |--------------------------------------------------------------------------
    */

    Route::get('/events/{event}/move', [EventController::class, 'moveForm'])
        ->name('events.move.form');

    Route::put('/events/{event}/move', [EventController::class, 'move'])
        ->name('events.move');


    /*
    |--------------------------------------------------------------------------
    | Peringatan WhatsApp Pengaduan ke PIC
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/admin/complaints/{complaint}/whatsapp',
        [ComplaintController::class, 'whatsappWarning']
    )->name('complaint.whatsapp');

});


/*
|--------------------------------------------------------------------------
| PIC
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:pic'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard PIC
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard/pic', [DashboardController::class, 'pic'])
        ->name('dashboard.pic');


    /*
    |--------------------------------------------------------------------------
    | Verifikasi Agenda
    |--------------------------------------------------------------------------
    */

    Route::get('/pic/events', [EventController::class, 'verification'])
        ->name('pic.events');

    Route::get('/pic/agenda', [EventController::class, 'verification'])
        ->name('pic.agenda');

    Route::get('/pic/calendar', [EventController::class, 'picCalendar'])
        ->name('pic.calendar');


    /*
    |--------------------------------------------------------------------------
    | Approve Event
    |--------------------------------------------------------------------------
    */

    Route::post('/events/{event}/approve', [EventController::class, 'approve'])
        ->name('events.approve');


    /*
    |--------------------------------------------------------------------------
    | Reject Event
    |--------------------------------------------------------------------------
    */

    Route::get('/events/{event}/reject', [EventController::class, 'rejectForm'])
        ->name('events.reject.form');

    Route::post('/events/{event}/reject', [EventController::class, 'reject'])
        ->name('events.reject');


    /*
    |--------------------------------------------------------------------------
    | Route Lama Approve
    |--------------------------------------------------------------------------
    */

    Route::post('/agenda/{event}/setujui', [EventController::class, 'setujui'])
        ->name('agenda.setujui');


    /*
    |--------------------------------------------------------------------------
    | Route Lama Reject
    |--------------------------------------------------------------------------
    */

    Route::get('/agenda/{event}/tolak', [EventController::class, 'rejectForm'])
        ->name('agenda.tolak.form');

    Route::post('/agenda/{event}/tolak', [EventController::class, 'tolak'])
        ->name('agenda.tolak');


    /*
    |--------------------------------------------------------------------------
    | Jadwal Ruangan - PIC
    |--------------------------------------------------------------------------
    */

    Route::get('/pic/schedule', [EventController::class, 'schedule'])
        ->name('pic.schedule');


    /*
    |--------------------------------------------------------------------------
    | Pengaduan Kerusakan - PIC
    |--------------------------------------------------------------------------
    */

    // Menampilkan semua pengaduan
    Route::get('/pic/complaints', [ComplaintController::class, 'picIndex'])
        ->name('pic.complaints');

    Route::get('/pic/complaints/report', [ComplaintController::class, 'picReport'])
        ->name('pic.complaints.report');

    // Update status pengaduan
    Route::put('/pic/complaints/{complaint}', [ComplaintController::class, 'update'])
        ->name('pic.complaints.update');

});


/*
|--------------------------------------------------------------------------
| GURU
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:guru'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard Guru
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard/guru', [DashboardController::class, 'guru'])
        ->name('dashboard.guru');


    /*
    |--------------------------------------------------------------------------
    | Agenda Guru
    |--------------------------------------------------------------------------
    */

    Route::get('/guru/events', [EventController::class, 'myEvents'])
        ->name('guru.events');

    Route::get('/guru/agenda', [EventController::class, 'agendaGuru'])
        ->name('guru.agenda');

    Route::get('/guru/calendar', [EventController::class, 'teacherCalendar'])
        ->name('guru.calendar');


    /*
    |--------------------------------------------------------------------------
    | Ajukan Agenda
    |--------------------------------------------------------------------------
    */

    Route::get('/guru/events/create', [EventController::class, 'create'])
        ->name('guru.events.create');

    Route::post('/guru/events', [EventController::class, 'store'])
        ->name('guru.events.store');


    /*
    |--------------------------------------------------------------------------
    | Jadwal Ruangan - Guru
    |--------------------------------------------------------------------------
    */

    Route::get('/schedule', [EventController::class, 'schedule'])
        ->name('schedule.index');


    /*
    |--------------------------------------------------------------------------
    | Pengaduan Kerusakan - Guru
    |--------------------------------------------------------------------------
    */

    Route::get('/complaints', [ComplaintController::class, 'index'])
        ->name('complaints.index');

    Route::get('/complaints/create', [ComplaintController::class, 'create'])
        ->name('complaints.create');

    Route::post('/complaints', [ComplaintController::class, 'store'])
        ->name('complaints.store');

});


/*
|--------------------------------------------------------------------------
| JADWAL UMUM
|--------------------------------------------------------------------------
| Admin, Guru, dan PIC dapat melihat jadwal
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin,guru,pic'])->group(function () {

    Route::get('/jadwal', [EventController::class, 'jadwal'])
        ->name('jadwal.index');

});
