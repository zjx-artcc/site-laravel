<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records a privileged ("sudo") staff action to both audit trails:
 *
 *  1. The `activity_log` table, so the action shows up in the admin audit-log
 *     viewer alongside model changes (see docs/systems/audit-logging.md).
 *  2. The application log at info level, so it is present in whatever the
 *     `LOG_STACK` is shipping to (file, Discord, syslog, ...) even if the
 *     database is later lost or rolled back.
 *
 * Use this for actions that change someone *else's* standing at the facility —
 * visitor acceptances/denials, roster removals, solo cert issue/revoke,
 * training assignment changes, and deletions of admin-managed records.
 */
final class PrivilegedAction
{
    /**
     * Action name constants. These become the activity `event` value, which is
     * what the audit-log viewer renders as the action badge, so keep them
     * short, lowercase and dot-namespaced.
     */
    public const VISITOR_REQUEST_APPROVED = 'visitor.approved';

    public const VISITOR_REQUEST_DENIED = 'visitor.denied';

    public const VISITOR_REQUEST_SUBMITTED = 'visitor.submitted';

    public const SOLO_CERT_ISSUED = 'solo-cert.issued';

    public const SOLO_CERT_REVOKED = 'solo-cert.revoked';

    public const TRAINING_ASSIGNMENT_UPDATED = 'training-assignment.updated';

    public const TRAINING_ASSIGNMENT_CLAIMED = 'training-assignment.claimed';

    public const TRAINING_ASSIGNMENT_DROPPED = 'training-assignment.dropped';

    public const TRAINING_ASSIGNMENT_FORFEITED = 'training-assignment.forfeited';

    public const CONTRIBUTOR_ADDED = 'contributor.added';

    public const CONTRIBUTOR_REMOVED = 'contributor.removed';

    public const STATISTICS_SYNC_QUEUED = 'statistics.sync-queued';

    public const ROSTER_USER_REMOVED = 'roster.user-removed';

    /**
     * Record a privileged action.
     *
     * @param  string  $action  One of the constants above (the activity `event`).
     * @param  Model|null  $subject  The record the action was performed on, if any.
     * @param  array<string, mixed>  $properties  Action detail rendered in the audit-log viewer.
     * @param  Model|null  $actor  Overrides the authenticated user; pass explicitly
     *                             for actions taken by a job or console command.
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?Model $actor = null,
    ): void {
        $actor ??= Auth::user();
        $context = self::requestContext();

        self::recordToActivityLog($action, $subject, $properties, $actor, $context);

        Log::info("privileged action: {$action}", array_merge([
            'action' => $action,
            'actor_cid' => $actor?->getKey(),
            'actor_name' => $actor->name ?? null,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
        ], $properties, $context));
    }

    /**
     * Write the activity_log row.
     *
     * The write is deliberately non-fatal: an audit-log failure must not turn a
     * successful staff action into a 500. If it fails we escalate to `critical`
     * on the application log so the gap in the database trail is itself visible.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $context
     */
    private static function recordToActivityLog(
        string $action,
        ?Model $subject,
        array $properties,
        ?Model $actor,
        array $context,
    ): void {
        try {
            $activity = activity()->event($action)->withProperties([
                // The viewer and CSV export read `attributes`, so action detail
                // goes there; request metadata is kept separate to avoid
                // cluttering every row with IPs and route names.
                'attributes' => $properties,
                'context' => $context,
            ]);

            if ($actor) {
                $activity->causedBy($actor);
            }

            if ($subject) {
                $activity->performedOn($subject);
            }

            $activity->log($action);
        } catch (Throwable $e) {
            Log::critical("Failed to write privileged action to the audit log: {$action}", [
                'action' => $action,
                'actor_cid' => $actor?->getKey(),
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Forensic metadata about where the action came from.
     *
     * Presence of a resolved route — rather than `runningInConsole()` — is what
     * distinguishes a real HTTP request from a console command or queue worker.
     * A worker still has a `request()` instance, but it has no route, and
     * `runningInConsole()` is true under the test runner even for HTTP tests.
     *
     * @return array<string, mixed>
     */
    private static function requestContext(): array
    {
        $request = request();
        $route = $request->route();

        if ($route === null) {
            return ['source' => 'console'];
        }

        return [
            'source' => 'web',
            'ip' => $request->ip(),
            'route' => $route->getName() ?? $request->path(),
            'method' => $request->method(),
        ];
    }
}
