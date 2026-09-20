<?php

use App\Http\Controllers\Admin\ManualContributorController;
use App\Http\Controllers\AdminPublicationCategoriesController;
use App\Http\Controllers\AdminPublicationsController;
use App\Http\Controllers\AtcBookingController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\VatsimOauthController;
use App\Http\Controllers\CertificationFacilityController;
use App\Http\Controllers\ContributorsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Events\EventController;
use App\Http\Controllers\Events\EventFieldController;
use App\Http\Controllers\Events\EventManagementController;
use App\Http\Controllers\Events\EventPositionPresetController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoaController;
use App\Http\Controllers\News\NewsController;
use App\Http\Controllers\PublicationsController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffingRequestController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\StatisticsPrefixesController;
use App\Http\Controllers\Training\SoloCertController;
use App\Http\Controllers\Training\TrainingAssignmentController;
use App\Http\Controllers\Training\TrainingDashController;
use App\Http\Controllers\Training\TrainingTicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VisitFacilityController;
use App\Jobs\SyncRoster;
use App\Jobs\SyncTrainingTickets;
use App\Jobs\UpdateOnlineControllers;
use App\Livewire\EventRegistration;
use App\Mail\FeedbackCommentPosted;
use App\Mail\FeedbackReceived;
use App\Mail\FeedbackReleased;
use App\Mail\TrainingAssignmentCreated;
use App\Mail\Welcome;
use App\Models\Feedback;
use App\Models\FeedbackComment;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

// Homepage
Route::get('/', [HomeController::class, 'index'])->name('home');

// Contributors
Route::get('/contributors', [ContributorsController::class, 'index'])->middleware('auth')->name('contributors.index');

// ATC Bookings
Route::post('/bookings', [AtcBookingController::class, 'store'])
    ->middleware('permission:create atc booking')
    ->name('bookings.store');

// Roster
Route::get('/roster', [RosterController::class, 'index'])->middleware('auth')->name('roster.index');

// Visit
Route::get('/visit', [VisitFacilityController::class, 'index'])->name('visit.index');
Route::get('/visit/create', [VisitFacilityController::class, 'create'])
    ->middleware('auth')
    ->name('visit.create');

Route::post('/visit', [VisitFacilityController::class, 'store'])
    ->middleware('auth')
    ->name('visit.store');

// OAuth
Route::get('/auth/redirect', [VatsimOauthController::class, 'redirect'])
    ->name('auth.redirect');

Route::get('/login', function () {
    return redirect()->route('auth.redirect', [], 301);
})->name('login');

Route::get('/auth/callback', [VatsimOauthController::class, 'callback'])
    ->name('auth.callback');

Route::get('/auth/logout', [VatsimOauthController::class, 'logout'])
    ->name('auth.logout');

// Users
Route::resource('users', UserController::class, [
    'only' => ['show', 'edit', 'update'],
]);

Route::prefix('users/{user}')->middleware('auth')->group(function () {
    Route::get('/', [UserController::class, 'show'])->name('users.show');
    Route::get('feedback', [UserController::class, 'feedback'])->middleware('auth')->name('users.show.feedback');
    Route::get('training-tickets', [UserController::class, 'trainingTickets'])->middleware('auth')->name('users.show.training-tickets');
    Route::get('training-assignments', [UserController::class, 'trainingAssignments'])->middleware('auth')->name('users.show.training-assignments');
    Route::get('solo-certs', [UserController::class, 'soloCerts'])->middleware('auth')->name('users.show.solo-certs');
    Route::get('loa', [UserController::class, 'loa'])->middleware('auth')->name('users.show.loa');
    Route::get('registered-events', [UserController::class, 'registeredEvents'])->middleware('auth')->name('users.show.registered-events');
});

// Staff Directory
Route::get('/staff', [StaffController::class, 'index'])
    ->middleware('auth')
    ->name('staff.index');

// LOA
Route::prefix('loa')->middleware('auth')->name('loa.')->group(function () {
    Route::post('/', [LoaController::class, 'store'])->name('store');
    Route::put('{loa}', [LoaController::class, 'update'])->name('update');
    Route::delete('{loa}', [LoaController::class, 'destroy'])->name('destroy');
});

