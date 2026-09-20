<?php

use App\Enums\FeedbackExperience;
use App\Enums\FeedbackStatus;
use App\Jobs\SendFeedbackToWebhook;
use App\Mail\FeedbackCommentPosted;
use App\Mail\FeedbackReceived;
use App\Mail\FeedbackReleased;
use App\Models\Feedback;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

// Submitting feedback

test('the feedback form lives at /feedback/new and the old /feedback path redirects there', function () {
    expect(route('feedback.index', absolute: false))->toBe('/feedback/new');

    $this->get('/feedback')->assertRedirect('/feedback/new');
});

test('given a guest, when visiting the feedback form, then they are redirected to login', function () {
    $response = $this->get(route('feedback.index'));

    $response->assertRedirect(route('login'));
});

test('given an authenticated user, when visiting the feedback form, then only rostered controllers are listed', function () {
    $user = User::factory()->create();
    $rostered = User::factory()->create(['rostered' => true]);
    $unrostered = User::factory()->create(['rostered' => false]);

    $response = $this->actingAs($user)->get(route('feedback.index'));

    $response->assertOk();
    $response->assertSee($rostered->name_reversed);
    $response->assertDontSee($unrostered->name_reversed);
});

test('given an authenticated user, when submitting valid feedback, then it is stored as pending', function () {
    $user = User::factory()->create();
    $controller = User::factory()->create(['rostered' => true]);

    $response = $this->actingAs($user)->post(route('feedback.store'), [
        'controller_id' => $controller->id,
        'position' => 'JAX_CTR',
        'experience' => FeedbackExperience::OUTSTANDING->value,
        'staff_followup' => '1',
        'comments' => 'Great session, very helpful controller.',
    ]);

    $response->assertRedirect(route('feedback.index'));
    $response->assertSessionHas('success');

    $feedback = Feedback::sole();
    expect($feedback->user_id)->toBe($user->id)
        ->and($feedback->controller_id)->toBe($controller->id)
        ->and($feedback->experience)->toBe(FeedbackExperience::OUTSTANDING)
        ->and($feedback->staff_followup)->toBeTrue()
        ->and($feedback->fresh()->status)->toBe(FeedbackStatus::PENDING);
});

test('given an authenticated user, when submitting feedback for a non-rostered controller, then a controller error is returned and nothing is stored', function () {
    $user = User::factory()->create();
    $controller = User::factory()->create(['rostered' => false]);

    $response = $this->actingAs($user)->post(route('feedback.store'), [
        'controller_id' => $controller->id,
        'position' => 'JAX_CTR',
        'experience' => FeedbackExperience::GOOD->value,
        'comments' => 'Some comments.',
    ]);

    $response->assertSessionHasErrors('controller_id');
    expect(Feedback::count())->toBe(0);
});

test('given an authenticated user, when submitting feedback with an invalid experience, then an experience error is returned and nothing is stored', function () {
    $user = User::factory()->create();
    $controller = User::factory()->create(['rostered' => true]);

    $response = $this->actingAs($user)->post(route('feedback.store'), [
        'controller_id' => $controller->id,
        'position' => 'JAX_CTR',
        'experience' => 'Mediocre',
        'comments' => 'Some comments.',
    ]);

    $response->assertSessionHasErrors('experience');
    expect(Feedback::count())->toBe(0);
});

// Managing feedback

test('given an admin, when visiting the feedback management page, then submitted feedback is shown', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create(['position' => 'JAX_TWR']);

    $response = $this->actingAs($admin)->get(route('admin.feedback.index'));

    $response->assertOk();
    $response->assertSee('JAX_TWR');
    $response->assertSee($feedback->controller->name);
});

test('given a user without the feedback:read permission, when visiting the feedback management page, then the request is forbidden', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view dashboard');

    $response = $this->actingAs($user)->get(route('admin.feedback.index'));

    $response->assertForbidden();
});

test('given a user with only the feedback:read permission, when visiting the feedback management page, then feedback is shown without actions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['view dashboard', 'feedback:read']);

    $feedback = Feedback::factory()->create(['position' => 'JAX_TWR']);

    $response = $this->actingAs($user)->get(route('admin.feedback.index'));

    $response->assertOk();
    $response->assertSee('JAX_TWR');
    $response->assertDontSee('Stash');
});

test('given a user with only the feedback:read permission, when stashing feedback, then the request is forbidden', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['view dashboard', 'feedback:read']);

    $feedback = Feedback::factory()->create();

    $response = $this->actingAs($user)->put(route('admin.feedback.stash', [$feedback]));

    $response->assertForbidden();
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::PENDING);
});

test('given a user with only the feedback:read permission, when posting a staff comment, then the request is forbidden', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['view dashboard', 'feedback:read']);

    $feedback = Feedback::factory()->create();

    $response = $this->actingAs($user)->post(route('admin.feedback.comments.store', [$feedback]), [
        'comment' => 'Trying to comment without write access',
    ]);

    $response->assertForbidden();
    expect($feedback->fresh()->staffComments)->toBeEmpty();
});

