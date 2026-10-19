<?php

use App\Http\Middleware\BackupDaily;
use App\Services\BackupManager;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // A real SQLite file (":memory:" cannot be copied) and a storage folder of its own.
    $this->storage = temporaryFolder();
    $this->app->useStoragePath($this->storage);
    $database = temporaryFolder().DIRECTORY_SEPARATOR.'music.sqlite';
    touch($database);
    config(['database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true]]);
    config(['database.default' => 'backup_test']);
    DB::purge('backup_test');
    DB::statement('create table songs (id integer primary key, title text)');
    DB::table('songs')->insert(['title' => 'Sombra']);
});

// RefreshDatabase rolls back the default connection when the test ends: give it back its own.
afterEach(fn () => config(['database.default' => 'sqlite']));

it('makes one copy a day and keeps it', function () {
    $this->travelTo('2026-10-19 09:00');
    $backups = app(BackupManager::class);

    $file = $backups->dailyBackup();

    expect($file)->toBe($this->storage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'music_2026-10-19.sqlite')
        ->and(file_exists($file))->toBeTrue();

    $copy = new PDO("sqlite:{$file}");
    expect($copy->query('select title from songs')->fetchColumn())->toBe('Sombra');

    $mtime = filemtime($file);
    $this->travelTo('2026-10-19 23:00');

    expect($backups->dailyBackup())->toBe($file)->and(filemtime($file))->toBe($mtime);
});

it('copies on the first request of the day', function () {
    $this->travelTo('2026-10-20 08:00');

    app(BackupDaily::class)->handle(request(), fn () => response('ok'));

    expect(file_exists($this->storage.'/app/backups/music_2026-10-20.sqlite'))->toBeTrue();
});

it('makes a copy on request and keeps only the newest ones', function () {
    config(['music.backups_to_keep' => 3]);
    $backups = app(BackupManager::class);

    foreach (range(1, 5) as $minute) {
        $this->travelTo("2026-10-19 10:0{$minute}:00");
        $file = $backups->backupNow();
        touch($file, now()->getTimestamp());
    }

    expect(collect($backups->backups())->pluck('name')->all())->toBe([
        'music_2026-10-19_100500.sqlite',
        'music_2026-10-19_100400.sqlite',
        'music_2026-10-19_100300.sqlite',
    ]);
});

it('says copies are not possible with an in-memory database', function () {
    config(['database.default' => 'sqlite']);

    expect(app(BackupManager::class)->isAvailable())->toBeFalse()
        ->and(app(BackupManager::class)->dailyBackup())->toBeNull();
});