// FAQ (public)
Route::get('/faq', [FaqController::class, 'index'])->name('faq.index');

// Feedback
Route::redirect('/feedback', '/feedback/new');
Route::get('/feedback/new', [FeedbackController::class, 'index'])->middleware('auth')->name('feedback.index');
Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('auth')->name('feedback.store');

// Staffing Requests
Route::get('/staffing-requests', [StaffingRequestController::class, 'index'])->middleware('auth')->name('staffing-requests.index');
Route::post('/staffing-requests', [StaffingRequestController::class, 'store'])->middleware('auth')->name('staffing-requests.store');

// Controller Statistics
Route::get('controllers/statistics', [StatisticsController::class, 'index'])
    ->middleware('auth')
    ->name('statistics.index');

// Publications
Route::get('/publications/downloads', [PublicationsController::class, 'index'])
    ->name('publications.index');

Route::get('/publications/{publication}/file', [PublicationsController::class, 'file'])
    ->name('publications.file');

// Training Assignment Creation
Route::post('training-assignment/create', [TrainingAssignmentController::class, 'create'])
    ->middleware('auth')
    ->name('training-assignment.create');

// Training ticket view
Route::get('training-tickets/{ticket}', [TrainingTicketController::class, 'show'])
    ->middleware('auth')
    ->name('training-tickets.show');

// Events
Route::prefix('events')->name('events.')->group(function () {
    Route::get('/', [EventController::class, 'index'])
        ->name('index');

    Route::get('{event}', [EventController::class, 'show'])
        ->name('show');
});

Route::post('/events/{event}/request-position', [EventRegistration::class, 'store'])
    ->middleware('auth')
    ->name('events.request-position.store');

