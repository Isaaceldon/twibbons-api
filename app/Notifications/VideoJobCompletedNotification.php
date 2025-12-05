<?php
namespace App\Notifications;

use App\Models\VideoJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class VideoJobCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected VideoJob $videoJob;

    public function __construct(VideoJob $videoJob)
    {
        $this->videoJob = $videoJob;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Video Twibbon is Ready!')
            ->line('Excellent! Your animated video twibbon has been generated successfully.')
            ->line("Template: {$this->videoJob->videoTemplate->name}")
            ->line("Processing time: " . round($this->videoJob->processing_time, 2) . " seconds")
            ->action('Download Video', url("/video-jobs/{$this->videoJob->id}/download"))
            ->line('Your video is ready to share on social media!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'video_job_completed',
            'video_job_id' => $this->videoJob->id,
            'template_name' => $this->videoJob->videoTemplate->name,
            'processing_time' => $this->videoJob->processing_time,
            'download_url' => url("/video-jobs/{$this->videoJob->id}/download"),
            'message' => 'Your video twibbon is ready!',
        ];
    }
}
