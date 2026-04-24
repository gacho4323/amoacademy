<?php

use App\Http\Controllers\API\Admin\AdminCourseController;
use App\Http\Controllers\API\Admin\AdminEbookUploadController;
use App\Http\Controllers\API\Admin\AdminInstructorController;
use App\Http\Controllers\API\Admin\AdminOrderController;
use App\Http\Controllers\API\Admin\AdminTemplateUploadController;
use App\Http\Controllers\API\Admin\AdminUserController;
use App\Http\Controllers\API\Admin\AdminVideoUploadController;
use App\Http\Controllers\API\Admin\DashboardController;
use App\Http\Controllers\API\Admin\ExportCsvController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Cart\CartController;
use App\Http\Controllers\API\Contact\ContactController;
use App\Http\Controllers\API\Course\CourseController;
use App\Http\Controllers\API\Ebook\EbookController;
use App\Http\Controllers\API\Instructor\InstructorController;
use App\Http\Controllers\API\Meta\MetaConversionController;
use App\Http\Controllers\API\MinimaxController;
use App\Http\Controllers\API\Payment\PaymentController;
use App\Http\Controllers\API\Search\SearchController;
use App\Http\Controllers\API\Template\TemplateController;
use App\Http\Controllers\API\Video\VideoController;
use Illuminate\Support\Facades\Route;

Route::post('/minimax/test-invoice/{paymentId}', [MinimaxController::class, 'testInvoice']);
Route::get('/minimax/invoices', [MinimaxController::class, 'getIssuedInvoices']);
Route::get('/minimax/fiscal/{invoiceTitle}', [MinimaxController::class, 'downloadFiscal']);

// Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/register-free-course', [AuthController::class, 'registerFreeCourse']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider'])
    ->where('provider', 'google|facebook')
    ->name('social.redirect');

Route::post('/meta/conversion', [MetaConversionController::class, 'sendEvent'])->name('meta.conversion');

/*Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])
    ->where('provider', 'google|facebook')
    ->name('social.callback');*/

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);
Route::middleware('auth:sanctum')->post('/update-credentials', [AuthController::class, 'updateCredentials']);

Route::get('/search', [SearchController::class, 'search'])->name('search');

Route::get('/instructors/{instructor}', [InstructorController::class, 'show']);
Route::get('/instructors', [InstructorController::class, 'index']);

Route::get('/ebooks', [EbookController::class, 'indexAll']);
Route::get('/templates', [TemplateController::class, 'indexAll']);

Route::get('ebooks/{ebook}', [EbookController::class, 'show']);

Route::get('/courses/category/{categorySlug}', [CourseController::class, 'getCoursesByCategory']);

Route::prefix('courses/{course}/')->group(function () {
    // Video Routes
    Route::get('videos', [VideoController::class, 'index']);
    Route::get('videos/{video}/url', [VideoController::class, 'getVideoUrl']);
    Route::get('videos/{video}/thumbnail', [VideoController::class, 'getThumbnailUrl']);
    Route::post('videos/{introVideo}/concatenate/{mainVideo}', [VideoController::class, 'concatenate']);

    // Ebook Routes
    Route::get('ebooks', [EbookController::class, 'index']);
    Route::get('ebooks/{ebook}/url', [EbookController::class, 'getEbookUrl']);
    Route::get('ebooks/{ebook}/thumbnail', [EbookController::class, 'getThumbnailUrl']);
    Route::get('ebooks/{ebook}', [EbookController::class, 'show']);

    // Template Routes
    Route::get('templates', [TemplateController::class, 'index']);
    Route::get('templates/{template}/url', [TemplateController::class, 'getTemplateUrl']);
    Route::get('templates/{template}/thumbnail', [TemplateController::class, 'getThumbnailUrl']);
    Route::get('templates/{template}', [TemplateController::class, 'show']);
});

