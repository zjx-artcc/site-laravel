{{--
    Flash and validation messages.

    Lives in a component because both layouts need it: `layouts.admin` stopped
    extending `layouts.main` and silently lost its only copy, so no admin page
    showed a success or error message at all.

    Seeded from the session on render, then open to anything dispatching a
    `notify` window event with `{ type, message }`.
--}}
<div
    x-data="{
        alerts: [
            @if ($errors->any())
                { id: 'validation', type: 'error', message: @js(implode(' ', $errors->all())) },
            @endif
            @if (session('error'))
                { id: 'session-error', type: 'error', message: @js(session('error')) },
            @endif
            @if (session('success'))
                { id: 'session-success', type: 'success', message: @js(session('success')) },
            @endif
        ]
    }"
    x-on:notify.window="alerts.push({ id: Date.now() + '-' + Math.random(), type: $event.detail.type ?? 'info', message: $event.detail.message })"
    class="flex flex-col gap-2 px-2 sm:px-0"
>
    <template x-for="alert in alerts" :key="alert.id">
        <div
            role="alert"
            class="alert"
            :class="{
                'alert-error': alert.type === 'error',
                'alert-success': alert.type === 'success',
                'alert-warning': alert.type === 'warning',
                'alert-info': alert.type === 'info',
            }"
        >
            <span x-text="alert.message"></span>
            <button type="button" class="btn btn-ghost btn-sm" @click="alerts = alerts.filter(a => a.id !== alert.id)">Close</button>
        </div>
    </template>
</div>
