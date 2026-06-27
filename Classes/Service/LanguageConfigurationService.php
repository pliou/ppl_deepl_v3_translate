<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use Ppl\PplDeeplV3Requests\Service\DeeplLanguageConfigurationService;
use Ppl\PplDeeplV3Translate\Service\Api\DeepLApiAdapterInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class LanguageConfigurationService
{
    private ?DeeplLanguageConfigurationService $sharedLanguageConfigurationService;

    public function __construct(
        ?DeepLApiAdapterInterface $apiAdapter = null,
        ?DeeplLanguageConfigurationService $sharedLanguageConfigurationService = null
    ) {
        $this->sharedLanguageConfigurationService = $sharedLanguageConfigurationService;
    }

    public function getApiCapabilities(): array
    {
        return $this->getSharedLanguageConfigurationService()->getApiCapabilities();
    }

    public function fetchRemoteLanguages(string $authKey): array
    {
        return $this->getSharedLanguageConfigurationService()->fetchRemoteLanguages($authKey);
    }

    public function getSavedLanguages(): array
    {
        return $this->getSharedLanguageConfigurationService()->getSavedLanguages();
    }

    public function saveLanguages(array $remoteLanguages, array $enabledCodes): array
    {
        return $this->getSharedLanguageConfigurationService()->saveLanguages($remoteLanguages, $enabledCodes);
    }

    public function getEnabledLanguages(): array
    {
        return $this->getSharedLanguageConfigurationService()->getEnabledLanguages();
    }

    public function getEnabledSourceLanguages(): array
    {
        return $this->getSharedLanguageConfigurationService()->getEnabledSourceLanguages();
    }

    public function getEnabledTargetLanguages(): array
    {
        return $this->getSharedLanguageConfigurationService()->getEnabledTargetLanguages();
    }

    public function getApprovalLanguageGroups(array $languages): array
    {
        return $this->getSharedLanguageConfigurationService()->getApprovalLanguageGroups($languages);
    }

    private function getSharedLanguageConfigurationService(): DeeplLanguageConfigurationService
    {
        if ($this->sharedLanguageConfigurationService === null) {
            $this->sharedLanguageConfigurationService = GeneralUtility::makeInstance(DeeplLanguageConfigurationService::class);
        }

        return $this->sharedLanguageConfigurationService;
    }
}
