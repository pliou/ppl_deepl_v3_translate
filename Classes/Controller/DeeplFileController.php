<?php

namespace Ppl\PplDeeplV3Translate\Controller;

use Ppl\PplDeeplV3Requests\Service\DeeplApiClientService;
use Ppl\PplDeeplV3Requests\Service\DeeplCustomInstructionConfigurationService;
use Ppl\PplDeeplV3Requests\Service\DeeplConfigurationService;
use Ppl\PplDeeplV3Translate\Service\DeeplGlossaryService;
use Ppl\PplDeeplV3Translate\Service\DeeplLanguageService;
use Ppl\PplDeeplV3Translate\Service\DeeplStyleRuleService;
use Ppl\PplDeeplV3Translate\Service\DeeplTranslationService;
use Ppl\PplDeeplV3Translate\Service\DocumentUploadValidationService;
use Ppl\PplDeeplV3Translate\Service\Api\V3RequestAdapter;
use Ppl\PplDeeplV3Translate\Service\FrontendAccessService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class DeeplFileController extends ActionController
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;

    public function indexAction(): ResponseInterface
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
        $uploadValidationService = GeneralUtility::makeInstance(DocumentUploadValidationService::class);

        $translatedFilePath = null;
        $translatedFileName = null;
        $errorMessage = null;
        $authKey = $configurationService->getAuthKey();
        $languages = $languageService->getLanguages();
        $sourceLanguages = $languageService->getSourceLanguages();
        $targetLanguages = $languageService->getTargetLanguages();
        $languageSource = DeeplLanguageService::DEFAULT_SOURCE_LANGUAGE;
        $languageDest = DeeplLanguageService::DEFAULT_TARGET_LANGUAGE;
        $selectedGlossaryId = '';

        if ($this->request->hasArgument('language_source')) {
            $requestedLanguage = (string)$this->request->getArgument('language_source');
            if (array_key_exists($requestedLanguage, $sourceLanguages)) {
                $languageSource = $requestedLanguage;
            } elseif (array_key_exists($languageService->normalizeSourceLanguage($requestedLanguage), $sourceLanguages)) {
                $languageSource = $languageService->normalizeSourceLanguage($requestedLanguage);
            }
        }

        if ($this->request->hasArgument('language_ziel')) {
            $requestedLanguage = (string)$this->request->getArgument('language_ziel');
            if (array_key_exists($requestedLanguage, $targetLanguages)) {
                $languageDest = $requestedLanguage;
            }
        }

        if ($this->request->hasArgument('glossary_id')) {
            $selectedGlossaryId = trim((string)$this->request->getArgument('glossary_id'));
            if (!$glossaryService->isGlossaryAvailableForLanguagePair($selectedGlossaryId, $languageSource, $languageDest)) {
                $selectedGlossaryId = '';
            }
        } else {
            $glossaryOptions = $glossaryService->getGlossaryOptionsForLanguagePair($languageSource, $languageDest);
            if ($glossaryOptions !== []) {
                $selectedGlossaryId = (string)array_key_first($glossaryOptions);
            }
        }

        if (
            isset($_FILES['tx_ppldeeplv3translate_deeplfile']['tmp_name']['userfile'])
            && is_uploaded_file($_FILES['tx_ppldeeplv3translate_deeplfile']['tmp_name']['userfile'])
        ) {
            $uploadedFile = $_FILES['tx_ppldeeplv3translate_deeplfile'];
            $tmpFile = $uploadedFile['tmp_name']['userfile'];
            $originalName = (string)$uploadedFile['name']['userfile'];
            $fileSize = isset($uploadedFile['size']['userfile']) ? (int)$uploadedFile['size']['userfile'] : null;
            $validationError = $uploadValidationService->validateFile($tmpFile, $originalName, $fileSize);

            if ($validationError !== null) {
                $errorMessage = $this->translate($validationError);
            } elseif ($authKey === '') {
                $errorMessage = $this->translate('error.missingAuthKey.v3');
            } elseif ($this->isSameLanguagePair($languageService, $languageSource, $languageDest)) {
                $errorMessage = $this->translate('error.sameLanguage');
            } else {
                $safeOriginalName = $uploadValidationService->sanitizeOriginalFileName($originalName);
                $fileName = 'translated_' . date('Ymd-His') . '_' . $safeOriginalName;
                $targetDir = 'fileadmin/user_upload/translated/';
                $absoluteTargetDir = GeneralUtility::getFileAbsFileName($targetDir);

                if (!is_dir($absoluteTargetDir)) {
                    GeneralUtility::mkdir_deep($absoluteTargetDir);
                }

                $sourcePath = $absoluteTargetDir . 'original_' . $fileName;
                $targetPath = $absoluteTargetDir . $fileName;

                if (!move_uploaded_file($tmpFile, $sourcePath)) {
                    $errorMessage = $this->translate('error.uploadSaveFailed');
                } else {
                    try {
                        $translationService->translateDocument(
                            $authKey,
                            $sourcePath,
                            $targetPath,
                            $languageSource,
                            $languageDest,
                            $selectedGlossaryId
                        );

                        $translatedFilePath = '/' . $targetDir . $fileName;
                        $translatedFileName = $fileName;

                        if (file_exists($sourcePath)) {
                            unlink($sourcePath);
                        }
                    } catch (\Throwable $exception) {
                        $errorMessage = $this->translate('error.documentTranslation', [$exception->getMessage()]);

                        if (file_exists($sourcePath)) {
                            unlink($sourcePath);
                        }
                    }
                }
            }
        }

        $glossaryOptionsByCombination = $glossaryService->getGlossaryOptionsByCombination();
        $frontendControlData = [
            'type' => 'file',
            'glossaryOptionsByCombination' => (object)$glossaryOptionsByCombination,
            'styleRuleOptions' => (object)[],
            'styleRuleOptionsByLanguage' => (object)[],
            'labels' => [
                'noGlossary' => $this->translate('option.noGlossary'),
                'glossaryAvailable' => $this->translate('hint.glossaryAvailable'),
                'noGlossaryApproved' => $this->translate('hint.noGlossary'),
                'sameLanguage' => $this->translate('message.sameLanguage'),
            ],
        ];

        $this->view->assignMultiple([
            'frontendAccessHeader' => $frontendAccessService->renderAccessHeader($this->request),
            'frontendControlDataJson' => $this->encodeJson($frontendControlData),
            'translatedFilePath' => $translatedFilePath,
            'translatedFileName' => $translatedFileName,
            'errorMessage' => $errorMessage,
            'language_source' => $languageSource,
            'language_ziel' => $languageDest,
            'languages' => $languages,
            'sourceLanguages' => $sourceLanguages,
            'targetLanguages' => $targetLanguages,
            'useGlossary' => $selectedGlossaryId !== '',
            'glossaryAvailable' => $glossaryService->hasGlossaryForLanguagePair($languageSource, $languageDest),
            'glossaryCombinationsJson' => $this->encodeJson((object)$glossaryService->getGlossaryCombinations()),
            'glossaryOptions' => $glossaryService->getGlossaryOptionsForLanguagePair($languageSource, $languageDest),
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
