<?php

namespace App\Http\Controllers;

use App\Enums\LoaStatus;
use App\Mail\LoaApproved;
use App\Mail\LoaDeleted;
use App\Mail\LoaDenied;
use App\Mail\LoaRevoked;
use App\Mail\LoaSubmitted;
use App\Models\Loa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LoaController extends Controller
{
    public function store(Request $request)
    {
        if (Loa::where('user_id', Auth::user()->id)->where('status', '!=', LoaStatus::INACTIVE)->exists()) {
            return redirect()->back()->with('error', 'You already have an active LOA request.');
        }

        $validated = $this->validateLoa($request);

        $loa = Loa::create([
            'user_id' => Auth::user()->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'status' => LoaStatus::PENDING,
        ]);

        Mail::to($loa->user->email)->bcc(config('app.vatusa_facility').'-ec@vatusa.net')->queue(new LoaSubmitted($loa));
        Log::info('LOA request submitted by user '.$loa->user_id);

        return redirect()->route('users.show', Auth::user())->with('success', 'LOA request submitted successfully.');
    }

    public function update(Request $request, int $loa)
    {
        $loa = Loa::findOrFail($loa);

        if (Auth::user()->id !== $loa->user_id) {
            return response('Unauthorized', 403);
        }

        if ($loa->status === LoaStatus::INACTIVE) {
            return redirect()->back()->with('error', 'This LOA is inactive and can no longer be modified.');
        }

        $validated = $this->validateLoa($request);

        $loa->start_date = $validated['start_date'];
        $loa->end_date = $validated['end_date'];
        $loa->reason = $validated['reason'];
        $loa->status = LoaStatus::PENDING;
        $loa->save();

        Log::info('LOA #'.$loa->id.' modified by user '.Auth::user()->id.'. Status reset to pending.');

        return redirect()->route('users.show', Auth::user())->with('success', 'LOA request updated and resubmitted for review.');
    }

    public function destroy(int $loa)
    {
        $loa = Loa::findOrFail($loa);

        if (Auth::user()->id !== $loa->user_id) {
            return response('Unauthorized', 403);
        }

        if ($loa->status === LoaStatus::INACTIVE) {
            return redirect()->back()->with('error', 'This LOA is already inactive.');
        }

        $loa->status = LoaStatus::INACTIVE;
        $loa->save();

        Mail::to($loa->user->email)->queue(new LoaDeleted($loa));
        Log::info('LOA #'.$loa->id.' cancelled by user '.Auth::user()->id);

        return redirect()->route('users.show', Auth::user())->with('success', 'LOA cancelled.');
    }

    public function manage(Request $request)
    {
        $loas = Loa::with('user')->orderByRaw('status = '.LoaStatus::PENDING->value.' desc')->orderBy('created_at', 'desc')->paginate(25);

        return view('loa.manage', ['loas' => $loas]);
    }

    public function show(int $loa)
    {
        $loa = Loa::with('user')->findOrFail($loa);

        return view('loa.show', ['loa' => $loa]);
    }

    public function approve(Request $request, int $loa)
    {
        $validated = $request->validate([
            'response' => 'nullable|string|max:1000',
        ]);

        $loa = Loa::findOrFail($loa);

        if ($loa->status !== LoaStatus::PENDING) {
            return redirect()->back()->with('error', 'Only pending LOA requests can be approved.');
        }

        $loa->status = LoaStatus::APPROVED;
        $loa->response = $validated['response'] ?? $loa->response;
        $loa->save();

        Mail::to($loa->user->email)->queue(new LoaApproved($loa));
        Log::info('LOA #'.$loa->id.' for user '.$loa->user_id.' approved by '.Auth::user()->id);

        return redirect()->route('loa.manage')->with('success', 'LOA approved.');
    }

    public function deny(Request $request, int $loa)
    {
        $validated = $request->validate([
            'response' => 'nullable|string|max:1000',
        ]);

        $loa = Loa::findOrFail($loa);

        if ($loa->status !== LoaStatus::PENDING) {
            return redirect()->back()->with('error', 'Only pending LOA requests can be denied.');
        }

        $loa->status = LoaStatus::DENIED;
        $loa->response = $validated['response'] ?? $loa->response;
        $loa->save();

        Mail::to($loa->user->email)->queue(new LoaDenied($loa));
        Log::info('LOA #'.$loa->id.' for user '.$loa->user_id.' denied by '.Auth::user()->id);

        return redirect()->route('loa.manage')->with('success', 'LOA denied.');
    }

    public function revoke(Request $request, int $loa)
    {
        $validated = $request->validate([
            'response' => 'nullable|string|max:1000',
        ]);

        $loa = Loa::findOrFail($loa);

        if ($loa->status !== LoaStatus::APPROVED) {
            return redirect()->back()->with('error', 'Only approved LOAs can be revoked.');
        }

        $loa->status = LoaStatus::INACTIVE;
        $loa->response = $validated['response'] ?? $loa->response;
        $loa->save();

        Mail::to($loa->user->email)->queue(new LoaRevoked($loa));
        Log::info('LOA #'.$loa->id.' for user '.$loa->user_id.' revoked by '.Auth::user()->id);

        return redirect()->route('loa.manage')->with('success', 'LOA revoked.');
    }

    private function validateLoa(Request $request): array
    {
        return $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => [
                'required',
                'date',
                'after:start_date',
                function ($attribute, $value, $fail) use ($request) {
                    if (Carbon::parse($request->input('start_date'))->diffInDays(Carbon::parse($value)) < 7) {
                        $fail('The end date must be at least 7 days after the start date.');
                    }
                },
            ],
            'reason' => 'required|string|max:1000',
        ]);
    }
}
