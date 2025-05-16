<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Installment;
use App\Notifications\InstallmentDueNotification;
use Carbon\Carbon;

class SendInstallmentDueNotifications extends Command
{
    protected $signature = 'installments:notify-due';
    protected $description = 'Send notifications for due installments';

    public function handle()
    {
        $today = Carbon::today();
        $installments = Installment::where('due_date', '<=', $today)
            ->where('status', '!=', 'paid')
            ->get();
        foreach ($installments as $installment) {
            $user = $installment->installmentPlan->user ?? null;
            if ($user) {
                $user->notify(new InstallmentDueNotification($installment));
            }
        }
        $this->info('Installment due notifications sent.');
    }
}
