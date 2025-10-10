<?php

namespace App\Jobs;

use App\Mail\SendVerifyAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendVerifyAccountEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $email,
        public string $link,
        public string $name,
        public string $locale = 'en'
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(new SendVerifyAccount(
            $this->link,
            $this->name,
            $this->locale
        ));
    }
}
