<?php

namespace App\Http\Middleware;

use App\Services\BackupManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** The first request of the day copies the database (SPEC §5.10). */
class BackupDaily
{
    public function __construct(private readonly BackupManager $backups) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->backups->dailyBackup();
        } catch (Throwable $exception) {
            // A failed copy must not keep the app from opening; it is in the log.
            report($exception);
        }

        return $next($request);
    }
}
