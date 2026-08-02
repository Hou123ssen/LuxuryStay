<?php

namespace App\Console\Commands;

use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Console\Command;

class ClearDemoGeographyAnalyticsCommand extends Command
{
    protected $signature = 'analytics:clear-demo-geography';

    protected $description = 'Clear local/testing-only demo geography analytics data.';

    public function handle(DemoAnalyticsDataService $demoData): int
    {
        if ($this->isProduction()) {
            $this->error('Refusing to clear demo geography analytics in production.');

            return self::FAILURE;
        }

        $deleted = $demoData->deleteDemoData();

        $this->info("Deleted {$deleted['users']} demo user(s).");
        $this->info("Deleted {$deleted['events']} demo analytics event(s).");

        return self::SUCCESS;
    }

    private function isProduction(): bool
    {
        return app()->environment('production') || config('app.env') === 'production';
    }
}
