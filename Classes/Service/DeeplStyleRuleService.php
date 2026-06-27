<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use Ppl\PplDeeplV3Requests\Service\DeeplApiClientService;
use Ppl\PplDeeplV3Requests\Service\DeeplStyleRuleConfigurationService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class DeeplStyleRuleService
{
    private ?DeeplStyleRuleConfigurationService $sharedStyleRuleConfigurationService;

    public function __construct(
        ?DeeplLanguageService $languageService = null,
        ?DeeplApiClientService $apiClient = null,
        ?DeeplStyleRuleConfigurationService $sharedStyleRuleConfigurationService = null
    ) {
        $this->sharedStyleRuleConfigurationService = $sharedStyleRuleConfigurationService;
    }

    public function fetchRemoteStyleRules(string $authKey): array
    {
        return $this->getSharedStyleRuleConfigurationService()->fetchRemoteStyleRules($authKey);
    }

    public function getSavedStyleRules(): array
    {
        return $this->getSharedStyleRuleConfigurationService()->getSavedStyleRules();
    }

    public function saveSelectedStyleRules(array $remoteStyleRules, array $selectedIds): array
    {
        return $this->getSharedStyleRuleConfigurationService()->saveSelectedStyleRules($remoteStyleRules, $selectedIds);
    }

    public function saveStyleRules(array $remoteStyleRules, array $enabledIds): array
    {
        return $this->getSharedStyleRuleConfigurationService()->saveStyleRules($remoteStyleRules, $enabledIds);
    }

    public function getSelectedStyleRuleIds(): array
    {
        return $this->getSharedStyleRuleConfigurationService()->getSelectedStyleRuleIds();
    }

    public function getEnabledStyleRules(): array
    {
        return $this->getSharedStyleRuleConfigurationService()->getEnabledStyleRules();
    }

    public function getStyleRuleOptions(): array
    {
        return $this->getSharedStyleRuleConfigurationService()->getStyleRuleOptions();
    }

    public function getStyleRuleOptionsForLanguage(string $targetLanguage): array
    {
        return $this->getSharedStyleRuleConfigurationService()->getStyleRuleOptionsForLanguage($targetLanguage);
    }

    public function getStyleRuleDisplayOptions(): array
    {
        return $this->getSharedStyleRuleConfigurationService()->getStyleRuleDisplayOptions();
    }

    public function getStyleRuleOptionsByLanguage(): array
    {
        return $this->getSharedStyleRuleConfigurationService()->getStyleRuleOptionsByLanguage();
    }

    public function isStyleRuleAvailableForLanguage(string $styleRuleId, string $targetLanguage): bool
    {
        return $this->getSharedStyleRuleConfigurationService()->isStyleRuleAvailableForLanguage($styleRuleId, $targetLanguage);
    }

    private function getSharedStyleRuleConfigurationService(): DeeplStyleRuleConfigurationService
    {
        if ($this->sharedStyleRuleConfigurationService === null) {
            $this->sharedStyleRuleConfigurationService = GeneralUtility::makeInstance(DeeplStyleRuleConfigurationService::class);
        }

        return $this->sharedStyleRuleConfigurationService;
    }
}
