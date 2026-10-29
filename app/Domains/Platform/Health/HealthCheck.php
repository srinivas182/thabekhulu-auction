<?php

namespace App\Domains\Platform\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks the services the platform depends on and reports their status
 * for the admin dashboard. Each check is isolated so one failure never
 * hides the others.
 */
class HealthCheck
{
    /**
     * @return array<string, array{ok: bool, detail: string}>
     */
    public function run(): array
    {
        return [
            'database' => $this->check(function (): string {
                DB::select('select 1');

                return DB::connection()->getDriverName();
            }),
            'cache' => $this->check(function (): string {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $value = Cache::pull($key);

                if ($value !== 'ok') {
                    throw new \RuntimeException('Cache read-back failed');
                }

                return (string) config('cache.default');
            }),
            'queue' => $this->check(function (): string {
                $connection = (string) config('queue.default');

                if ($connection === 'redis') {
                    Redis::connection()->ping();
                }

                return $connection;
            }),
            'migrations' => $this->migrations(),
            'uploads' => $this->uploads(),
        ];
    }

    /**
     * Database updates shipped with the code but not yet run: features that need them fail with a server error.
     *
     * @return array{ok: bool, detail: string}
     */
    private function migrations(): array
    {
        try {
            $files = array_keys(app('migrator')->getMigrationFiles([database_path('migrations')]));
            $ran = DB::table('migrations')->pluck('migration')->all();
            $pending = count(array_diff($files, $ran));

            return $pending === 0
                ? ['ok' => true, 'detail' => 'Up to date']
                : ['ok' => false, 'detail' => "{$pending} pending: run php artisan migrate --force"];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'detail' => 'Unavailable'];
        }
    }

    /**
     * PHP's upload limits must allow the app's 10 MB documents (cPanel defaults are 2 MB and 8 MB).
     *
     * @return array{ok: bool, detail: string}
     */
    private function uploads(): array
    {
        $bytes = function (string $v): int {
            $v = trim($v);
            $n = (int) $v;

            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n
            };
        };
        $upload = (string) ini_get('upload_max_filesize');
        $post = (string) ini_get('post_max_size');
        $ok = $bytes($upload) >= 10 * 1024 ** 2 && $bytes($post) >= 12 * 1024 ** 2;

        return ['ok' => $ok, 'detail' => $ok
            ? "Files up to {$upload}"
            : "Files limited to {$upload} (request {$post}): set upload_max_filesize=20M and post_max_size=25M in MultiPHP INI Editor"];
    }

    /**
     * @param  callable(): string  $probe
     * @return array{ok: bool, detail: string}
     */
    private function check(callable $probe): array
    {
        try {
            return ['ok' => true, 'detail' => $probe()];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'detail' => 'Unavailable'];
        }
    }
}
