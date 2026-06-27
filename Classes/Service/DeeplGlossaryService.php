<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use Ppl\PplDeeplV3Requests\Service\DeeplGlossaryConfigurationService;
use Ppl\PplDeeplV3Translate\Service\Api\DeepLApiAdapterInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class DeeplGlossaryService
{
    private ?DeeplGlossaryConfigurationService $sharedGlossaryConfigurationService;

    public function __construct(
        ?DeeplLanguageService $languageService = null,
        ?DeepLApiAdapterInterface $apiAdapter = null,
        ?DeeplGlossaryConfigurationService $sharedGlossaryConfigurationService = null
    ) {
        $this->sharedGlossaryConfigurationService = $sharedGlossaryConfigurationService;
    }

    public function fetchRemoteGlossaries(string $authKey): array
    {
        return $this->getSharedGlossaryConfigurationService()->fetchRemoteGlossaries($authKey);
    }

    public function getSavedGlossaries(): array
    {
        return $this->getSharedGlossaryConfigurationService()->getSavedGlossaries();
    }

    public function saveSelectedGlossaries(array $remoteGlossaries, array $selectedIds): array
    {
        return $this->getSharedGlossaryConfigurationService()->saveSelectedGlossaries($remoteGlossaries, $selectedIds);
    }

    public function saveGlossaries(array $remoteGlossaries, array $enabledIds): array
    {
        return $this->getSharedGlossaryConfigurationService()->saveGlossaries($remoteGlossaries, $enabledIds);
    }

    public function getSelectedGlossaryIds(): array
    {
        return $this->getSharedGlossaryConfigurationService()->getSelectedGlossaryIds();
    }

    public function getEnabledGlossaries(): array
    {
        return $this->getSharedGlossaryConfigurationService()->getEnabledGlossaries();
    }

    public function getGlossaryIdForLanguagePair(string $sourceLanguage, string $targetLanguage, string $preferredGlossaryId = ''): ?string
    {
        return $this->getSharedGlossaryConfigurationService()->getGlossaryIdForLanguagePair($sourceLanguage, $targetLanguage, $preferredGlossaryId);
    }

    public function hasGlossaryForLanguagePair(string $sourceLanguage, string $targetLanguage): bool
    {
        return $this->getSharedGlossaryConfigurationService()->hasGlossaryForLanguagePair($sourceLanguage, $targetLanguage);
    }

    public function getGlossaryCombinations(): array
    {
        return $this->getSharedGlossaryConfigurationService()->getGlossaryCombinations();
    }

    public function getGlossaryOptionsForLanguagePair(string $sourceLanguage, string $targetLanguage): array
    {
        return $this->getSharedGlossaryConfigurationService()->getGlossaryOptionsForLanguagePair($sourceLanguage, $targetLanguage);
    }

    public function getGlossaryOptionsByCombination(): array
    {
        return $this->getSharedGlossaryConfigurationService()->getGlossaryOptionsByCombination();
    }

    public function isGlossaryAvailableForLanguagePair(string $glossaryId, string $sourceLanguage, string $targetLanguage): bool
    {
        return $this->getSharedGlossaryConfigurationService()->isGlossaryAvailableForLanguagePair($glossaryId, $sourceLanguage, $targetLanguage);
    }

    private function getSharedGlossaryConfigurationService(): DeeplGlossaryConfigurationService
    {
        if ($this->sharedGlossaryConfigurationService === null) {
            $this->sharedGlossaryConfigurationService = GeneralUtility::makeInstance(DeeplGlossaryConfigurationService::class);
        }

        return $this->sharedGlossaryConfigurationService;
    }
}
