<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackExperience;
use App\Enums\FeedbackStatus;
use App\Jobs\SendFeedbackToWebhook;
use App\Mail\FeedbackCommentPosted;
use App\Mail\FeedbackReceived;
use App\Mail\FeedbackReleased;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $controllers = User::where('rostered', true)->orderBy('last_name')->get();

        $myFeedback = Feedback::where('user_id', $request->user()->id)
            ->with(['controller', 'visibleComments.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('feedback.index', [
            'controllers' => $controllers,
            'experiences' => FeedbackExperience::cases(),
            'myFeedback' => $myFeedback,
        ]);
    }

    public function manage(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $feedback = Feedback::search($request->input('search'))->paginate(25);

        return view('manage-feedback.index', ['feedback' => $feedback]);
    }

    public function stash(Feedback $feedback)
    {
        $feedback->update(['status' => FeedbackStatus::STASHED]);

        return redirect()->route('admin.feedback.index')->with('success', 'Feedback stashed.');
    }

    public function unstash(Feedback $feedback)
    {
        $feedback->update(['status' => FeedbackStatus::PENDING]);

        return redirect()->route('admin.feedback.index')->with('success', 'Feedback returned to pending.');
    }

    public function release(Feedback $feedback)
    {
        // Conditional update so concurrent submits can't both win the race and
        // double-send the webhook and emails.
        $claimed = Feedback::whereKey($feedback->getKey())
            ->where('status', '!=', FeedbackStatus::RELEASED)
            ->update(['status' => FeedbackStatus::RELEASED]);

        if ($claimed === 0) {
            return redirect()->route('admin.feedback.index')->with('error', 'That feedback has already been released.');
        }

        $feedback->refresh();

        SendFeedbackToWebhook::dispatch($feedback);
        Mail::to($feedback->user)->queue(new FeedbackReleased($feedback));
        Mail::to($feedback->controller)->queue(new FeedbackReceived($feedback));

        return redirect()->route('admin.feedback.index')->with('success', 'Feedback released.');
    }

    public function show(Feedback $feedback)
    {
        $feedback->load(['user', 'controller', 'staffComments.user']);

        return view('manage-feedback.show', ['feedback' => $feedback]);
    }

    public function storeComment(Request $request, Feedback $feedback)
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'user_visible' => ['nullable', 'boolean'],
        ]);

        $comment = $feedback->staffComments()->create([
            'user_id' => $request->user()->id,
            'comment' => $validated['comment'],
            'user_visible' => $request->boolean('user_visible'),
        ]);

        if ($comment->user_visible) {
            Mail::to($feedback->user)->queue(new FeedbackCommentPosted($comment));
        }

        return redirect()->route('admin.feedback.show', [$feedback])->with('success', 'Comment posted.');
    }

    public function store(Request $request)
    {
        if ($request->user()->hasRole('rostered')) {
            abort(403, 'Rostered controllers cannot submit feedback.');
        }

        $validated = $request->validate([
            'controller_id' => ['required', Rule::exists('users', 'id')->where('rostered', true)],
            'position' => ['required', 'string', 'max:255'],
            'experience' => ['required', Rule::enum(FeedbackExperience::class)],
            'staff_followup' => ['nullable', 'boolean'],
            'comments' => ['required', 'string', 'max:5000'],
        ]);

        Feedback::create([
            'user_id' => $request->user()->id,
            'controller_id' => $validated['controller_id'],
            'position' => $validated['position'],
            'experience' => $validated['experience'],
            'staff_followup' => $request->boolean('staff_followup'),
            'comments' => $validated['comments'],
        ]);

        return redirect()->route('feedback.index')->with('success', 'Thank you! Your feedback has been submitted.');
    }
}
