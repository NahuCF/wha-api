<?php

namespace App\Jobs;

use App\Mail\SendPasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $toEmail,
        public string $name,
        public string $resetLink,
        public string $locale = 'en'
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        Mail::to($this->toEmail)->send(new SendPasswordReset(
            $this->name,
            $this->resetLink,
            $this->locale
        ));
    }
}