test('given an admin, when searching feedback, then only matching entries are shown', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $matchingController = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Zuluair']);
    $otherController = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Yankeetown']);
    Feedback::factory()->create(['controller_id' => $matchingController->id]);
    Feedback::factory()->create(['controller_id' => $otherController->id]);

    $response = $this->actingAs($admin)->get(route('admin.feedback.index', ['search' => 'Zuluair']));

    $response->assertOk();
    $response->assertSee('Zuluair');
    $response->assertDontSee('Yankeetown');
});

test('given an admin, when stashing pending feedback, then its status becomes stashed', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.feedback.stash', $feedback));

    $response->assertRedirect(route('admin.feedback.index'));
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::STASHED);
});

test('given an admin, when unstashing stashed feedback, then its status returns to pending', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create(['status' => FeedbackStatus::STASHED]);

    $response = $this->actingAs($admin)->put(route('admin.feedback.unstash', $feedback));

    $response->assertRedirect(route('admin.feedback.index'));
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::PENDING);
});

test('given an admin, when releasing feedback, then its status becomes released and the webhook job is dispatched', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.feedback.release', $feedback));

    $response->assertRedirect(route('admin.feedback.index'));
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::RELEASED);
    Queue::assertPushed(SendFeedbackToWebhook::class, fn ($job) => $job->feedback->is($feedback));
});

test('given already released feedback, when releasing it again, then no webhook or emails are re-sent', function () {
    Queue::fake();
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create(['status' => FeedbackStatus::RELEASED]);

    $response = $this->actingAs($admin)->put(route('admin.feedback.release', $feedback));

    $response->assertRedirect(route('admin.feedback.index'));
    $response->assertSessionHas('error');
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::RELEASED);
    Queue::assertNotPushed(SendFeedbackToWebhook::class);
    Mail::assertNothingQueued();
});

test('given a non-admin user, when stashing feedback, then the request is forbidden and the status is unchanged', function () {
    $user = User::factory()->create();
    $user->assignRole('rostered');

    $feedback = Feedback::factory()->create();

    $response = $this->actingAs($user)->put(route('admin.feedback.stash', $feedback));

    $response->assertForbidden();
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::PENDING);
});

// Staff comments

test('given an admin, when viewing feedback, then its staff comments are shown', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create();
    $feedback->staffComments()->create([
        'user_id' => $admin->id,
        'comment' => 'Discussed with the training team.',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.feedback.show', $feedback));

    $response->assertOk();
    $response->assertSee('Discussed with the training team.');
    $response->assertSee($admin->name);
});

test('given an admin, when posting a staff comment, then it is stored against the feedback and their user', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.feedback.comments.store', $feedback), [
        'comment' => 'Following up with the controller.',
    ]);

    $response->assertRedirect(route('admin.feedback.show', $feedback));
    $response->assertSessionHas('success');

    $comment = $feedback->staffComments()->sole();
    expect($comment->user_id)->toBe($admin->id)
        ->and($comment->comment)->toBe('Following up with the controller.');
});

test('given an admin, when posting an empty staff comment, then a comment error is returned and nothing is stored', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.feedback.comments.store', $feedback), [
        'comment' => '',
    ]);

    $response->assertSessionHasErrors('comment');
    expect($feedback->staffComments()->count())->toBe(0);
});

// Feedback on controller profile

test('given released feedback, when the controller views their own profile, then the feedback is shown without the submitter identity', function () {
    $feedback = Feedback::factory()->create([
        'position' => 'JAX_TWR',
        'comments' => 'Great service on tower frequency.',
        'status' => FeedbackStatus::RELEASED,
    ]);

    $response = $this->actingAs($feedback->controller)->get(route('users.show.feedback', [$feedback->controller_id]));

    $response->assertOk();
    $response->assertSee('Great service on tower frequency.');
    $response->assertSee('JAX_TWR');
    $response->assertDontSee($feedback->user->name);
});

test('given pending and stashed feedback, when the controller views their own profile, then that feedback is not shown', function () {
    $controller = User::factory()->create();
    Feedback::factory()->create([
        'controller_id' => $controller->id,
        'comments' => 'Pending feedback comment text.',
    ]);
    Feedback::factory()->create([
        'controller_id' => $controller->id,
        'comments' => 'Stashed feedback comment text.',
        'status' => FeedbackStatus::STASHED,
    ]);

    $response = $this->actingAs($controller)->get(route('users.show.feedback', [$controller->id]));

    $response->assertOk();
    $response->assertDontSee('Pending feedback comment text.');
    $response->assertDontSee('Stashed feedback comment text.');
});

test('given a guest, when visiting a controller feedback page, then they are redirected to login', function () {
    $controller = User::factory()->create(['rostered' => true]);

    $response = $this->get(route('users.show.feedback', [$controller->id]));

    $response->assertRedirect(route('login'));
});

