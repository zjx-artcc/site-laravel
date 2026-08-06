<?php

namespace App\Http\Controllers\Training;

use App\Enums\TrainingStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CreateVatusaSoloCert;
use App\Jobs\RevokeVatusaSoloCert;
use App\Mail\SoloCertIssued;
use App\Mail\SoloCertRevoked;
use App\Models\SoloCert;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SoloCertController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $query = $request->input('search');
        $soloCerts = SoloCert::search($query)->orderBy('created_at', 'desc')->paginate(25);

        return view('solo-certs.index', compact('soloCerts'));
    }

    public function show(int $id)
    {
        $soloCert = SoloCert::findOrFail($id);
    }

    public function create()
    {
        $users = User::where(['rostered' => true])->get();

        return view('solo-certs.create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'userId' => 'required|exists:users,id',
            'position' => ['required', 'regex:/^([A-Z]{2,3})(_([A-Z]{1,3}))?_(DEL|GND|TWR|APP|DEP|CTR)$/'],
        ]);

        if ($validated['userId'] == Auth::user()->id) {
            return redirect()->back()->withErrors(['userId' => 'You cannot issue a solo certification to yourself.'])->withInput();
        }

        $soloCert = SoloCert::create([
            'user_id' => $validated['userId'],
            'issued_by_id' => Auth::user()->id,
            'position' => $validated['position'],
        ]);

        $relaventAssignments = TrainingAssignment::where([
            'user_id' => $validated['userId'],
            'active' => true,
        ])->get();

        foreach ($relaventAssignments as $relaventAssignment) {
            $relaventAssignment->status = TrainingStatus::SOLO;
            $relaventAssignment->save();
        }

        CreateVatusaSoloCert::dispatch($soloCert);
        Mail::to($soloCert->user)->bcc([$soloCert->issuedBy, config('app.vatusa_facility').'-ta@vatusa.net'])->queue(new SoloCertIssued($soloCert));

        Log::info('Solo cert issued', [
            'solo_cert_id' => $soloCert->id,
            'issued_by' => Auth::user()->id,
            'user_id' => $validated['userId'],
            'position' => $validated['position'],
        ]);

        return redirect(route('solo-certs.index'))->with('success', 'Solo certification created successfully.');
    }

    public function destroy(int $id)
    {
        $soloCert = SoloCert::findOrFail($id);

        if (! \Auth::user()->hasPermissionTo('revoke solo certs')) {
            abort(403, 'You do not have permission to revoke solo certifications.');
        }

        RevokeVatusaSoloCert::dispatch($soloCert);

        $relaventAssignments = TrainingAssignment::where([
            'user_id' => $soloCert->user_id,
            'active' => true,
        ])->get();

        foreach ($relaventAssignments as $relaventAssignment) {
            $relaventAssignment->status = TrainingStatus::ACTIVE;
            $relaventAssignment->save();
        }

        $soloCert->revoked = true;
        $soloCert->save();

        Mail::to($soloCert->user)->bcc([$soloCert->issuedBy, config('app.vatusa_facility').'-ta@vatusa.net'])->queue(new SoloCertRevoked($soloCert));

        Log::info('Solo cert revoked', [
            'solo_cert_id' => $soloCert->id,
            'user_id' => $soloCert->user_id,
            'position' => $soloCert->position,
            'revoked_by' => Auth::user()->id,
        ]);

        return redirect(route('solo-certs.index'))->with('success', 'Solo certification revoked successfully.');
    }
}
