<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * The password curtain. No users, no token table.
 *
 * A token is "<expiry>.<signature>": the expiry as a Unix timestamp and an
 * HMAC of it with the password hash, keyed with APP_KEY. So a token dies on
 * its own after `gate.token_days`, changing the password (or APP_KEY) signs
 * every browser out, and logging out lists the token as revoked in the cache
 * until it would have expired anyway (SPEC addendum §14).
 */
class AccessGate
{
    public function isEnabled(): bool
    {
        return filled($this->passwordHash());
    }

    /**
     * A fresh token for a correct password, or null.
     */
    public function attempt(string $password): ?string
    {
        if (! $this->isEnabled() || ! Hash::check($password, $this->passwordHash())) {
            return null;
        }

        $expiresAt = now()->addDays((int) config('gate.token_days'))->getTimestamp();

        return $expiresAt.'.'.$this->signature($expiresAt);
    }

    public function accepts(?string $token): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $expiresAt = $this->validExpiry($token);

        return $expiresAt !== null && ! Cache::has($this->revocationKey($token));
    }

    /**
     * Log a browser out: its token stops working now, not in 90 days.
     */
    public function revoke(?string $token): void
    {
        $expiresAt = $this->isEnabled() ? $this->validExpiry($token) : null;

        if ($expiresAt !== null) {
            Cache::put($this->revocationKey($token), true, now()->setTimestamp($expiresAt));
        }
    }

    /**
     * The token's expiry when it is well-formed, correctly signed and not
     * expired; null otherwise.
     */
    private function validExpiry(?string $token): ?int
    {
        if (! is_string($token) || ! preg_match('/^(\d{1,12})\.([0-9a-f]{64})$/', $token, $parts)) {
            return null;
        }

        $expiresAt = (int) $parts[1];

        if (! hash_equals($this->signature($expiresAt), $parts[2]) || $expiresAt <= now()->getTimestamp()) {
            return null;
        }

        return $expiresAt;
    }

    private function signature(int $expiresAt): string
    {
        return hash_hmac('sha256', "access-gate|{$expiresAt}|".$this->passwordHash(), (string) config('app.key'));
    }

    private function revocationKey(string $token): string
    {
        return 'gate:revoked:'.hash('sha256', $token);
    }

    private function passwordHash(): ?string
    {
        return config('gate.password_hash');
    }
}
