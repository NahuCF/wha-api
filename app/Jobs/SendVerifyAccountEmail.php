<?php

namespace App\Jobs;

use App\Mail\SendVerifyAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendVerifyAccountEmail implements ShouldQueue
{
    public $queue = 'emails';  // High priority email queue

    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $email, public string $link)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->email)->send(new SendVerifyAccount($this->link));
    }
}
