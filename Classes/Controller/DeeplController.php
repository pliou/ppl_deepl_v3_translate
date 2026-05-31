<?php

namespace Ppl\PplDeeplV3Translate\Controller;

use Ppl\PplDeeplV3Requests\Service\DeeplApiClientService;
use Ppl\PplDeeplV3Requests\Service\DeeplCustomInstructionConfigurationService;
use Ppl\PplDeeplV3Requests\Service\DeeplConfigurationService;
use Ppl\PplDeeplV3Translate\Service\DeeplGlossaryService;
use Ppl\PplDeeplV3Translate\Service\DeeplLanguageService;
use Ppl\PplDeeplV3Translate\Service\DeeplStyleRuleService;
use Ppl\PplDeeplV3Translate\Service\DeeplTranslationService;
use Ppl\PplDeeplV3Translate\Service\Api\V3RequestAdapter;
use Ppl\PplDeeplV3Translate\Service\FrontendAccessService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeeplController extends ActionController
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;

    public function interfaceAction(): ResponseInterface
    {
        $frontendAccessService = GeneralUtility::makeInstance(FrontendAccessService::class);
        $accessResponse = $frontendAccessService->buildAccessResponse((array)$this->settings, $this->request, $this->uriBuilder);
        if ($accessResponse !== null) {
            return $accessResponse;
        }

        $languageService = GeneralUtility::makeInstance(DeeplLanguageService::class);
        $apiClient = GeneralUtility::makeInstance(DeeplApiClientService::class);
        $apiAdapter = GeneralUtility::makeInstance(V3RequestAdapter::class, $languageService, $apiClient);
        $glossaryService = GeneralUtility::makeInstance(DeeplGlossaryService::class, $languageService, $apiAdapter);
        $styleRuleService = GeneralUtility::makeInstance(DeeplStyleRuleService::class, $languageService, $apiClient);
        $translationService = GeneralUtility::makeInstance(
            DeeplTranslationService::class,
            $languageService,
            $glossaryService,
            $apiAdapter,
            $styleRuleService,
            GeneralUtility::makeInstance(DeeplCustomInstructionConfigurationService::class)
        );
        $configurationService = GeneralUtility::makeInstance(DeeplConfigurationService::class);

        $authKey = $configurationService->getAuthKey();

        $inputText = '';
        $translatedText = null;
        $translationError = null;

        $useGlossary = true;
        $selectedSourceLanguage = DeeplLanguageService::DEFAULT_SOURCE_LANGUAGE;
        $selectedTargetLanguage = DeeplLanguageService::DEFAULT_TARGET_LANGUAGE;
        $selectedGlossaryId = '';
        $selectedStyleRuleId = '';
        $customInstructions = '';

        $languages = $languageService->getLanguages();
        $sourceLanguages = $languageService->getSourceLanguages();
        $targetLanguages = $languageService->getTargetLanguages();

        if ($this->request->hasArgument('language_source')) {
            $requestedSourceLanguage = trim((string)$this->request->getArgument('language_source'));
            if (array_key_exists($requestedSourceLanguage, $sourceLanguages)) {
                $selectedSourceLanguage = $requestedSourceLanguage;
            } elseif (array_key_exists($languageService->normalizeSourceLanguage($requestedSourceLanguage), $sourceLanguages)) {
                $selectedSourceLanguage = $languageService->normalizeSourceLanguage($requestedSourceLanguage);
            }
        }

        if ($this->request->hasArgument('language_ziel')) {
            $requestedTargetLanguage = trim((string)$this->request->getArgument('language_ziel'));
            if (array_key_exists($requestedTargetLanguage, $targetLanguages)) {
                $selectedTargetLanguage = $requestedTargetLanguage;
            }
        }

        $glossaryOptions = $glossaryService->getGlossaryOptionsForLanguagePair($selectedSourceLanguage, $selectedTargetLanguage);
        if (!$this->request->hasArgument('glossary_id') && $glossaryOptions !== []) {
            $selectedGlossaryId = (string)array_key_first($glossaryOptions);
        }
        $useGlossary = false;

        if ($this->request->hasArgument('textarea')) {
            $inputText = trim((string)$this->request->getArgument('textarea'));

            $selectedGlossaryId = $this->request->hasArgument('glossary_id')
                ? trim((string)$this->request->getArgument('glossary_id'))
                : '';
            if (!$glossaryService->isGlossaryAvailableForLanguagePair($selectedGlossaryId, $selectedSourceLanguage, $selectedTargetLanguage)) {
                $selectedGlossaryId = '';
            }
            $useGlossary = $selectedGlossaryId !== '';

            $selectedStyleRuleId = $this->request->hasArgument('style_rule_id')
                ? trim((string)$this->request->getArgument('style_rule_id'))
                : '';
            if (!$styleRuleService->isStyleRuleAvailableForLanguage($selectedStyleRuleId, $selectedTargetLanguage)) {
                $selectedStyleRuleId = '';
            }

            $customInstructions = $this->request->hasArgument('custom_instructions')
                ? trim((string)$this->request->getArgument('custom_instructions'))
                : '';

            if ($inputText === '') {
                $translationError = $this->translate('error.missingText');
            } elseif ($this->isSameLanguagePair($languageService, $selectedSourceLanguage, $selectedTargetLanguage)) {
                $translationError = $this->translate('error.sameLanguage');
            } elseif ($authKey === '') {
                $translationError = $this->translate('error.missingAuthKey.v3');
            } else {
                try {
                    $translatedText = $translationService->translateText(
                        $authKey,
                        $inputText,
                        $selectedSourceLanguage,
                        $selectedTargetLanguage,
                        $selectedGlossaryId,
                        $selectedStyleRuleId,
                        $customInstructions
                    );
                } catch (\Throwable $exception) {
                    $translationError = $this->translate('error.translation', [$exception->getMessage()]);
                }
            }
        }

        $selectionMode = $selectedStyleRuleId !== '' ? 'style_rule' : 'none';
        $styleRuleOptions = $styleRuleService->getStyleRuleOptionsForLanguage($selectedTargetLanguage);
        $selectionLabel = $selectedStyleRuleId !== '' ? ($styleRuleOptions[$selectedStyleRuleId] ?? $selectedStyleRuleId) : $this->translate('option.disabled');
        $glossaryOptionsByCombination = $glossaryService->getGlossaryOptionsByCombination();
        $styleRuleDisplayOptions = $styleRuleService->getStyleRuleDisplayOptions();
        $styleRuleOptionsByLanguage = $styleRuleService->getStyleRuleOptionsByLanguage();
        $frontendControlData = [
            'type' => 'text',
            'glossaryOptionsByCombination' => (object)$glossaryOptionsByCombination,
            'styleRuleOptions' => (object)$styleRuleDisplayOptions,
            'styleRuleOptionsByLanguage' => (object)$styleRuleOptionsByLanguage,
            'labels' => [
                'source' => $this->translate('badge.source'),
                'target' => $this->translate('badge.target'),
                'glossaryActive' => $this->translate('badge.glossaryActive'),
                'glossaryInactive' => $this->translate('badge.glossaryInactive'),
                'styleRulePrefix' => $this->translate('badge.styleRulePrefix'),
                'styleRuleInactive' => $this->translate('badge.styleRuleInactive'),
                'noGlossary' => $this->translate('option.noGlossary'),
                'glossaryAvailable' => $this->translate('hint.glossaryAvailable'),
                'noGlossaryApproved' => $this->translate('hint.noGlossary'),
                'disabled' => $this->translate('option.disabled'),
                'styleRuleAvailable' => $this->translate('hint.styleRuleAvailable'),
                'noStyleRule' => $this->translate('hint.noStyleRule'),
                'noStyleRuleForTarget' => $this->translate('hint.noStyleRuleForTarget'),
                'sameLanguage' => $this->translate('message.sameLanguage'),
            ],
        ];

        $this->view->assignMultiple([
            'apiCapabilities' => $translationService->getApiCapabilities(),
            'frontendAccessHeader' => $frontendAccessService->renderAccessHeader($this->request),
            'frontendControlDataJson' => $this->encodeJson($frontendControlData),
            'custom_instructions' => $customInstructions,
            'textarea' => $inputText,
            'translatedText' => $translatedText,
            'translationError' => $translationError,
            'useGlossary' => $selectedGlossaryId !== '',
            'languages' => $languages,
            'sourceLanguages' => $sourceLanguages,
            'targetLanguages' => $targetLanguages,
            'language_source' => $selectedSourceLanguage,
            'language_ziel' => $selectedTargetLanguage,
            'sourceLanguageLabel' => $sourceLanguages[$selectedSourceLanguage] ?? $languages[$selectedSourceLanguage] ?? 'English (British)',
            'sourceLanguageCode' => $selectedSourceLanguage,
            'targetLanguageLabel' => $targetLanguages[$selectedTargetLanguage] ?? $languages[$selectedTargetLanguage] ?? 'English (UK)',
            'targetLanguageCode' => $selectedTargetLanguage,
            'styleRuleOptions' => $styleRuleOptions,
            'styleRuleOptionsJson' => $this->encodeJson((object)$styleRuleService->getStyleRuleDisplayOptions()),
            'styleRuleOptionsByLanguageJson' => $this->encodeJson((object)$styleRuleService->getStyleRuleOptionsByLanguage()),
            'style_rule_id' => $selectedStyleRuleId,
            'selectionMode' => $selectionMode,
            'selectionLabel' => $selectionLabel,
            'sameLanguageSelected' => $this->isSameLanguagePair($languageService, $selectedSourceLanguage, $selectedTargetLanguage),
            'glossaryAvailable' => $glossaryService->hasGlossaryForLanguagePair($selectedSourceLanguage, $selectedTargetLanguage),
            'glossaryCombinationsJson' => $this->encodeJson((object)$glossaryService->getGlossaryCombinations()),
            'glossaryOptions' => $glossaryOptions,
            'glossaryOptionsByCombinationJson' => $this->encodeJson((object)$glossaryOptionsByCombination),
            'glossary_id' => $selectedGlossaryId,
        ]);

        return $this->htmlResponse();
    }

    private function translate(string $key, array $arguments = []): string
    {
        $label = LocalizationUtility::translate($key, 'PplDeeplV3Translate');
        if (!is_string($label)) {
            return $key;
        }

        return $arguments !== [] ? sprintf($label, ...$arguments) : $label;
    }

    private function isSameLanguagePair(DeeplLanguageService $languageService, string $sourceLanguage, string $targetLanguage): bool
    {
        return $languageService->normalizeGlossaryLanguage($sourceLanguage) === $languageService->normalizeGlossaryLanguage($targetLanguage);
    }

    private function encodeJson(mixed $data): string
    {
        return (string)json_encode($data, self::JSON_FLAGS);
    }
}
