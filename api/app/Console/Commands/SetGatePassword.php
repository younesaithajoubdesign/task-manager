<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Set (or clear) the app password. Only its bcrypt hash is written to .env;
 * the password itself is typed hidden and never stored.
 */
class SetGatePassword extends Command
{
    /** A curtain, not security (SPEC §5.1): a 4-digit code is enough. */
    public const MIN_LENGTH = 4;

    protected $signature = 'gate:password {--clear : Remove the password and leave the app open}';

    protected $description = 'Set the password that opens the app (stored as a hash in .env)';

    public function handle(): int
    {
        $envPath = $this->laravel->environmentFilePath();

        if (! is_file($envPath)) {
            $this->error("No .env file at {$envPath}.");

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            $this->writeHash($envPath, null);
            $this->info('Password removed: the app no longer asks for one.');

            return self::SUCCESS;
        }

        $password = (string) $this->secret('New password');

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $this->error('Use at least '.self::MIN_LENGTH.' characters.');

            return self::FAILURE;
        }

        if ($password !== $this->secret('Repeat it')) {
            $this->error('The two entries differ. Nothing changed.');

            return self::FAILURE;
        }

        $this->writeHash($envPath, Hash::make($password));
        $this->info('Password set. Every browser will be asked for it again.');

        return self::SUCCESS;
    }

    /**
     * Replace (or append, or remove) the APP_PASSWORD_HASH line. Single
     * quotes keep the "$" characters of a bcrypt hash literal.
     */
    private function writeHash(string $envPath, ?string $hash): void
    {
        $lines = preg_split('/\R/', (string) file_get_contents($envPath));
        $lines = array_values(array_filter($lines, fn (string $line) => ! str_starts_with($line, 'APP_PASSWORD_HASH=')));

        if ($hash !== null) {
            // Keep the file's trailing newline at the end.
            $last = end($lines) === '' ? array_pop($lines) : null;
            $lines[] = "APP_PASSWORD_HASH='{$hash}'";
            if ($last !== null) {
                $lines[] = $last;
            }
        }

        file_put_contents($envPath, implode(PHP_EOL, $lines));

        // In production the config is cached (`artisan optimize`), so .env
        // is not read again until the cache is rebuilt from it.
        if ($this->laravel->configurationIsCached()) {
            $this->call('config:cache');
        }
    }
}
