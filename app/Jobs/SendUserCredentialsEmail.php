<?php

namespace App\Jobs;

use App\Mail\SendUserCredentials;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendUserCredentialsEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $toEmail,
        public string $companyName,
        public string $email,
        public string $password,
        public string $link,
        public string $locale = 'en'
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        Mail::to($this->toEmail)->send(new SendUserCredentials(
            $this->companyName,
            $this->email,
            $this->password,
            $this->link,
            $this->locale
        ));
    }
}