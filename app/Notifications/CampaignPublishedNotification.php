<?php
namespace App\Notifications;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CampaignPublishedNotification extends Notification
{
    use Queueable;

    protected Campaign $campaign;

    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Campaign is Now Live!')
            ->line("Congratulations! Your campaign \"{$this->campaign->title}\" is now published and live.")
            ->line("Public URL: {$this->campaign->getPublicUrl()}")
            ->action('View Campaign', $this->campaign->getPublicUrl())
            ->line('Share this link with your audience to start collecting twibbons!')
            ->line('You can track statistics in your dashboard.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'campaign_published',
            'campaign_id' => $this->campaign->id,
            'campaign_title' => $this->campaign->title,
            'public_url' => $this->campaign->getPublicUrl(),
            'message' => 'Your campaign is now live!',
        ];
    }
}
