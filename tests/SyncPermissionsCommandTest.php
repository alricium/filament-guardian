<?php

use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Run Spatie permission migrations
    $migration = include __DIR__ . '/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';
    $migration->up();
});

it('does not prune when running without force option', function () {
    // Create a stale permission in the database
    Permission::create(['name' => 'stale_permission', 'guard_name' => 'web']);

    expect(Permission::where('name', 'stale_permission')->exists())->toBeTrue();

    // Run the sync command without force
    Artisan::call('guardian:sync');

    // Stale permission should still exist
    expect(Permission::where('name', 'stale_permission')->exists())->toBeTrue();
});

it('prunes stale permissions when running with force option', function () {
    // Create a stale permission in the database
    Permission::create(['name' => 'stale_permission', 'guard_name' => 'web']);

    expect(Permission::where('name', 'stale_permission')->exists())->toBeTrue();

    // Run the sync command with force
    $exitCode = Artisan::call('guardian:sync', ['--force' => true]);

    // Dump output for debugging if needed
    // fwrite(STDERR, "Artisan Exit Code: " . $exitCode . "\n");
    // fwrite(STDERR, "Artisan Output:\n" . Artisan::output() . "\n");

    // Stale permission should be deleted
    expect(Permission::where('name', 'stale_permission')->exists())->toBeFalse();
});
