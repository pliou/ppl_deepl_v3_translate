<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Per-frontend-user (or per-IP) sliding-window rate limit for the paid DeepL translate action, so a
 * broadly/publicly reachable plugin cannot burn the API quota. File + flock based (no cache infra
 * required), mirroring the proven login-attempt limiter. Fails OPEN if storage is unavailable, so a
 * filesystem problem never blocks legitimate translation.
 */
final class TranslationRateLimiter
{
    private const STORAGE_SUBDIR = 'transient/ppl_deepl_v3_translate/translation-rate/';
    private const WINDOW_SECONDS = 60;
    private const MAX_PER_WINDOW = 30;
    private const DAY_SECONDS = 86400;
    private const MAX_PER_DAY = 1000;

    /**
     * Record one request and return true if it is within the limits, false if the caller should be
     * throttled (do NOT call the paid API in that case).
     */
    public function allow(ServerRequestInterface $request): bool
    {
        $filePath = $this->storageFile($this->clientKey($request));
        GeneralUtility::mkdir_deep(dirname($filePath));

        $handle = @fopen($filePath, 'c+');
        if (!is_resource($handle)) {
            return true;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return true;
            }
            $now = time();
            rewind($handle);
            $contents = stream_get_contents($handle);
            $state = is_string($contents) && $contents !== '' ? json_decode($contents, true) : [];
            $state = is_array($state) ? $state : [];

            $windowStart = (int)($state['windowStart'] ?? 0);
            $windowCount = (int)($state['windowCount'] ?? 0);
            $dayStart = (int)($state['dayStart'] ?? 0);
            $dayCount = (int)($state['dayCount'] ?? 0);

            if ($windowStart <= 0 || $windowStart + self::WINDOW_SECONDS <= $now) {
                $windowStart = $now;
                $windowCount = 0;
            }
            if ($dayStart <= 0 || $dayStart + self::DAY_SECONDS <= $now) {
                $dayStart = $now;
                $dayCount = 0;
            }

            $allowed = $windowCount < self::MAX_PER_WINDOW && $dayCount < self::MAX_PER_DAY;
            if ($allowed) {
                $windowCount++;
                $dayCount++;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string)json_encode([
                'windowStart' => $windowStart,
                'windowCount' => $windowCount,
                'dayStart' => $dayStart,
                'dayCount' => $dayCount,
            ], JSON_THROW_ON_ERROR));
            fflush($handle);

            return $allowed;
        } catch (\Throwable) {
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function clientKey(ServerRequestInterface $request): string
    {
        $user = $request->getAttribute('frontend.user');
        $uid = is_object($user) ? (int)($this->userArray($user)['uid'] ?? 0) : 0;
        if ($uid > 0) {
            return 'fe_' . $uid;
        }

        $ip = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '');

        return 'ip_' . sha1($ip !== '' ? $ip : 'unknown');
    }

    /**
     * @return array<string, mixed>
     */
    private function userArray(object $user): array
    {
        $data = get_object_vars($user)['user'] ?? null;

        return is_array($data) ? $data : [];
    }

    private function storageFile(string $key): string
    {
        return rtrim(str_replace('\\', '/', Environment::getVarPath()), '/') . '/' . self::STORAGE_SUBDIR
            . (string)preg_replace('/[^a-z0-9_]/i', '_', $key) . '.json';
    }
}
