<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;

/**
 * The password curtain. No users, no token table: the token is an HMAC of
 * the password hash keyed with APP_KEY, so it never expires on its own and
 * changing the password (or APP_KEY) signs every browser out.
 */
class AccessGate
{
    public function isEnabled(): bool
    {
        return filled($this->passwordHash());
    }

    /**
     * The token for a correct password, or null.
     */
    public function attempt(string $password): ?string
    {
        if (! $this->isEnabled() || ! Hash::check($password, $this->passwordHash())) {
            return null;
        }

        return $this->token();
    }

    public function accepts(?string $token): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        return is_string($token) && hash_equals($this->token(), $token);
    }

    private function token(): string
    {
        return hash_hmac('sha256', 'access-gate|'.$this->passwordHash(), (string) config('app.key'));
    }

    private function passwordHash(): ?string
    {
        return config('gate.password_hash');
    }
}
