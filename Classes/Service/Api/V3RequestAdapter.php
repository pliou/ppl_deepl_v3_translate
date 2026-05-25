<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service\Api;

use Ppl\PplDeeplV3Requests\Service\DeeplApiClientService;
use Ppl\PplDeeplV3Translate\Service\DeeplLanguageService;

final class V3RequestAdapter implements DeepLApiAdapterInterface
{
    public function __construct(
        private readonly DeeplLanguageService $languageService,
        private readonly DeeplApiClientService $apiClient
    ) {}

    public function getCapabilities(): array
    {
        return [
            'supportsGlossaries' => true,
            'supportsFileTranslation' => true,
            'supportsWritingStyleTone' => false,
            'supportsStyleRules' => true,
            'supportsCustomInstructions' => true,
        ];
    }

    public function fetchLanguages(string $authKey): array
    {
        if (trim($authKey) === '') {
            return [];
        }

        $sourceLanguages = $this->apiClient->listTextTranslationLanguages($authKey, 'source');
        $targetLanguages = $this->apiClient->listTextTranslationLanguages($authKey, 'target');
        $languages = [];

        foreach ($sourceLanguages as $language) {
            if (!is_array($language)) {
                continue;
            }

            $code = $this->normalizeCode((string)($language['language'] ?? $language['lang'] ?? ''));
            if ($code === '') {
                continue;
            }

            $languages[$code] = [
                'code' => $code,
                'name' => (string)($language['name'] ?? $code),
                'enabled' => false,
                'supportsSource' => true,
                'supportsTarget' => false,
            ];
        }

        foreach ($targetLanguages as $language) {
            if (!is_array($language)) {
                continue;
            }

            $code = $this->normalizeCode((string)($language['language'] ?? $language['lang'] ?? ''));
            if ($code === '') {
                continue;
            }

            if (!isset($languages[$code])) {
                $languages[$code] = [
                    'code' => $code,
                    'name' => (string)($language['name'] ?? $code),
                    'enabled' => false,
                    'supportsSource' => false,
                    'supportsTarget' => true,
                ];
                continue;
            }

            $languages[$code]['name'] = (string)($language['name'] ?? $languages[$code]['name']);
            $languages[$code]['supportsTarget'] = true;
        }

        return array_values($languages);
    }

    public function fetchGlossaries(string $authKey): array
    {
        if (trim($authKey) === '') {
            return [];
        }

        $response = $this->apiClient->listGlossaries($authKey);
        $glossaries = $response['glossaries'] ?? [];
        $normalizedGlossaries = [];

        foreach ($glossaries as $glossary) {
            if (!is_array($glossary)) {
                continue;
            }

            $id = (string)($glossary['glossary_id'] ?? $glossary['glossaryId'] ?? $glossary['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $dictionaries = $this->normalizeDictionaries((array)($glossary['dictionaries'] ?? []));
            $normalizedGlossaries[] = [
                'id' => $id,
                'name' => (string)($glossary['name'] ?? $id),
                'creationTime' => $this->formatCreationTime($glossary['creation_time'] ?? $glossary['creationTime'] ?? null),
                'dictionaries' => $dictionaries,
                'languagePairs' => $this->buildLanguagePairs($dictionaries),
                'entryCount' => array_sum(array_map(static fn(array $dictionary): int => (int)$dictionary['entryCount'], $dictionaries)),
            ];
        }

        usort(
            $normalizedGlossaries,
            static fn(array $left, array $right): int => strcasecmp((string)$left['name'], (string)$right['name'])
        );

        return $normalizedGlossaries;
    }

    public function translateText(
        string $authKey,
        string $inputText,
        string $sourceLanguage,
        string $targetLanguage,
        ?string $glossaryId = null,
        string $writingStyle = '',
        string $tone = '',
        string $styleRuleId = '',
        array $customInstructions = []
    ): string {
        return $this->apiClient->translateText(
            $authKey,
            $inputText,
            $this->languageService->normalizeSourceLanguage($sourceLanguage),
            $this->languageService->normalizeTargetLanguage($targetLanguage),
            $glossaryId,
            $styleRuleId,
            array_slice(array_values(array_unique($customInstructions)), 0, 10)
        );
    }

    public function translateDocument(
        string $authKey,
        string $sourcePath,
        string $targetPath,
        string $sourceLanguage,
        string $targetLanguage,
        ?string $glossaryId = null
    ): void {
        $this->apiClient->translateDocument(
            $authKey,
            $sourcePath,
            $targetPath,
            $this->languageService->normalizeSourceLanguage($sourceLanguage),
            $this->languageService->normalizeTargetLanguage($targetLanguage),
            $glossaryId
        );
    }

    private function normalizeDictionaries(array $dictionaries): array
    {
        $normalized = [];

        foreach ($dictionaries as $dictionary) {
            if (!is_object($dictionary) && !is_array($dictionary)) {
                continue;
            }

            $sourceLanguage = $this->readValue($dictionary, 'source_lang', 'sourceLang');
            $targetLanguage = $this->readValue($dictionary, 'target_lang', 'targetLang');
            if ($sourceLanguage === '' || $targetLanguage === '') {
                continue;
            }

            $sourceLanguage = $this->languageService->normalizeGlossaryLanguage($sourceLanguage);
            $targetLanguage = $this->languageService->normalizeGlossaryLanguage($targetLanguage);
            $normalized[] = [
                'sourceLang' => $sourceLanguage,
                'targetLang' => $targetLanguage,
                'entryCount' => (int)$this->readValue($dictionary, 'entry_count', 'entryCount'),
                'combinationKey' => $this->languageService->buildGlossaryCombinationKey($sourceLanguage, $targetLanguage),
            ];
        }

        return $normalized;
    }

    private function readValue(object|array $source, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (is_array($source) && array_key_exists($key, $source)) {
                return (string)$source[$key];
            }

            if (is_object($source) && isset($source->{$key})) {
                return (string)$source->{$key};
            }
        }

        return '';
    }

    private function buildLanguagePairs(array $dictionaries): string
    {
        $pairs = array_map(
            static fn(array $dictionary): string => $dictionary['sourceLang'] . ' -> ' . $dictionary['targetLang'],
            $dictionaries
        );

        return implode(', ', $pairs);
    }

    private function formatCreationTime(mixed $creationTime): string
    {
        if ($creationTime instanceof \DateTimeInterface) {
            return $creationTime->format(DATE_ATOM);
        }

        return is_scalar($creationTime) ? (string)$creationTime : '';
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
