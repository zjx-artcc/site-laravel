<?php

namespace App\Http\Controllers;

use App\Mail\StaffingRequestClosed;
use App\Mail\StaffingRequestSubmitted;
use App\Models\StaffingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StaffingRequestController extends Controller
{
    public function index()
    {
        return view('staffing-requests.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'requested_at' => 'required|date',
        ]);

        $staffingRequest = StaffingRequest::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'requested_at' => $validated['requested_at'],
        ]);

        Mail::to($staffingRequest->user)->queue(new StaffingRequestSubmitted($staffingRequest));
        Log::info('New staffing request submitted by '.$request->user()->id.': '.$staffingRequest->name);

        return redirect()->route('staffing-requests.index')->with('success', 'Thank you! Your staffing request has been submitted. Our events team will follow up by email.');
    }

    public function manage(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $staffingRequests = StaffingRequest::search($request->input('search'))
            ->orderBy('closed')
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('manage-staffing-requests.index', ['staffingRequests' => $staffingRequests]);
    }

    public function show(StaffingRequest $staffingRequest)
    {
        $staffingRequest->load('user');

        return view('manage-staffing-requests.show', ['staffingRequest' => $staffingRequest]);
    }

    public function close(StaffingRequest $staffingRequest)
    {
        $staffingRequest->update(['closed' => true]);

        Mail::to($staffingRequest->user)->queue(new StaffingRequestClosed($staffingRequest));
        Log::info('Staffing request "'.$staffingRequest->name.'" closed by '.Auth::user()->id);

        return redirect()->route('admin.staffing-requests.index')->with('success', 'Staffing request closed.');
    }

    public function reopen(StaffingRequest $staffingRequest)
    {
        $staffingRequest->update(['closed' => false]);

        Log::info('Staffing request "'.$staffingRequest->name.'" reopened by '.Auth::user()->id);

        return redirect()->route('admin.staffing-requests.index')->with('success', 'Staffing request reopened.');
    }
}
