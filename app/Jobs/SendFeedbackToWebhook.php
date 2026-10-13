<?php

namespace App\Jobs;

use App\Models\Feedback;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SendFeedbackToWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Feedback $feedback) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $webhookUrl = config('app.feedback_webhook_url');

        $payload = [
            'content' => null,
            'embeds' => [
                [
                    'title' => 'New Controller Feedback',
                    'description' => "Feedback has been released for {$this->feedback->controller->nameReversed} ({$this->feedback->controller_id}).",
                    'fields' => [
                        [
                            'name' => 'Position',
                            'value' => $this->feedback->position,
                            'inline' => false,
                        ],
                        [
                            'name' => 'Experience',
                            'value' => $this->feedback->experience->value,
                            'inline' => false,
                        ],
                        [
                            'name' => 'Comments',
                            'value' => $this->feedback->comments,
                            'inline' => false,
                        ],
                    ],
                    'color' => 5763719,
                ],
            ],
        ];

        Http::post($webhookUrl, $payload);
    }
}
