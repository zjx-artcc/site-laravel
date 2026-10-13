<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Jobs\SyncTrainingTickets;
use App\Mail\TrainingTicketCreated;
use App\Models\CertificationFacility;
use App\Models\TrainingTicket;
use App\Models\User;
use App\Models\UserCertification;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stevebauman\Purify\Facades\Purify;

class TrainingTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $query = $request->input('search');
        $trainingTickets = TrainingTicket::search($query)->paginate(25);

        return view('training-tickets.index', compact('trainingTickets'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::where(['rostered' => true])->orderBy('last_name')->get();

        // Instructors with certifications:write may optionally issue a certification
        // alongside the ticket. Only load the level list for them.
        $certificationFacilities = Auth::user()->hasPermissionTo('certifications:write')
            ? CertificationFacility::orderBy('order')->with('certificationLevels')->get()
            : collect();

        return view('training-tickets.create', [
            'users' => $users,
            'certificationFacilities' => $certificationFacilities,
        ]);
        // /^([A-Z]{2,3})(_([A-Z]{1,3}))?_(DEL|GND|TWR|APP|DEP|CTR)$/
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $instructor = Auth::user();

        $validated = $request->validate([
            'student' => 'required|integer',
            'position' => ['required', 'regex:/^([A-Z]{2,3})(_([A-Z]{1,3}))?_(DEL|GND|TWR|APP|DEP|CTR)$/'],
            'location' => 'required|integer|min:0|max:2',
            'sessionStart' => 'required|date',
            'sessionEnd' => 'required|date|after:sessionStart',
            'movements' => 'required|integer',
            'score' => 'required|integer|between:1,5',
            // Notes come from the Quill editor as HTML, so an "empty" editor still
            // submits markup like "<p><br></p>". Reject anything with no real text.
            'notes' => ['required', 'string', function ($attribute, $value, $fail) {
                if (trim(strip_tags($value)) === '') {
                    $fail('The notes field cannot be empty.');
                }
            }],
            // Staff-only notes. Never shown to the student, never synced to VATUSA.
            'instructor_notes' => ['nullable', 'string'],
            'certification_level_id' => 'nullable|integer|exists:certification_levels,id',
        ]);

        if ($instructor->id == $validated['student']) {
            return redirect()->back()->withInput($request->input())->with('error', 'Cannot create training ticket with yourself as the student.');
        }

        // Only issue a certification if one was selected AND the instructor is allowed to.
        $issueCertification = ! empty($validated['certification_level_id'])
            && $instructor->hasPermissionTo('certifications:write');

        $ticket = new TrainingTicket([
            'user_id' => $validated['student'],
            'instructor_id' => $instructor->id,
            'position' => $validated['position'],
            'session_start' => $validated['sessionStart'],
            'session_end' => $validated['sessionEnd'],
            'movements' => $validated['movements'],
            'score' => $validated['score'],
            'notes' => Purify::clean($validated['notes']),
            'instructor_notes' => filled($validated['instructor_notes'] ?? null)
                ? Purify::clean($validated['instructor_notes'])
                : null,
            'location' => $validated['location'],
            'issued_certification_level_id' => $issueCertification ? $validated['certification_level_id'] : null,
        ]);

        $ticket->save();

        if ($issueCertification) {
            UserCertification::firstOrCreate([
                'user_id' => $validated['student'],
                'certification_level_id' => $validated['certification_level_id'],
            ]);
        }

        Mail::to($ticket->student->email)->bcc($ticket->instructor->email)->queue(new TrainingTicketCreated($ticket));
        Log::info('Training ticket created', [
            'ticket_id' => $ticket->id,
            'instructor_id' => $ticket->instructor_id,
            'student_id' => $ticket->user_id,
        ]);
        SyncTrainingTickets::dispatch();

        return redirect(route('training-tickets.show', [$ticket]))
            ->with('success', 'Training ticket successfully created.');
    }

    public function show(string $id)
    {
        $trainingTicket = TrainingTicket::findOrFail($id);

        $isStudent = Auth::id() === $trainingTicket->user_id;

        if (! $isStudent && ! Auth::user()->hasRole('training')) {
            abort(403);
        }

        // Training staff get the staff view (admin layout + instructor notes) only
        // when viewing someone else's ticket. On their own ticket they see the same
        // student view as everyone else, so instructor notes about them stay hidden.
        $staffView = ! $isStudent && Auth::user()->hasRole('training');

        return view('training-tickets.show', compact('trainingTicket', 'staffView'));
    }

    /**
     * Show the form for editing the specified resource.
     * !!!! NOT IN USE !!!!
     */
    public function edit(string $id)
    {
        $trainingTicket = TrainingTicket::findOrFail($id);

        if ($trainingTicket->vatusa_synced) {
            return back()->with('error', 'Cannot edit a training ticket that has been synced to VATUSA.');
        }

        return view('training-tickets.edit', ['trainingTicket' => $trainingTicket]);
    }

    /**
     * Update the specified resource in storage.
     * !!!! NOT IN USE !!!!
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'position' => ['required', 'regex:/^([A-Z]{2,3})(_([A-Z]{1,3}))?_(DEL|GND|TWR|APP|DEP|CTR)$/'],
            'location' => 'required|integer|min:0|max:2',
            'sessionStart' => 'required|date',
            'sessionEnd' => 'required|date|after:sessionStart',
            'movements' => 'required|integer',
            'score' => 'required|integer|between:1,5',
            'notes' => 'required|min:20',
            'instructor_notes' => ['nullable', 'string'],
        ]);

        $ticket = TrainingTicket::findOrFail($id);

        if ($ticket->vatusa_synced) {
            return back()->with('error', 'Cannot edit a training ticket that has been synced to VATUSA.');
        }

        $ticket->update([
            'position' => $validated['position'],
            'session_start' => $validated['sessionStart'],
            'session_end' => $validated['sessionEnd'],
            'movements' => $validated['movements'],
            'score' => $validated['score'],
            'notes' => Purify::clean($validated['notes']),
            'instructor_notes' => filled($validated['instructor_notes'] ?? null)
                ? Purify::clean($validated['instructor_notes'])
                : null,
            'location' => $validated['location'],
        ]);

        return redirect(route('training-tickets.show', [$id]))
            ->with('success', 'Training ticket successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
