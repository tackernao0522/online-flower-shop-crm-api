<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class UpdateOnlineStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-online-status';
    protected $description = 'Update online status of users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $offlineThreshold = Carbon::now()->subMinutes(5);
        User::where('is_online', true)
            ->where('last_activity', '<', $offlineThreshold)
            ->update(['is_online' => false]);

        $this->info('User online statuses updated successfully.');
    }
}
