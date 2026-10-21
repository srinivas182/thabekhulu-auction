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
        ];
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
