<?php

namespace App\Jobs;

use App\Models\Group;
use App\Services\ContactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessGroupContacts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $maxExceptions = 3;

    protected Group $group;

    protected array $filters;

    /**
     * Create a new job instance.
     */
    public function __construct(Group $group, array $filters)
    {
        $this->group = $group;
        $this->filters = $filters;

        $this->onQueue('heavy');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        tenancy()->initialize($this->group->tenant);

        DB::table('contact_group')->where('group_id', $this->group->id)->delete();

        $contactsQuery = (new ContactService)->getFilteredQuery($this->filters);

        $totalProcessed = 0;

        $contactsQuery->select('id')->chunk(1000, function ($contacts) use (&$totalProcessed) {
            $pivotData = $contacts->map(function ($contact) {
                return [
                    'group_id' => $this->group->id,
                    'contact_id' => $contact->id,
                ];
            })->toArray();

            if (! empty($pivotData)) {
                DB::table('contact_group')->insert($pivotData);
                $totalProcessed += count($pivotData);
            }
        });

        $this->group->update([
            'contacts_count' => $totalProcessed,
        ]);
    }
}
