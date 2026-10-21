<?php

namespace App\Http\Controllers;

use App\Enums\ControllerRating;
use App\Enums\LoaStatus;
use App\Jobs\RemoveUserFromRoster;
use App\Models\ControllerMonthlyStat;
use App\Models\Loa;
use App\Models\TrainingAssignment;
use App\Models\TrainingTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RosterPurgeController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request)
    {
        $data = $this->resolveData($request);
        $data['flagged'] = $this->paginate($data['flagged'], $request);

        return view('roster-purge.index', $data);
    }

    public function export(Request $request)
    {
        $data = $this->resolveData($request);

        $filename = "quarterly-flagged-q{$data['quarter']}-{$data['year']}-".Carbon::now()->utc()->format('Ymd-His').'Z.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Controller CID', 'FULL Name', 'Status', 'Hours (Activity)', 'Training Hrs (Student)', 'Training Hrs (Instructor)', 'Hours + Instructing', 'On LOA'], ',', '"', '\\');

            foreach ($data['flagged'] as $row) {
                fputcsv($handle, [
                    $row->user->id,
                    $row->user->name,
                    $this->statusLabel($row->user),
                    number_format($row->total, 2),
                    number_format($row->training_student, 2),
                    number_format($row->training_instructor, 2),
                    number_format($row->counted, 2),
                    $row->on_loa ? 'Yes' : 'No',
                ], ',', '"', '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function remove(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
            'reason' => 'required|string|max:255',
        ]);

        if (in_array(Auth::id(), array_map('intval', $validated['user_ids']), true)) {
            return redirect()->back()->with('error', 'You cannot remove yourself from the roster.');
        }

        // Controllers with an approved LOA are never purged, even if selected.
        $users = User::whereIn('id', $validated['user_ids'])
            ->whereNotIn('id', $this->approvedLoaIds()->keys())
            ->get();

        foreach ($users as $user) {
            RemoveUserFromRoster::dispatch($user->id, $validated['reason'], Auth::id());
            Log::info('Queued roster removal for user '.$user->id.' ('.$user->name.'). Reason: '.$validated['reason'].' by '.Auth::id());
        }

        return redirect()->back()->with('success', 'Queued removal for '.$users->count().' controller(s).');
    }

    private function resolveData(Request $request): array
    {
        $now = Carbon::now();
        $currentQuarter = intdiv($now->month - 1, 3) + 1;

        $year = (int) $request->query('year', $now->year);
        if ($year < 2000 || $year > 2100) {
            $year = $now->year;
        }

        $quarter = (int) $request->query('quarter', $currentQuarter);
        if ($quarter < 1 || $quarter > 4) {
            $quarter = $currentQuarter;
        }

        $threshold = (float) $request->query('threshold', 3);
        if ($threshold < 0) {
            $threshold = 3;
        }

        $cid = $request->query('cid');

        // LOA controllers are shown by default; the form sends show_loa=0 to hide them.
        $showLoa = $request->query('show_loa', '1') !== '0';

        // Optional: only flag controllers with fewer than this many training ticket hours.
        $maxTrainingParam = $request->query('max_training');
        $maxTraining = is_numeric($maxTrainingParam) && (float) $maxTrainingParam >= 0
            ? (float) $maxTrainingParam
            : null;

        $months = range(($quarter - 1) * 3 + 1, $quarter * 3);

        $years = ControllerMonthlyStat::select('year')->distinct()->orderBy('year', 'desc')->pluck('year');
        if (! $years->contains($now->year)) {
            $years = $years->prepend($now->year);
        }

        // Every rostered controller is considered, including those with no logged
        // hours at all (they count as 0h and are the most likely to be flagged).
        $users = User::where('rostered', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'rating', 'rostered', 'facility']);

        $hours = ControllerMonthlyStat::whereIn('user_id', $users->pluck('id'))
            ->where('year', $year)
            ->whereIn('month', $months)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->sum(fn ($r) => $r->totalHours()));

        $training = $this->trainingHours($users->pluck('id'), $year, $months);
        $loaIds = $this->approvedLoaIds();

        // Observers with an active training assignment are still working towards
        // their first rating, so they are never flagged.
        $observersInTraining = TrainingAssignment::where('active', true)->pluck('user_id')->flip();

        $rows = $users->map(function ($user) use ($hours, $training, $loaIds) {
            $trainingHours = $training->get($user->id, ['student' => 0.0, 'instructor' => 0.0]);

            return (object) [
                'user' => $user,
                'total' => $hours->get($user->id, 0),
                // Hours spent instructing count towards activity.
                'counted' => $hours->get($user->id, 0) + $trainingHours['instructor'],
                'training_student' => $trainingHours['student'],
                'training_instructor' => $trainingHours['instructor'],
                'training_total' => $trainingHours['student'] + $trainingHours['instructor'],
                'on_loa' => $loaIds->has($user->id),
                'loa_id' => $loaIds->get($user->id),
            ];
        });

        // Looking up a single controller always shows them, whatever the filters say.
        $flagged = $cid
            ? $rows->filter(fn ($row) => (string) $row->user->id === (string) $cid)
            : $rows
                ->filter(fn ($row) => $row->counted < $threshold)
                ->reject(fn ($row) => $row->user->rating === ControllerRating::OBS && $observersInTraining->has($row->user->id))
                ->when(! $showLoa, fn ($rows) => $rows->reject(fn ($row) => $row->on_loa))
                ->when($maxTraining !== null, fn ($rows) => $rows->filter(fn ($row) => $row->training_total < $maxTraining));

        $flagged = $flagged->sortBy('counted')->values();

        return [
            'flagged' => $flagged,
            'year' => $year,
            'quarter' => $quarter,
            'years' => $years,
            'threshold' => $threshold,
            'controllers' => $users->map(fn ($user) => (object) [
                'id' => $user->id,
                'name' => $user->name,
                'rating' => $user->rating,
            ])->values(),
            'cid' => $cid,
            'showLoa' => $showLoa,
            'maxTraining' => $maxTraining,
        ];
    }

    /**
     * Training ticket hours per controller for the quarter, split into time spent
     * as a student and as an instructor. Keyed by user id.
     */
    private function trainingHours(Collection $userIds, int $year, array $months): Collection
    {
        $start = Carbon::create($year, $months[0], 1)->startOfMonth();
        $end = Carbon::create($year, end($months), 1)->endOfMonth();

        $tickets = TrainingTicket::whereBetween('session_start', [$start, $end])
            ->where(fn ($q) => $q->whereIn('user_id', $userIds)->orWhereIn('instructor_id', $userIds))
            ->get(['user_id', 'instructor_id', 'session_start', 'session_end']);

        $hours = collect();

        foreach ($tickets as $ticket) {
            if (! $ticket->session_start || ! $ticket->session_end) {
                continue;
            }

            $seconds = Carbon::parse($ticket->session_end)->diffInSeconds(Carbon::parse($ticket->session_start), true);
            $duration = max(0, $seconds) / 3600;

            foreach (['student' => $ticket->user_id, 'instructor' => $ticket->instructor_id] as $role => $id) {
                $entry = $hours->get($id, ['student' => 0.0, 'instructor' => 0.0]);
                $entry[$role] += $duration;
                $hours->put($id, $entry);
            }
        }

        return $hours;
    }

    /**
     * Approved LOA ids keyed by user id (these users are exempt from the purge).
     */
    private function approvedLoaIds(): Collection
    {
        return Loa::where('status', LoaStatus::APPROVED)->pluck('id', 'user_id');
    }

    private function statusLabel(User $user): string
    {
        if (strcasecmp($user->facility, config('app.vatusa_facility')) === 0) {
            return 'Home';
        }

        return "Visitor ({$user->facility})";
    }

    private function paginate(Collection $items, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            $items->forPage($page, self::PER_PAGE)->values(),
            $items->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
