<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Stores DeepL-translated documents outside the public web root and hands them out
 * only via short-lived, HMAC-signed download tokens.
 *
 * Replaces the former public delivery via fileadmin/user_upload/translated/, where
 * translated documents (potentially confidential) were reachable by direct URL.
 */
final class TranslatedDownloadStorage
{
    private const SUBDIR = 'transient/ppl_deepl_v3_translate/downloads/';
    private const SECRET_SCOPE = 'ppl_deepl_v3_translate/translated-download';
    private const TTL_SECONDS = 3600;

    /**
     * Absolute path to the private storage directory (created on demand).
     */
    public function getStorageDirectory(): string
    {
        $dir = rtrim(Environment::getVarPath(), '/') . '/' . self::SUBDIR;
        if (!is_dir($dir)) {
            GeneralUtility::mkdir_deep($dir);
        }

        return $dir;
    }

    /**
     * Random, traversal-safe storage file name that keeps the original extension.
     */
    public function buildStorageFileName(string $originalName): string
    {
        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = (string)preg_replace('/[^a-z0-9]/', '', $extension);
        $random = bin2hex(random_bytes(16));

        return $extension !== '' ? $random . '.' . $extension : $random;
    }

    public function getStoragePath(string $storageFileName): string
    {
        return $this->getStorageDirectory() . $this->safeStorageName($storageFileName);
    }

    /**
     * Build a signed, time-limited download token for a stored file.
     */
    public function createToken(string $storageFileName, string $displayName): string
    {
        $payload = [
            'n' => $this->safeStorageName($storageFileName),
            'd' => $displayName,
            'e' => $this->now() + self::TTL_SECONDS,
        ];
        $encoded = $this->base64UrlEncode((string)json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = GeneralUtility::hmac($encoded, self::SECRET_SCOPE);

        return $encoded . '.' . $signature;
    }

    /**
     * Validate a token and resolve it to an existing, non-expired file.
     *
     * @return array{path: string, name: string}|null
     */
    public function resolveToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$encoded, $signature] = $parts;
        if (!hash_equals(GeneralUtility::hmac($encoded, self::SECRET_SCOPE), $signature)) {
            return null;
        }
        $decoded = json_decode($this->base64UrlDecode($encoded), true);
        if (!is_array($decoded)) {
            return null;
        }
        if ((int)($decoded['e'] ?? 0) < $this->now()) {
            return null;
        }
        $storageName = $this->safeStorageName((string)($decoded['n'] ?? ''));
        if ($storageName === '') {
            return null;
        }
        $path = $this->getStorageDirectory() . $storageName;
        if (!is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'name' => $this->safeDisplayName((string)($decoded['d'] ?? $storageName)),
        ];
    }

    /**
     * Best-effort cleanup of files older than the token lifetime.
     */
    public function sweepExpired(): void
    {
        $this->purgeExpired();
    }

    /**
     * Delete all stored files older than the token lifetime and return the number removed.
     * Used by the scheduled cleanup command and opportunistically on each upload.
     */
    public function purgeExpired(): int
    {
        $threshold = $this->now() - self::TTL_SECONDS;
        $removed = 0;
        foreach (glob($this->getStorageDirectory() . '*') ?: [] as $file) {
            if (is_file($file) && (int)filemtime($file) < $threshold && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Only the basename of the random token pattern may pass; blocks path traversal.
     */
    private function safeStorageName(string $name): string
    {
        $name = basename($name);

        return preg_match('/^[a-f0-9]{32}(\.[a-z0-9]+)?$/', $name) === 1 ? $name : '';
    }

    private function safeDisplayName(string $name): string
    {
        $name = (string)preg_replace('/[\r\n"\\\\\/]+/', '_', basename($name));

        return $name !== '' ? $name : 'download';
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return (string)base64_decode(strtr($data, '-_', '+/'), true);
    }

    private function now(): int
    {
        return (int)($GLOBALS['EXEC_TIME'] ?? time());
    }
}