test('given another user, when visiting a controller feedback page, then the request is forbidden', function () {
    $controller = User::factory()->create(['rostered' => true]);
    $other = User::factory()->create();

    Feedback::factory()->create([
        'controller_id' => $controller->id,
        'comments' => 'Released feedback comment text.',
        'status' => FeedbackStatus::RELEASED,
    ]);

    $response = $this->actingAs($other)->get(route('users.show.feedback', [$controller->id]));

    $response->assertForbidden();
});

test('given a staff member with feedback read permission, when visiting a controller feedback page, then the feedback is shown', function () {
    $controller = User::factory()->create(['rostered' => true]);
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    Feedback::factory()->create([
        'controller_id' => $controller->id,
        'comments' => 'Released feedback comment text.',
        'status' => FeedbackStatus::RELEASED,
    ]);

    $response = $this->actingAs($staff)->get(route('users.show.feedback', [$controller->id]));

    $response->assertOk();
    $response->assertSee('Released feedback comment text.');
});

test('given another user, when visiting a controller profile, then the feedback tab is not shown', function () {
    $controller = User::factory()->create(['rostered' => true]);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->get(route('users.show', [$controller->id]));

    $response->assertOk();
    $response->assertDontSee(route('users.show.feedback', [$controller->id]));
});

// Email notifications & user-visible comments (issue #179)

test('given an admin, when releasing feedback, then the submitter is emailed', function () {
    Queue::fake();
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create();

    $this->actingAs($admin)->put(route('admin.feedback.release', [$feedback]));

    Mail::assertQueued(FeedbackReleased::class, function ($mail) use ($feedback) {
        return $mail->hasTo($feedback->user->email) && $mail->feedback->is($feedback);
    });
});

test('given an admin, when posting a user-visible comment, then it is stored as visible and the submitter is emailed', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.feedback.comments.store', [$feedback]), [
        'comment' => 'This will be shared with the submitter.',
        'user_visible' => '1',
    ]);

    $response->assertRedirect(route('admin.feedback.show', [$feedback]));

    $comment = $feedback->staffComments()->sole();
    expect($comment->user_visible)->toBeTrue();

    Mail::assertQueued(FeedbackCommentPosted::class, function ($mail) use ($feedback) {
        return $mail->hasTo($feedback->user->email);
    });
});

test('given an admin, when posting a comment without marking it visible, then it is internal and no email is sent', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create();

    $this->actingAs($admin)->post(route('admin.feedback.comments.store', [$feedback]), [
        'comment' => 'Internal note only.',
    ]);

    $comment = $feedback->staffComments()->sole();
    expect($comment->user_visible)->toBeFalse();

    Mail::assertNotQueued(FeedbackCommentPosted::class);
});

test('given a user with feedback, when visiting the feedback page, then their feedback and visible staff comments are shown', function () {
    $user = User::factory()->create();
    $staff = User::factory()->create();

    $feedback = Feedback::factory()->create([
        'user_id' => $user->id,
        'comments' => 'My original feedback text.',
    ]);
    $feedback->staffComments()->create([
        'user_id' => $staff->id,
        'comment' => 'A visible staff reply.',
        'user_visible' => true,
    ]);
    $feedback->staffComments()->create([
        'user_id' => $staff->id,
        'comment' => 'An internal staff note.',
        'user_visible' => false,
    ]);

    $otherFeedback = Feedback::factory()->create(['comments' => 'Somebody elses feedback.']);

    $response = $this->actingAs($user)->get(route('feedback.index'));

    $response->assertOk();
    $response->assertSee('My original feedback text.');
    $response->assertSee('A visible staff reply.');
    $response->assertDontSee('An internal staff note.');
    $response->assertDontSee('Somebody elses feedback.');
});

test('given an admin, when releasing feedback, then the controller is emailed', function () {
    Queue::fake();
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);

    $feedback = Feedback::factory()->create();

    $this->actingAs($admin)->put(route('admin.feedback.release', [$feedback]));

    Mail::assertQueued(FeedbackReceived::class, function ($mail) use ($feedback) {
        return $mail->hasTo($feedback->controller->email) && $mail->feedback->is($feedback);
    });
});

test('given a rostered controller, when submitting feedback, then the request is forbidden', function () {
    $rostered = User::factory()->create();
    $rostered->assignRole('rostered');

    $controller = User::factory()->create(['rostered' => true]);

    $response = $this->actingAs($rostered)->post(route('feedback.store'), [
        'controller_id' => $controller->id,
        'position' => 'JAX_TWR',
        'experience' => 'Good',
        'comments' => 'Should not be allowed.',
    ]);

    $response->assertForbidden();
    expect(Feedback::count())->toBe(0);
});

test('given a rostered controller, when visiting the feedback page, then the submit form is not shown', function () {
    $rostered = User::factory()->create();
    $rostered->assignRole('rostered');

    $response = $this->actingAs($rostered)->get(route('feedback.index'));

    $response->assertOk();
    $response->assertSee('Rostered controllers cannot submit feedback.');
    $response->assertDontSee('Submit Feedback</button>', false);
});
