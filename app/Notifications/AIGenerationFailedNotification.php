<?php

namespace App\Notifications;

use App\Models\AiGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AIGenerationFailedNotification extends Notification
{
    use Queueable;

    protected AiGeneration $generation;
    protected string $errorMessage;

    public function __construct(AiGeneration $generation, string $errorMessage)
    {
        $this->generation = $generation;
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
            ->subject('AI Frame Generation Failed')
            ->line('Unfortunately, your AI frame generation could not be completed.')
            ->line("Prompt: \"{$this->generation->prompt}\"")
            ->line("Error: {$this->errorMessage}")
            ->action('Try Again', url("/ai-generations/{$this->generation->id}"))
            ->line('You can retry the generation or contact support if the issue persists.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'ai_generation_failed',
            'generation_id' => $this->generation->id,
            'error_message' => $this->errorMessage,
            'prompt' => $this->generation->prompt,
            'message' => 'AI generation failed. You can retry.',
        ];
    }
}
