<?php

namespace App\Services;

class AnonymousReporterService
{
    public function issueOrReuse(?string $candidate): string
    {
        return $this->isValid($candidate) ? $candidate : $this->issue();
    }

    public function isValid(?string $token): bool
    {
        if (! is_string($token)) {
            return false;
        }

        $prefix = preg_quote((string) config('reporting.anonymous_token_prefix'), '/');

        return preg_match("/^{$prefix}[A-Za-z0-9_-]{43}$/D", $token) === 1;
    }

    public function hash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    private function issue(): string
    {
        // 32 random bytes encode to exactly 43 unpadded base64url characters.
        // Keep this explicit so the browser-continuity token is cryptographically
        // generated without depending on a convenience-string implementation.
        $random = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return config('reporting.anonymous_token_prefix').$random;
    }
}
