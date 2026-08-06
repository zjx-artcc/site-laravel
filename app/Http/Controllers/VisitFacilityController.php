<?php

namespace App\Http\Controllers;

use App\Enums\VisitRequestStatus;
use App\Jobs\AddUserToVisitingRoster;
use App\Mail\VisitorRequestAccepted;
use App\Mail\VisitorRequestReceived;
use App\Mail\VisitorRequestRejected;
use App\Models\User;
use App\Models\VisitorRequest;
use App\Services\VisitingChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VisitFacilityController extends Controller
{
    public function index()
    {
        if (Auth::user()) {
            $hasActiveVisitRequest = VisitorRequest::where('user_id', Auth::user()->id)->exists();
        } else {
            $hasActiveVisitRequest = false;
        }

        return view('visit.index', compact('hasActiveVisitRequest'));
    }

    public function show(Request $request, int $visitRequest)
    {
        $request = VisitorRequest::findOrFail($visitRequest);

        return view('visit.show', ['request' => $request]);
    }

    public function manage(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $query = $request->input('search');

        $visitRequests = VisitorRequest::search($query)->orderBy('created_at', 'desc')->paginate(25);

        return view('visit.manage', ['visitRequests' => $visitRequests]);
    }

    public function create(Request $request, VisitingChecklistService $visitingChecklistService)
    {
        if (VisitorRequest::where('user_id', Auth::user()->id)->count() > 0) {
            return redirect()->route('visit.index')->with('error', 'You have already submitted a visit request.');
        }

        $cid = strval(Auth::user()->id);
        $checklist = $visitingChecklistService->getChecklistItems($cid);

        return view('visit.create', ['checklist' => $checklist]);
    }

    public function store(Request $request)
    {
        if (VisitorRequest::where('user_id', Auth::user()->id)->count() > 0) {
            return redirect()->route('visit.index')->with('error', 'You have already submitted a visit request.');
        }

        $validated = $request->validate([
            'personalNote' => 'required|string|max:1000',
        ]);

        $visitRequest = VisitorRequest::create([
            'user_id' => Auth::user()->id,
            'user_note' => $validated['personalNote'],
            'status' => VisitRequestStatus::PENDING,
        ]);

        Mail::to($visitRequest->user->email)->bcc(['atm@zjxartcc.org', 'datm@zjxartcc.org'])->queue(new VisitorRequestReceived($visitRequest));
        Log::info('Visit request submitted', [
            'visit_request_id' => $visitRequest->id,
            'user_id' => $visitRequest->user_id,
        ]);

        return redirect()->route('visit.index')->with('success', 'Visit request submitted successfully.');
    }

    public function deny(Request $request, int $visitRequest)
    {
        $validated = $request->validate([
            'adminNotes' => 'string|nullable|max:1000',
            'reason' => 'string|required|max:1000',
        ]);

        $visitRequest = VisitorRequest::findOrFail($visitRequest);

        if ($visitRequest->status !== VisitRequestStatus::PENDING) {
            return redirect()->back()->with('error', 'Only pending visit requests can be approved.');
        }

        $visitRequest->reason = $validated['reason'];
        $visitRequest->admin_notes = $validated['adminNotes'] ?? '';
        $visitRequest->status = VisitRequestStatus::DENIED;
        $visitRequest->save();

        Log::info('Visit request denied', [
            'visit_request_id' => $visitRequest->id,
            'user_id' => $visitRequest->user_id,
            'reason' => $validated['reason'],
            'admin_notes' => $validated['adminNotes'] ?? null,
            'denied_by' => Auth::user()->id,
        ]);
        Mail::to($visitRequest->user->email)->bcc(['atm@zjxartcc.org', 'datm@zjxartcc.org'])->queue(new VisitorRequestRejected($visitRequest));

        return redirect()->back()->with('success', 'Visit request denied.');
    }

    public function approve(Request $request, int $visitRequest)
    {
        $validated = $request->validate([
            'operatingInitials' => 'required|string|size:2',
            'adminNotes' => 'string|nullable|max:1000',
        ], [
            'operatingInitials.size' => 'Operating initials must be 2 characters long',
        ]);

        if (User::where('operating_initials', strtoupper($validated['operatingInitials']))->exists()) {
            return redirect()->back()->with('error', 'OIs already assigned.');
        }

        $visitRequest = VisitorRequest::findOrFail($visitRequest);

        if ($visitRequest->status !== VisitRequestStatus::PENDING) {
            return redirect()->back()->with('error', 'Only pending visit requests can be approved.');
        }

        $visitRequest->status = VisitRequestStatus::APPROVED;
        $visitRequest->admin_notes = $validated['adminNotes'] ?? '';
        $visitRequest->save();

        $visitRequest->user->operating_initials = $validated['operatingInitials'];
        $visitRequest->user->rostered = true;
        $visitRequest->user->save();

        Mail::to($visitRequest->user->email)->bcc(['atm@zjxartcc.org', 'datm@zjxartcc.org'])->queue(new VisitorRequestAccepted($visitRequest));
        AddUserToVisitingRoster::dispatch($visitRequest->user->id);
        Log::info('Visit request approved', [
            'visit_request_id' => $visitRequest->id,
            'user_id' => $visitRequest->user_id,
            'operating_initials' => strtoupper($validated['operatingInitials']),
            'admin_notes' => $validated['adminNotes'] ?? null,
            'approved_by' => Auth::user()->id,
        ]);

        return redirect()->back()->with('success', 'Visit request approved.');
    }
}
