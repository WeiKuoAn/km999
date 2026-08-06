<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CourseCategoryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeePlanController;
use App\Http\Controllers\FlowPreviewController;
use App\Http\Controllers\GradeLevelController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentPaymentController;
use App\Http\Controllers\StudentPromotionController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TuitionBagStatisticsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserTodoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('calendar', CalendarController::class)->name('calendar');
    Route::post('todos', [UserTodoController::class, 'store'])->name('todos.store');
    Route::patch('todos/{todo}', [UserTodoController::class, 'update'])->name('todos.update');
    Route::delete('todos/{todo}', [UserTodoController::class, 'destroy'])->name('todos.destroy');

    // 營運流程預覽頁（假資料，供核對流暢）
    Route::prefix('flow-preview')->name('flow-preview.')->group(function () {
        Route::get('/', [FlowPreviewController::class, 'index'])->name('index');
        Route::get('students', [FlowPreviewController::class, 'students'])->name('students');
        Route::get('trial', [FlowPreviewController::class, 'trial'])->name('trial');
        Route::get('enrollment', [FlowPreviewController::class, 'enrollment'])->name('enrollment');
        Route::get('counter', [FlowPreviewController::class, 'counter'])->name('counter');
        Route::get('fee-plans', [FlowPreviewController::class, 'feePlans'])->name('fee-plans');
        Route::get('sessions', [FlowPreviewController::class, 'sessions'])->name('sessions');
        Route::get('calendar', [FlowPreviewController::class, 'calendar'])->name('calendar');
        Route::get('short-courses', [FlowPreviewController::class, 'shortCourses'])->name('short-courses');
        Route::get('roster', [FlowPreviewController::class, 'roster'])->name('roster');
        Route::get('revenue', [FlowPreviewController::class, 'revenue'])->name('revenue');
    });

    Route::middleware('role:super_admin,admin,teacher')->group(function () {
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
        Route::get('students/next-code', [StudentController::class, 'nextCode'])->name('students.next-code');
        Route::post('students', [StudentController::class, 'store'])->name('students.store');
        Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::get('students/{student}/payments', [StudentController::class, 'payments'])->name('students.payments');
        Route::get('students/{student}/attendance-rate', [StudentController::class, 'attendanceRate'])->name('students.attendance-rate');
        Route::get('students/{student}/courses-schedule', [StudentController::class, 'coursesSchedule'])->name('students.courses-schedule');
        Route::delete('students/{student}/courses/{course}', [StudentController::class, 'destroyCourse'])->name('students.courses.destroy');
        Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
        Route::get('teachers/{teacher}/courses-schedule', [TeacherController::class, 'coursesSchedule'])->name('teachers.courses-schedule');
        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');

        Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::get('attendances/live', [AttendanceController::class, 'live'])->name('attendances.live');
        Route::get('student-attendances', [AttendanceController::class, 'studentIndex'])->name('student-attendances.index');
        Route::delete('student-attendances/bulk', [AttendanceController::class, 'bulkDestroyStudentAttendance'])->name('student-attendances.bulk-destroy');
        Route::get('student-attendances/{attendance}/edit', [AttendanceController::class, 'editStudentAttendance'])->name('student-attendances.edit');
        Route::put('student-attendances/{attendance}', [AttendanceController::class, 'updateStudentAttendance'])->name('student-attendances.update');
        Route::delete('student-attendances/{attendance}', [AttendanceController::class, 'destroyStudentAttendance'])->name('student-attendances.destroy');
        Route::post('student-attendances/{attendance}/makeup-date', [AttendanceController::class, 'updateMakeupDate'])->name('student-attendances.makeup-date.update');
        Route::get('attendances/classrooms/{classroom}/roll-call', [AttendanceController::class, 'rollCall'])->name('attendances.roll-call');
        Route::post('attendances/classrooms/{classroom}/day', [AttendanceController::class, 'storeDay'])->name('attendances.store-day');

        Route::get('tuition-bag-statistics', [TuitionBagStatisticsController::class, 'index'])->name('tuition-bag-statistics.index');
        Route::get('tuition-bag-statistics/unpaid', [TuitionBagStatisticsController::class, 'unpaidList'])->name('tuition-bag-statistics.unpaid');
        Route::get('tuition-bag-statistics/reconciliation-records', [TuitionBagStatisticsController::class, 'reconciliationRecords'])->name('tuition-bag-statistics.reconciliation-records');
        Route::post('tuition-bag-statistics/confirm-payment', [TuitionBagStatisticsController::class, 'confirmPayment'])->name('tuition-bag-statistics.confirm-payment');
        Route::post('tuition-bag-statistics/update-payment', [TuitionBagStatisticsController::class, 'updatePayment'])->name('tuition-bag-statistics.update-payment');
        Route::post('tuition-bag-statistics/cancel-payment', [TuitionBagStatisticsController::class, 'cancelPayment'])->name('tuition-bag-statistics.cancel-payment');

        Route::get('student-payments', [StudentPaymentController::class, 'index'])->name('student-payments.index');
        Route::get('payment-lists', [StudentPaymentController::class, 'roster'])->name('payment-lists.index');
        Route::get('payment-lists/pdf', [StudentPaymentController::class, 'rosterPdf'])->name('payment-lists.pdf');
        Route::get('student-payments/create', [StudentPaymentController::class, 'create'])->name('student-payments.create');
        Route::get('student-payments/search', [StudentPaymentController::class, 'search'])->name('student-payments.search');
        Route::get('student-payments/{student}/quote', [StudentPaymentController::class, 'quote'])->name('student-payments.quote');
        Route::post('student-payments/{student}/quote', [StudentPaymentController::class, 'store'])->name('student-payments.store');
        Route::post('student-payments/{student}/renew-next', [StudentPaymentController::class, 'renewNext'])->name('student-payments.renew-next');
        Route::get('student-payments/{student}', [StudentPaymentController::class, 'show'])->name('student-payments.show');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/attendance-rate', [ReportController::class, 'attendanceRate'])->name('reports.attendance-rate');
    });

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::resource('teachers', TeacherController::class)->except(['show', 'index']);
        Route::resource('course-categories', CourseCategoryController::class)->except('show');
        Route::resource('courses', CourseController::class)->except(['show', 'index']);
        Route::resource('academic-years', AcademicYearController::class)->except('show');
        Route::resource('grade-levels', GradeLevelController::class)->except('show');
        Route::get('student-promotions', [StudentPromotionController::class, 'index'])->name('student-promotions.index');
        Route::post('student-promotions', [StudentPromotionController::class, 'store'])->name('student-promotions.store');
        Route::resource('fee-plans', FeePlanController::class)->except('show');
        Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::post('holidays/import', [HolidayController::class, 'import'])->name('holidays.import');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
        Route::get('classrooms/{classroom}/students', [ClassroomController::class, 'editStudents'])->name('classrooms.students.index');
        Route::get('classrooms/{classroom}/attendance-rate', [ClassroomController::class, 'attendanceRate'])->name('classrooms.attendance-rate');
        Route::post('classrooms/{classroom}/enrollments', [ClassroomController::class, 'storeEnrollments'])->name('classrooms.enrollments.store');
        Route::patch('classrooms/{classroom}/enrollments/{enrollment}', [ClassroomController::class, 'updateEnrollment'])->name('classrooms.enrollments.update');
        Route::delete('classrooms/{classroom}/enrollments/{enrollment}', [ClassroomController::class, 'destroyEnrollment'])->name('classrooms.enrollments.destroy');
        Route::resource('classrooms', ClassroomController::class)->except('show');

        Route::resource('users', UserController::class)->except('show');
    });
});

require __DIR__.'/settings.php';
