<?php

namespace App\Jobs;

use App\Mail\SendUserInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendUserInvitationEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $toEmail,
        public string $companyName,
        public string $userName,
        public string $setPasswordLink,
        public string $locale = 'en'
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        Mail::to($this->toEmail)->send(new SendUserInvitation(
            $this->companyName,
            $this->userName,
            $this->setPasswordLink,
            $this->locale
        ));
    }
}