// Protected Routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [AuthController::class, 'notifications']);
    Route::post('/contact', [ContactController::class, 'store']);

    Route::prefix('courses/{course}/')->group(function () {
        Route::post('ebooks/{author}', [EbookController::class, 'store'])->middleware('admin');
        Route::post('templates/{author}', [TemplateController::class, 'store'])->middleware('admin');

        Route::get('supplemental-materials', [EbookController::class, 'getSupplementalMaterial']);
    });

    // Course Routes
    Route::prefix('courses')->group(function () {
        Route::get('/featured', [CourseController::class, 'featured']);
        Route::get('/new', [CourseController::class, 'new']);
        Route::get('/recommended', [CourseController::class, 'recommended']);
        Route::post('/', [CourseController::class, 'store'])->middleware('admin');
        Route::post('/{course}/extend', [CourseController::class, 'extend']);
        Route::get('/user-courses', [CourseController::class, 'userCourses'])->name('courses.user-courses');
    });

    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'show']);
        Route::post('/add', [CartController::class, 'addCourse']);
        Route::delete('/remove', [CartController::class, 'removeCourse']);
        Route::delete('/clear', [CartController::class, 'clear']);
    });
});

Route::prefix('payments')->group(function () {
    Route::match(['get', 'post'], 'execute', [PaymentController::class, 'execute'])->name('api.payment.execute');
    Route::match(['get', 'post'], 'cancel', [PaymentController::class, 'cancel'])->name('api.payment.cancel');
    Route::post('notify', [PaymentController::class, 'notify'])->name('api.payment.notify');
    Route::get('status/{paymentId}', [PaymentController::class, 'status'])->name('api.payment.status');
});

Route::prefix('payments')->middleware('auth:sanctum')->group(function () {
    Route::post('course/{course}/initiate', [PaymentController::class, 'initiate'])->name('api.payment.initiate');

});

Route::prefix('payments')->middleware('auth:sanctum')->group(function () {
    Route::post('course/{course}/initiate-uplatnica', [PaymentController::class, 'initiateUplatnica'])->name('api.payment.initiate-uplatnica');

});

// Public Course Routes (accessible without authentication)
Route::prefix('courses')->middleware('throttle:60,1')->group(function () {
    Route::get('/popular', [CourseController::class, 'popular']);
    Route::apiResource('/', CourseController::class)->except(['store'])->parameter('', 'course');
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/activities', [DashboardController::class, 'getRecentActivities']);
    Route::get('/dashboard-overview', [DashboardController::class, 'getOverview']);

    Route::get('/courses/{id}/edit', [AdminCourseController::class, 'edit']);
    Route::post('/courses/{course}', [AdminCourseController::class, 'update']);
    Route::post('/courses', [AdminCourseController::class, 'store']);

    Route::get('/instructors', [AdminInstructorController::class, 'index']);
    Route::get('/instructors/{id}/edit', [AdminInstructorController::class, 'edit']);
    Route::post('/instructors', [AdminInstructorController::class, 'store']);
    Route::post('/instructors/{id}', [AdminInstructorController::class, 'update']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}/edit', [AdminUserController::class, 'edit']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::post('/users/{id}', [AdminUserController::class, 'update']);
    Route::get('/courses', [AdminUserController::class, 'getAvailableCourses']);

    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}/edit', [AdminOrderController::class, 'edit']);

    Route::post('/videos/upload/{course}', [AdminVideoUploadController::class, 'uploadVideo'])
        ->name('admin.videos.upload');

    Route::post('/ebook/upload/{course}', [AdminEbookUploadController::class, 'uploadEbook'])
        ->name('admin.ebooks.upload');

    Route::post('/templates/upload/{course}', [AdminTemplateUploadController::class, 'uploadTemplate'])
        ->name('admin.template.upload');

    Route::get('/users/export-csv', [ExportCsvController::class, 'exportCsv'])->name('admin.export-csv');
    Route::get('/users/check-csv/{userId}', [ExportCsvController::class, 'checkCsvStatus'])->name('admin.check-csv');
    Route::get('/users/download-csv/{userId}', [ExportCsvController::class, 'downloadCsv'])->name('admin.download-csv');
});
