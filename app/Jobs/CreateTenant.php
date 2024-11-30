<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;

class CreateTenant implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Tenant $tenant,
        public $password,
        public $email,
        public $cellphoneNumber,
        public $cellphonePrefix
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::setDefaultConnection('tenant');

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        User::create([
            'name' => $this->tenant->name,
            'email' => $this->tenant->email,
            'password' => Hash::make($this->password),
            'cellphone_number' => $this->cellphoneNumber,
            'cellphone_prefix' => $this->cellphonePrefix,
        ]);

        (new ClientRepository)->createPersonalAccessClient(
            null, 'Client fo '.$this->tenant->business_name, ''
        );
    }
}
