<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:deactivate-groups')
    ->yearlyOn(8, 15, '00:00')
    ->timezone('Europe/Rome');

// Elimina i file temporanei dell'importazione calendario più vecchi di 24 ore.
Schedule::call(function () {
    $disk = Storage::disk('local');
    $limit = now()->subDay()->getTimestamp();

    foreach ($disk->files('imports') as $file) {
        if ($disk->lastModified($file) < $limit) {
            $disk->delete($file);
        }
    }
})->daily()->name('prune-calendar-imports');
