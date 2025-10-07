<?php

namespace App\Jobs;

use App\Mail\SendVerifyAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendVerifyAccountEmail implements ShouldQueue
{
    use Queueable;

    public $queue = 'emails';

    public function __construct(public string $email, public string $link) {}

    public function handle(): void
    {
        Mail::to($this->email)->send(new SendVerifyAccount($this->link));
    }
}
