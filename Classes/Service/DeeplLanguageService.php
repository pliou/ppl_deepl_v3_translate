<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

final class DeeplLanguageService
{
    public const DEFAULT_SOURCE_LANGUAGE = 'EN';
    public const DEFAULT_TARGET_LANGUAGE = 'DE';

    public function getLanguages(): array
    {
        return GeneralUtility::makeInstance(LanguageConfigurationService::class)->getEnabledLanguages();
    }

    public function getSourceLanguages(): array
    {
        return GeneralUtility::makeInstance(LanguageConfigurationService::class)->getEnabledSourceLanguages();
    }

    public function getTargetLanguages(): array
    {
        return GeneralUtility::makeInstance(LanguageConfigurationService::class)->getEnabledTargetLanguages();
    }

    public function normalizeSourceLanguage(string $language): string
    {
        $language = strtoupper($language);

        return match (true) {
            $language === 'DE-DE' => 'DE',
            str_starts_with($language, 'EN-') => 'EN',
            str_starts_with($language, 'PT-') => 'PT',
            str_starts_with($language, 'ES-') => 'ES',
            $language === 'ZH-HANS' || $language === 'ZH-HANT' => 'ZH',
            str_contains($language, '-') => explode('-', $language, 2)[0],
            default => $language,
        };
    }

    public function normalizeTargetLanguage(string $language): string
    {
        return match (strtoupper($language)) {
            'EN' => 'EN-GB',
            'PT' => 'PT-PT',
            'DE-DE' => 'DE',
            default => strtoupper($language),
        };
    }

    public function normalizeGlossaryLanguage(string $language): string
    {
        $language = strtoupper($language);

        return match (true) {
            $language === 'DE-DE' => 'DE',
            str_starts_with($language, 'EN-') => 'EN',
            str_starts_with($language, 'PT-') => 'PT',
            str_starts_with($language, 'ES-') => 'ES',
            $language === 'ZH-HANS' || $language === 'ZH-HANT' => 'ZH',
            str_contains($language, '-') => explode('-', $language, 2)[0],
            default => $language,
        };
    }

    public function normalizeWriteLanguage(string $language): string
    {
        $language = strtoupper($language);

        return match (true) {
            $language === 'EN-GB' || $language === 'EN-US' => $language,
            $language === 'EN' => 'EN-GB',
            $language === 'DE' || $language === 'DE-DE' => 'DE',
            default => $this->normalizeSourceLanguage($language),
        };
    }

    public function normalizeStyleRuleLanguage(string $language): string
    {
        return match (true) {
            str_starts_with(strtoupper($language), 'EN') => 'EN',
            strtoupper($language) === 'DE' || strtoupper($language) === 'DE-DE' => 'DE',
            str_starts_with(strtoupper($language), 'ES') => 'ES',
            str_starts_with(strtoupper($language), 'FR') => 'FR',
            str_starts_with(strtoupper($language), 'IT') => 'IT',
            str_starts_with(strtoupper($language), 'JA') => 'JA',
            str_starts_with(strtoupper($language), 'KO') => 'KO',
            str_starts_with(strtoupper($language), 'ZH') => 'ZH',
            default => '',
        };
    }

    public function supportsDeepLWriteLanguage(string $language): bool
    {
        $language = strtoupper($language);

        return str_starts_with($language, 'EN') || $language === 'DE' || $language === 'DE-DE';
    }

    public function buildGlossaryCombinationKey(string $sourceLanguage, string $targetLanguage): string
    {
        return $this->normalizeGlossaryLanguage($sourceLanguage)
            . ':'
            . $this->normalizeGlossaryLanguage($targetLanguage);
    }
}
