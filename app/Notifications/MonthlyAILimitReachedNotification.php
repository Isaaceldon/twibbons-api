<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonthlyAILimitReachedNotification extends Notification
{
    use Queueable;

    protected int $limit;

    public function __construct(int $limit = 3)
    {
        $this->limit = $limit;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Monthly AI Generation Limit Reached')
            ->line("You've reached your monthly limit of {$this->limit} AI frame generations.")
            ->line('To continue using AI-powered frame generation, consider upgrading to our premium plan.')
            ->action('Upgrade to Premium', url('/pricing'))
            ->line('Premium benefits:')
            ->line('✓ Unlimited AI generations')
            ->line('✓ Animated video twibbons')
            ->line('✓ Advanced statistics')
            ->line('✓ Team collaboration')
            ->line('✓ Priority support');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'ai_limit_reached',
            'limit' => $this->limit,
            'upgrade_required' => true,
            'message' => "You've reached your monthly AI generation limit ({$this->limit}).",
        ];
    }
}
