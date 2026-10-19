<?php

namespace App\Console\Commands;

use App\Models\Group;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeactivateGroupsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deactivate-groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disattiva i gruppi di gioco creati prima del 15 agosto dell\'anno corrente';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoffDate = Carbon::create(now()->year, 8, 15, 0, 0, 0, 'Europe/Rome');

        $updatedCount = Group::where('is_active', true)
            ->where('created_at', '<', $cutoffDate)
            ->update(['is_active' => false]);

        $this->info("Disattivati {$updatedCount} gruppi attivi creati prima del {$cutoffDate->toDateString()}.");

        return self::SUCCESS;
    }
}
