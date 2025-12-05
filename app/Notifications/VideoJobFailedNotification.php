<?php
namespace App\Notifications;

use App\Models\VideoJob;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VideoJobFailedNotification extends Notification
{
    use Queueable;

    protected VideoJob $videoJob;
    protected string $errorMessage;

    public function __construct(VideoJob $videoJob, string $errorMessage)
    {
        $this->videoJob = $videoJob;
        $this->errorMessage = $errorMessage;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Video Processing Failed')
            ->line('We encountered an issue while processing your video twibbon.')
            ->line("Template: {$this->videoJob->videoTemplate->name}")
            ->line("Error: {$this->errorMessage}")
            ->action('Retry Generation', url("/video-jobs/{$this->videoJob->id}"))
            ->line('You can retry the generation or contact our support team.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'video_job_failed',
            'video_job_id' => $this->videoJob->id,
            'template_name' => $this->videoJob->videoTemplate->name,
            'error_message' => $this->errorMessage,
            'message' => 'Video processing failed. You can retry.',
        ];
    }
}
