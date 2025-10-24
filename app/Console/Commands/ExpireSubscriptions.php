<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Enums\SubscriptionStatus;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Mark cancelled subscriptions as expired after their end date';

    public function handle()
    {
        Subscription::where('status', SubscriptionStatus::ACTIVE->value)
            ->whereNotNull('cancelled_at')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->update(['status' => SubscriptionStatus::CANCELLED->value]);
        
        return Command::SUCCESS;
    }
}