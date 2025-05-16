<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Installment;

class InstallmentDueNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public $installment;
    public function __construct(Installment $installment)
    {
        $this->installment = $installment;
    }
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تنبيه: قسط مستحق')
            ->line('لديك قسط مستحق بتاريخ: ' . $this->installment->due_date)
            ->line('المبلغ: ' . $this->installment->amount)
            ->action('عرض القسط', url('/'));
    }
    public function toArray($notifiable)
    {
        return [
            'installment_id' => $this->installment->id,
            'due_date' => $this->installment->due_date,
            'amount' => $this->installment->amount,
        ];
    }
}
