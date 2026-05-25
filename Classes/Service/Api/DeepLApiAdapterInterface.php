<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service\Api;

interface DeepLApiAdapterInterface
{
    public function getCapabilities(): array;

    public function fetchLanguages(string $authKey): array;

    public function fetchGlossaries(string $authKey): array;

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
    ): string;

    public function translateDocument(
        string $authKey,
        string $sourcePath,
        string $targetPath,
        string $sourceLanguage,
        string $targetLanguage,
        ?string $glossaryId = null
    ): void;
}
