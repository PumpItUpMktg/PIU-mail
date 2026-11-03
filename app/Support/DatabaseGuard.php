<?php

namespace App\Support;

/**
 * PIU-mail needs Postgres + PostGIS. This guard runs whenever a database
 * connection is opened and stops with a plain-English message instead of
 * falling back to SQLite or a localhost default.
 */
final class DatabaseGuard
{
    public const HINT = 'In Laravel Cloud: attach a Postgres database to this environment, then redeploy. Locally: set DB_HOST (or DB_URL) in .env.';

    /**
     * @param  array<string, mixed>  $config
     */
    public static function problem(string $name, array $config): ?string
    {
        $driver = $config['driver'] ?? null;

        if ($driver !== 'pgsql') {
            return "PIU-mail requires Postgres with PostGIS, but database connection [{$name}] uses the [{$driver}] driver. Set DB_CONNECTION=pgsql. ".self::HINT;
        }

        if (empty($config['url']) && empty($config['host'])) {
            return 'No database is attached: DB_HOST and DB_URL are both empty. '.self::HINT;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function assert(string $name, array $config): void
    {
        if ($problem = self::problem($name, $config)) {
            throw new DatabaseNotConfiguredException($problem);
        }
    }
}
