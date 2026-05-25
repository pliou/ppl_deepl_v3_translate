<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use Ppl\PplDeeplV3Requests\Service\DeeplApiClientService;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class DeeplStyleRuleService
{
    private const STORAGE_DIRECTORY = 'ppl_deepl_v3_translate';
    private const STORAGE_FILE = 'style-rules.json';

    public function __construct(
        private readonly DeeplLanguageService $languageService,
        private readonly DeeplApiClientService $apiClient
    ) {}

    public function fetchRemoteStyleRules(string $authKey): array
    {
        if (trim($authKey) === '') {
            return [];
        }

        $response = $this->apiClient->listStyleRules($authKey);
        $styleRules = $response['style_rules'] ?? [];
        $normalizedStyleRules = [];

        foreach ($styleRules as $styleRule) {
            if (!is_array($styleRule)) {
                continue;
            }

            $id = (string)($styleRule['style_id'] ?? '');
            if ($id === '') {
                continue;
            }

            $language = $this->languageService->normalizeStyleRuleLanguage((string)($styleRule['language'] ?? ''));

            $normalizedStyleRules[] = [
                'id' => $id,
                'name' => (string)($styleRule['name'] ?? $id),
                'language' => $language,
                'version' => (int)($styleRule['version'] ?? 0),
                'updatedTime' => (string)($styleRule['updated_time'] ?? ''),
                'label' => trim((string)($styleRule['name'] ?? $id) . ($language !== '' ? ' (' . $language . ')' : '')),
            ];
        }

        usort(
            $normalizedStyleRules,
            static fn(array $left, array $right): int => strcasecmp((string)$left['label'], (string)$right['label'])
        );

        return $normalizedStyleRules;
    }

    public function getSavedStyleRules(): array
    {
        $storageFile = $this->getStorageFilePath();
        if (!is_file($storageFile)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($storageFile), true);
        if (!is_array($data)) {
            return [];
        }

        $styleRules = $data['styleRules'] ?? [];

        if (!is_array($styleRules)) {
            return [];
        }

        return array_values(array_map(
            static function (array $styleRule): array {
                $styleRule['enabled'] = array_key_exists('enabled', $styleRule) ? (bool)$styleRule['enabled'] : true;

                return $styleRule;
            },
            array_filter($styleRules, 'is_array')
        ));
    }

    public function saveSelectedStyleRules(array $remoteStyleRules, array $selectedIds): array
    {
        return $this->saveStyleRules($remoteStyleRules, $selectedIds);
    }

    public function saveStyleRules(array $remoteStyleRules, array $enabledIds): array
    {
        $enabledLookup = array_fill_keys(array_map('strval', $enabledIds), true);
        $savedStyleRules = [];

        foreach ($remoteStyleRules as $styleRule) {
            $id = (string)($styleRule['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $styleRule['enabled'] = isset($enabledLookup[$id]);
            $savedStyleRules[] = $styleRule;
        }

        $storageDirectory = dirname($this->getStorageFilePath());
        if (!is_dir($storageDirectory)) {
            GeneralUtility::mkdir_deep($storageDirectory);
        }

        file_put_contents(
            $this->getStorageFilePath(),
            json_encode(
                [
                    'savedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'styleRules' => $savedStyleRules,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        return $savedStyleRules;
    }

    public function getSelectedStyleRuleIds(): array
    {
        return array_values(array_filter(array_map(
            static fn(array $styleRule): string => (string)($styleRule['id'] ?? ''),
            $this->getEnabledStyleRules()
        )));
    }

    public function getEnabledStyleRules(): array
    {
        return array_values(array_filter(
            $this->getSavedStyleRules(),
            static fn(array $styleRule): bool => (bool)($styleRule['enabled'] ?? false)
        ));
    }

    public function getStyleRuleOptions(): array
    {
        $options = [];

        foreach ($this->getEnabledStyleRules() as $styleRule) {
            $id = (string)($styleRule['id'] ?? '');
            if ($id !== '') {
                $options[$id] = (string)($styleRule['label'] ?? $styleRule['name'] ?? $id);
            }
        }

        return $options;
    }

    public function getStyleRuleOptionsForLanguage(string $targetLanguage): array
    {
        $targetLanguage = $this->languageService->normalizeStyleRuleLanguage($targetLanguage);
        if ($targetLanguage === '') {
            return [];
        }

        $options = [];

        foreach ($this->getEnabledStyleRules() as $styleRule) {
            $id = (string)($styleRule['id'] ?? '');
            if ($id === '' || (string)($styleRule['language'] ?? '') !== $targetLanguage) {
                continue;
            }

            $options[$id] = (string)($styleRule['label'] ?? $styleRule['name'] ?? $id);
        }

        return $options;
    }

    public function getStyleRuleDisplayOptions(): array
    {
        $options = [];

        foreach ($this->getEnabledStyleRules() as $styleRule) {
            $id = (string)($styleRule['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $options[$id] = [
                'label' => (string)($styleRule['label'] ?? $styleRule['name'] ?? $id),
                'language' => (string)($styleRule['language'] ?? ''),
            ];
        }

        return $options;
    }

    public function getStyleRuleOptionsByLanguage(): array
    {
        $optionsByLanguage = [];

        foreach ($this->getEnabledStyleRules() as $styleRule) {
            $id = (string)($styleRule['id'] ?? '');
            $language = (string)($styleRule['language'] ?? '');
            if ($id === '' || $language === '') {
                continue;
            }

            $optionsByLanguage[$language][$id] = (string)($styleRule['label'] ?? $styleRule['name'] ?? $id);
        }

        return $optionsByLanguage;
    }

    public function isStyleRuleAvailableForLanguage(string $styleRuleId, string $targetLanguage): bool
    {
        if ($styleRuleId === '') {
            return false;
        }

        $targetLanguage = $this->languageService->normalizeStyleRuleLanguage($targetLanguage);
        if ($targetLanguage === '') {
            return false;
        }

        foreach ($this->getEnabledStyleRules() as $styleRule) {
            if ((string)($styleRule['id'] ?? '') !== $styleRuleId) {
                continue;
            }

            return (string)($styleRule['language'] ?? '') === $targetLanguage;
        }

        return false;
    }

    private function getStorageFilePath(): string
    {
        return Environment::getVarPath() . '/' . self::STORAGE_DIRECTORY . '/' . self::STORAGE_FILE;
    }
}