// Admin Routes
Route::prefix('admin')->middleware('permission:view dashboard')->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])
        ->name('admin.index');

    // News
    Route::middleware('role:admin')->prefix('/news')->group(function () {
        Route::get('/', [NewsController::class, 'index'])
            ->name('admin.news.index');

        Route::get('create', [NewsController::class, 'create'])
            ->name('admin.news.create');

        Route::post('/', [NewsController::class, 'store'])
            ->name('admin.news.store');

        Route::get('{news}/edit', [NewsController::class, 'edit'])
            ->name('admin.news.edit');

        Route::put('{news}', [NewsController::class, 'update'])
            ->name('admin.news.update');
    });

    // User Management
    Route::get('users', [UserManagementController::class, 'index'])
        ->name('manage-users.index');

    // FAQ Management (all ARTCC staff)
    Route::middleware('permission:manage faqs')->group(function () {
        Route::get('faqs', [FaqController::class, 'manage'])->name('admin.faqs.index');
    });

    // Feedback
    Route::middleware('permission:feedback:read')->group(function () {
        Route::get('feedback', [FeedbackController::class, 'manage'])->name('admin.feedback.index');
        Route::get('feedback/{feedback}', [FeedbackController::class, 'show'])->name('admin.feedback.show');
    });

    Route::middleware('permission:feedback:write')->group(function () {
        Route::put('feedback/{feedback}/stash', [FeedbackController::class, 'stash'])->name('admin.feedback.stash');
        Route::put('feedback/{feedback}/unstash', [FeedbackController::class, 'unstash'])->name('admin.feedback.unstash');
        Route::put('feedback/{feedback}/release', [FeedbackController::class, 'release'])->name('admin.feedback.release');
        Route::post('feedback/{feedback}/comments', [FeedbackController::class, 'storeComment'])->name('admin.feedback.comments.store');
    });

    // Staffing Requests
    Route::middleware('permission:staffing-requests:read')->group(function () {
        Route::get('staffing-requests', [StaffingRequestController::class, 'manage'])->name('admin.staffing-requests.index');
        Route::get('staffing-requests/{staffingRequest}', [StaffingRequestController::class, 'show'])->name('admin.staffing-requests.show');
    });

    Route::middleware('permission:staffing-requests:write')->group(function () {
        Route::patch('staffing-requests/{staffingRequest}/close', [StaffingRequestController::class, 'close'])->name('admin.staffing-requests.close');
        Route::patch('staffing-requests/{staffingRequest}/reopen', [StaffingRequestController::class, 'reopen'])->name('admin.staffing-requests.reopen');
    });

    Route::middleware('permission:manage visiting controllers')->group(function () {
        Route::get('visit-requests/{visitRequest}', [VisitFacilityController::class, 'show'])
            ->name('visit.show');

        Route::get('visit-requests', [VisitFacilityController::class, 'manage'])
            ->name('visit.manage');

        Route::put('visit-requests/{visitRequest}', [VisitFacilityController::class, 'update'])
            ->name('visit.update');

        Route::put('visit-requests/{visitRequest}/approve', [VisitFacilityController::class, 'approve'])
            ->name('visit.approve');

        Route::put('visit-requests/{visitRequest}/deny', [VisitFacilityController::class, 'deny'])
            ->name('visit.deny');
    });

    Route::middleware('permission:manage loas')->group(function () {
        Route::get('loas', [LoaController::class, 'manage'])->name('loa.manage');
        Route::get('loas/{loa}', [LoaController::class, 'show'])->name('loa.show');
        Route::put('loas/{loa}/approve', [LoaController::class, 'approve'])->name('loa.approve');
        Route::put('loas/{loa}/deny', [LoaController::class, 'deny'])->name('loa.deny');
        Route::put('loas/{loa}/revoke', [LoaController::class, 'revoke'])->name('loa.revoke');
    });

    // Contributors
    Route::middleware('permission:manage contributors')->group(function () {
        Route::get('contributors', [ManualContributorController::class, 'index'])
            ->name('admin.contributors.index');

        Route::post('contributors', [ManualContributorController::class, 'store'])
            ->name('admin.contributors.store');

        Route::delete('contributors/{contributor}', [ManualContributorController::class, 'destroy'])
            ->name('admin.contributors.destroy');
    });

    // Facilities Dept.
    Route::prefix('data')->group(function () {
        Route::middleware('permission:manage statistics prefixes')
            ->resource('statistics-prefixes', StatisticsPrefixesController::class);

        Route::middleware('permission:certification-facilities:write')->prefix('certification-facilities')->group(function () {
            // Facility + level CRUD is handled by Livewire components on these pages.
            Route::get('/', [CertificationFacilityController::class, 'index'])->name('certification-facilities.index');
            Route::get('/{facility}', [CertificationFacilityController::class, 'show'])->name('certification-facilities.show');
        });
    });

    // Facilities staff
    Route::middleware('permission:statistics:write')->group(function () {
        Route::post('statistics/sync', [StatisticsController::class, 'sync'])
            ->name('statistics.sync');
    });

    // Publications Management
    Route::middleware('permission:documents:write')
        ->prefix('publications')
        ->name('admin.publications.')
        ->group(function () {

            Route::get('/', [AdminPublicationsController::class, 'index'])
                ->name('index');

            Route::get('/create', [AdminPublicationsController::class, 'create'])
                ->name('create');

            Route::post('/', [AdminPublicationsController::class, 'store'])
                ->name('store');

            Route::prefix('categories')
                ->name('categories.')
                ->group(function () {

                    Route::get('/', [AdminPublicationCategoriesController::class, 'index'])
                        ->name('index');

                    Route::get('/create', [AdminPublicationCategoriesController::class, 'create'])
                        ->name('create');

                    Route::post('/', [AdminPublicationCategoriesController::class, 'store'])
                        ->name('store');

                    Route::get('/{id}/edit', [AdminPublicationCategoriesController::class, 'edit'])
                        ->name('edit');

                    Route::put('/{id}', [AdminPublicationCategoriesController::class, 'update'])
                        ->name('update');

                    Route::delete('/{id}', [AdminPublicationCategoriesController::class, 'destroy'])
                        ->name('destroy');

                    Route::patch('/{id}/toggle-nav', [AdminPublicationCategoriesController::class, 'toggleNav'])
                        ->name('toggle-nav');
                });

            Route::get('/{id}/edit', [AdminPublicationsController::class, 'edit'])
                ->name('edit');

            Route::put('/{id}', [AdminPublicationsController::class, 'update'])
                ->name('update');

            Route::delete('/{id}', [AdminPublicationsController::class, 'destroy'])
                ->name('destroy');
        });

    // Logs
    Route::middleware('permission:view audit logs')->group(function () {
        Route::get('logs', [AuditLogController::class, 'index'])
            ->name('logs.index');

        Route::get('logs/export', [AuditLogController::class, 'export'])
            ->name('logs.export');
    });

    // Training Dept.
    Route::prefix('/training')
        ->middleware('role:training')
        ->group(function () {

            Route::get('/', [TrainingDashController::class, 'index'])
                ->name('admin.training.index');

            Route::resource('tickets', TrainingTicketController::class, [
                'except' => ['show'],
            ])->names('training-tickets');

            Route::resource('assignments', TrainingAssignmentController::class, [
                'only' => ['update', 'edit', 'index'],
            ])->names('training-assignments');

            Route::resource('solo-certs', SoloCertController::class, [
                'only' => ['index', 'create', 'update', 'destroy', 'store'],
            ])->names('solo-certs');

            Route::put('assignments/claim/{assignment}',
                [TrainingAssignmentController::class, 'claim']
            )->name('training-assignments.claim');

            Route::put('assignments/drop/{assignment}',
                [TrainingAssignmentController::class, 'drop']
            )->name('training-assignments.drop');

            Route::delete('assignments',
                [TrainingAssignmentController::class, 'destroy']
            )->name('training-assignments.destroy');
        });

    // Events Dept.
    Route::prefix('/events')
        ->middleware('permission:manage events')
        ->group(function () {

            Route::patch('{event}/visibility',
                [EventManagementController::class, 'toggleVisibility']
            )->name('admin.event.visibility');

            Route::patch('{event}/archived',
                [EventManagementController::class, 'toggleArchived']
            )->name('admin.event.archived');

            Route::patch('{event}/positions-locked',
                [EventManagementController::class, 'togglePositionsLocked']
            )->name('admin.event.positions-locked');

            Route::resource('event-fields', EventFieldController::class)
                ->only(['index', 'store', 'destroy'])
                ->parameters(['event-fields' => 'eventField'])
                ->names('admin.events.event-fields');

            Route::resource('position-presets', EventPositionPresetController::class)
                ->names('admin.events.position-presets');

            Route::get('/', [EventManagementController::class, 'index'])
                ->name('admin.events.index');

            Route::get('{event}/manage',
                [EventManagementController::class, 'manage']
            )->name('admin.events.manage');

            Route::get('create',
                [EventManagementController::class, 'create']
            )->name('admin.events.create');

            Route::post('/',
                [EventManagementController::class, 'store']
            )->name('admin.events.store');

            Route::get('{event}/edit',
                [EventManagementController::class, 'edit']
            )->name('admin.events.edit');

            Route::get('{event}/positions',
                [EventManagementController::class, 'positions']
            )->name('admin.events.positions');

            Route::put('{event}',
                [EventManagementController::class, 'update']
            )->name('admin.events.update');

            Route::delete('{event}',
                [EventManagementController::class, 'destroy']
            )->name('admin.events.destroy');
        });
});

// Certification Management (instructors + admins; gated only by certifications:write)
Route::prefix('admin')->middleware('permission:certifications:write')->group(function () {
    Route::get('certifications', fn () => view('certifications.index'))->name('certifications.index');
});

// Dev Only Routes
if (App::environment('development', 'local')) {

    Route::get('/sync', function () {
        SyncRoster::dispatch();
        UpdateOnlineControllers::dispatch();

        return 'scheduled';
    });

    Route::get('/sync-training', function () {
        SyncTrainingTickets::dispatch();

        return 'scheduled';
    });

    Route::get('/test-email', function () {
        Mail::to('chrisjm66@gmail.com')
            ->send(new Welcome(User::find(1697197)));

        return new TrainingAssignmentCreated(
            TrainingAssignment::find(1)
        );
    });

    Route::get('/test-email/feedback-released', fn () => new FeedbackReleased(Feedback::latest()->firstOrFail()));

    Route::get('/test-email/feedback-comment', fn () => new FeedbackCommentPosted(FeedbackComment::latest()->firstOrFail()));

    Route::get('/test-email/feedback-received', fn () => new FeedbackReceived(Feedback::latest()->firstOrFail()));
}
