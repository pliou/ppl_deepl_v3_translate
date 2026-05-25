<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use Ppl\PplDeeplV3Translate\Service\Api\DeepLApiAdapterInterface;

final class DeeplTranslationService
{
    public function __construct(
        private readonly DeeplLanguageService $languageService,
        private readonly DeeplGlossaryService $glossaryService,
        private readonly DeepLApiAdapterInterface $apiAdapter,
        private readonly DeeplStyleRuleService $styleRuleService
    ) {}

    public function getApiCapabilities(): array
    {
        return $this->apiAdapter->getCapabilities();
    }

    public function translateText(
        string $authKey,
        string $inputText,
        string $sourceLanguage,
        string $targetLanguage,
        string $glossaryId,
        string $styleRuleId = '',
        array|string $customInstructions = []
    ): string {
        $glossaryId = $this->glossaryService->isGlossaryAvailableForLanguagePair($glossaryId, $sourceLanguage, $targetLanguage)
            ? $glossaryId
            : null;
        $styleRuleId = $this->styleRuleService->isStyleRuleAvailableForLanguage($styleRuleId, $targetLanguage)
            ? $styleRuleId
            : '';

        $instructions = $this->normalizeCustomInstructions($customInstructions);

        return $this->apiAdapter->translateText(
            $authKey,
            $inputText,
            $sourceLanguage,
            $targetLanguage,
            $glossaryId,
            '',
            '',
            $styleRuleId,
            array_slice(array_values(array_unique($instructions)), 0, 10)
        );
    }

    public function translateDocument(
        string $authKey,
        string $sourcePath,
        string $targetPath,
        string $sourceLanguage,
        string $targetLanguage,
        string $glossaryId
    ): void {
        $glossaryId = $this->glossaryService->isGlossaryAvailableForLanguagePair($glossaryId, $sourceLanguage, $targetLanguage)
            ? $glossaryId
            : null;

        $this->apiAdapter->translateDocument(
            $authKey,
            $sourcePath,
            $targetPath,
            $sourceLanguage,
            $targetLanguage,
            $glossaryId
        );
    }

    private function normalizeCustomInstructions(array|string $customInstructions): array
    {
        if (is_string($customInstructions)) {
            $customInstructions = preg_split('/\R+/', $customInstructions) ?: [];
        }

        $instructions = [];
        foreach ($customInstructions as $instruction) {
            $instruction = trim((string)$instruction);
            if ($instruction !== '') {
                $instructions[] = substr($instruction, 0, 300);
            }
        }

        return $instructions;
    }
}
