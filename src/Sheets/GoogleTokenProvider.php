<?php

declare(strict_types=1);
namespace App\Sheets;
use Google\Auth\Credentials\ServiceAccountCredentials;

final class GoogleTokenProvider implements AccessTokenProvider
{
    private string $cachedToken = '';
    private int $validUntil = 0;

    public function __construct(private readonly string $credentialsPath) {}

    public function token(): string
    {
        if ($this->cachedToken !== '' && time() < $this->validUntil) { return $this->cachedToken; }
        if ($this->credentialsPath === '' || !is_file($this->credentialsPath) || !is_readable($this->credentialsPath)) {
            throw new SyncFailure('Clé du compte de service absente ou illisible.');
        }
        try {
            $credentials = json_decode(@file_get_contents($this->credentialsPath), true, 512, JSON_THROW_ON_ERROR);
            if (($credentials['type'] ?? '') !== 'service_account') { throw new SyncFailure(); }
            $provider = new ServiceAccountCredentials('https://www.googleapis.com/auth/spreadsheets', $credentials);
            $token = $provider->fetchAuthToken();
            if (!is_string($token['access_token'] ?? null) || $token['access_token'] === '') { throw new SyncFailure(); }
            $this->cachedToken = $token['access_token'];
            $this->validUntil = time() + max(1, (int) ($token['expires_in'] ?? 3600)-60);
            return $this->cachedToken;
        } catch (\Throwable) {
            // Ne pas publier la clé, le jeton ou une réponse OAuth dans les logs.
            throw new SyncFailure('Authentification Google impossible ; vérifier la clé et l’API activée.');
        }
    }

    public function invalidate(): void { $this->validUntil = 0; $this->cachedToken = ''; }
}
