<?php

namespace App\Jobs;

use App\Mail\SendOTPCode as SendOTPCodeMail;
use App\Models\Tenant;
use App\Models\TenantOtp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class SendOTPCode implements NotTenantAware, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Tenant $tenant)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        TenantOtp::where('tenant_id', $this->tenant->id)->delete();

        TenantOtp::create([
            'tenant_id' => $this->tenant->id,
            'code' => $otp,
            'expire_at' => now()->addDay(),
            'sent_at' => now(),
        ]);

        Mail::to($this->tenant->email)->send(new SendOTPCodeMail($otp));
    }
}
