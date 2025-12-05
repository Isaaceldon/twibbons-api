<?php
namespace App\Notifications;

use App\Models\AiGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AIGenerationCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected AiGeneration $generation;

    public function __construct(AiGeneration $generation)
    {
        $this->generation = $generation;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $resultsCount = count($this->generation->results);

        return (new MailMessage)
            ->subject('Your AI Frame Generation is Ready!')
            ->line("Great news! Your AI generation has been completed successfully.")
            ->line("We've generated {$resultsCount} frame variations based on your prompt:")
            ->line("\"{$this->generation->prompt}\"")
            ->action('View Results', url("/ai-generations/{$this->generation->id}"))
            ->line('Generation time: ' . round($this->generation->generation_time, 2) . ' seconds')
            ->line('Thank you for using Twibbons!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'ai_generation_completed',
            'generation_id' => $this->generation->id,
            'results_count' => count($this->generation->results),
            'generation_time' => $this->generation->generation_time,
            'prompt' => $this->generation->prompt,
            'message' => 'Your AI frame generation is ready!',
        ];
    }
}
