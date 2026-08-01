<?php

use Illuminate\Foundation\Inspiring;
use App\Console\Commands\SeedDemoGeographyAnalyticsCommand;
use App\Models\CallSession;
use Illuminate\Support\Facades\Artisan;

Artisan::registerCommand(app(SeedDemoGeographyAnalyticsCommand::class));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('calls:cleanup-stale', function () {
    $now = now();
    $ringingCutoff = $now->copy()->subSeconds((int) config('calls.ringing_timeout_seconds', 45));
    $acceptedCutoff = $now->copy()->subMinutes((int) config('calls.accepted_cleanup_minutes', 180));

    $missed = CallSession::where('status', 'ringing')
        ->where('started_at', '<=', $ringingCutoff)
        ->update([
            'status' => 'missed',
            'ended_at' => $now,
        ]);

    $ended = CallSession::whereIn('status', ['accepted', 'active'])
        ->where('started_at', '<=', $acceptedCutoff)
        ->update([
            'status' => 'ended',
            'ended_at' => $now,
        ]);

    $this->info("Cleaned {$missed} missed ringing call(s) and {$ended} stale accepted call(s).");
})->purpose('Expire stale LuxurrStay call sessions');
