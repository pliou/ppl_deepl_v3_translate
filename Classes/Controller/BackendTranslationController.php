<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Controller;

use Ppl\PplDeeplV3Translate\Service\DeeplGlossaryService;
use Ppl\PplDeeplV3Translate\Service\DeeplLanguageService;
use Ppl\PplDeeplV3Translate\Service\DeeplStyleRuleService;
use Ppl\PplDeeplV3Translate\Service\DeeplTranslationService;
use Ppl\PplDeeplV3Requests\Service\DeeplConfigurationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

#[AsController]
final class BackendTranslationController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly PageRenderer $pageRenderer,
        private readonly DeeplConfigurationService $configurationService,
        private readonly DeeplLanguageService $languageService,
        private readonly DeeplGlossaryService $glossaryService,
        private readonly DeeplStyleRuleService $styleRuleService,
        private readonly DeeplTranslationService $translationService
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->getBody($request);
        $activeTab = $this->getActiveTab($request, $body);
        $authKey = $this->configurationService->getAuthKey();
        $messages = [];

        $textData = $this->getDefaultTextData();
        $fileData = $this->getDefaultFileData();

        $action = (string)($body['module_action'] ?? '');

        if ($action === 'translate_text') {
            $activeTab = 'translation';
            $textData = $this->handleTextTranslation($body, $authKey);
        }

        if ($action === 'translate_file') {
            $activeTab = 'file';
            $fileData = $this->handleFileTranslation($request, $body, $authKey);
        }

        $this->pageRenderer->addCssFile('EXT:ppl_deepl_v3_translate/Resources/Public/Css/site.css');
        $this->pageRenderer->addCssFile('EXT:ppl_deepl_v3_translate/Resources/Public/Css/backend.css');
        $this->pageRenderer->addJsFile('EXT:ppl_deepl_v3_translate/Resources/Public/Javascript/backend-scroll.js', 'module', true, false, '', true);
        $this->pageRenderer->addJsFile('EXT:ppl_deepl_v3_translate/Resources/Public/Javascript/backend-copy.js', 'module', true, false, '', true);

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setModuleClass('ppl-deepl-v3-module');
        $moduleTemplate->setTitle($this->translate('backend.title.v3'));
        $glossaryCombinations = $this->glossaryService->getGlossaryCombinations();
        $glossaryOptionsByCombination = $this->glossaryService->getGlossaryOptionsByCombination();
        $styleRuleDisplayOptions = $this->styleRuleService->getStyleRuleDisplayOptions();
        $styleRuleOptionsByLanguage = $this->styleRuleService->getStyleRuleOptionsByLanguage();
        $backendControlData = [
            'glossaryOptionsByCombination' => (object)$glossaryOptionsByCombination,
            'styleRuleOptions' => (object)$styleRuleDisplayOptions,
            'styleRuleOptionsByLanguage' => (object)$styleRuleOptionsByLanguage,
            'labels' => [
                'sameLanguage' => $this->translate('message.sameLanguage'),
                'noGlossary' => $this->translate('option.noGlossary'),
                'glossaryAvailable' => $this->translate('hint.glossaryAvailable'),
                'noGlossaryApproved' => $this->translate('hint.noGlossary'),
                'disabled' => $this->translate('option.disabled'),
                'styleRuleAvailable' => $this->translate('hint.styleRuleAvailable'),
                'noStyleRule' => $this->translate('hint.noStyleRule'),
                'noStyleRuleForTarget' => $this->translate('hint.noStyleRuleForTarget'),
                'styleRuleWrongLanguage' => $this->translate('option.styleRuleWrongLanguage'),
                'copied' => $this->translate('message.copied'),
            ],
        ];
        $moduleTemplate->assignMultiple([
            'activeTab' => $activeTab,
            'apiCapabilities' => $this->translationService->getApiCapabilities(),
            'authKeyConfigured' => $authKey !== '',
            'backendControlDataJson' => json_encode($backendControlData, JSON_THROW_ON_ERROR),
            'fileData' => $fileData,
            'glossaryCombinationsJson' => json_encode((object)$glossaryCombinations, JSON_THROW_ON_ERROR),
            'glossaryOptionsByCombinationJson' => json_encode((object)$glossaryOptionsByCombination, JSON_THROW_ON_ERROR),
            'languages' => $this->languageService->getLanguages(),
            'sourceLanguages' => $this->languageService->getSourceLanguages(),
            'targetLanguages' => $this->languageService->getTargetLanguages(),
            'messages' => $messages,
            'routeFile' => $this->buildRouteUrl('ppl_deepl_v3_file_translation'),
            'routeTranslation' => $this->buildRouteUrl('ppl_deepl_v3_translation'),
            'styleRuleOptions' => $this->styleRuleService->getStyleRuleOptions(),
            'styleRuleOptionsJson' => json_encode((object)$styleRuleDisplayOptions, JSON_THROW_ON_ERROR),
            'styleRuleOptionsByLanguageJson' => json_encode((object)$styleRuleOptionsByLanguage, JSON_THROW_ON_ERROR),
            'textData' => $textData,
        ]);

        return $moduleTemplate->renderResponse('Backend/Control');
    }

    private function handleTextTranslation(array $body, string $authKey): array
    {
        $data = $this->getDefaultTextData();
        $sourceLanguages = $this->languageService->getSourceLanguages();
        $targetLanguages = $this->languageService->getTargetLanguages();

        $data['textarea'] = trim((string)($body['textarea'] ?? ''));
        $data['language_source'] = $this->normalizePostedSourceLanguage((string)($body['language_source'] ?? ''), $sourceLanguages, DeeplLanguageService::DEFAULT_SOURCE_LANGUAGE);
        $data['language_ziel'] = $this->normalizePostedLanguage((string)($body['language_ziel'] ?? ''), $targetLanguages, DeeplLanguageService::DEFAULT_TARGET_LANGUAGE);
        $data['glossary_id'] = trim((string)($body['glossary_id'] ?? ''));
        if (!$this->glossaryService->isGlossaryAvailableForLanguagePair($data['glossary_id'], $data['language_source'], $data['language_ziel'])) {
            $data['glossary_id'] = '';
        }
        $data['style_rule_id'] = trim((string)($body['style_rule_id'] ?? ''));
        $data['custom_instructions'] = trim((string)($body['custom_instructions'] ?? ''));

        if (
            $data['style_rule_id'] !== ''
            && !$this->styleRuleService->isStyleRuleAvailableForLanguage($data['style_rule_id'], $data['language_ziel'])
        ) {
            $data['style_rule_id'] = '';
        }

        if ($data['textarea'] === '') {
            $data['translationError'] = $this->translate('error.missingText');
        } elseif ($this->isSameLanguagePair($data['language_source'], $data['language_ziel'])) {
            $data['translationError'] = $this->translate('error.sameLanguage');
        } elseif ($authKey === '') {
            $data['translationError'] = $this->translate('error.missingAuthKey.v3');
        } elseif ($data['translationError'] === null) {
            try {
                $data['translatedText'] = $this->translationService->translateText(
                    $authKey,
                    $data['textarea'],
                    $data['language_source'],
                    $data['language_ziel'],
                    $data['glossary_id'],
                    $data['style_rule_id'],
                    $data['custom_instructions']
                );
            } catch (\Throwable $exception) {
                $data['translationError'] = $this->translate('error.translation', [$exception->getMessage()]);
            }
        }

        $selectFallbackGlossary = !array_key_exists('glossary_id', $body)
            || trim((string)($body['glossary_id'] ?? '')) !== '';

        return $this->withSameLanguageState($this->withStyleRuleAvailability($this->withGlossaryAvailability($data, $selectFallbackGlossary)));
    }

    private function handleFileTranslation(ServerRequestInterface $request, array $body, string $authKey): array
    {
        $data = $this->getDefaultFileData();
        $sourceLanguages = $this->languageService->getSourceLanguages();
        $targetLanguages = $this->languageService->getTargetLanguages();
        $data['language_source'] = $this->normalizePostedSourceLanguage((string)($body['language_source'] ?? ''), $sourceLanguages, DeeplLanguageService::DEFAULT_SOURCE_LANGUAGE);
        $data['language_ziel'] = $this->normalizePostedLanguage((string)($body['language_ziel'] ?? ''), $targetLanguages, DeeplLanguageService::DEFAULT_TARGET_LANGUAGE);
        $data['glossary_id'] = trim((string)($body['glossary_id'] ?? ''));
        if (!$this->glossaryService->isGlossaryAvailableForLanguagePair($data['glossary_id'], $data['language_source'], $data['language_ziel'])) {
            $data['glossary_id'] = '';
        }

        $uploadedFile = $request->getUploadedFiles()['userfile'] ?? null;
        if (!$uploadedFile instanceof UploadedFileInterface || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $data['errorMessage'] = $this->translate('error.missingFile');
            return $this->withSameLanguageState($this->withGlossaryAvailability($data, false));
        }

        $originalName = (string)$uploadedFile->getClientFilename();
        $originalExtension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['txt', 'pdf', 'docx', 'pptx'];

        if (!in_array($originalExtension, $allowedExtensions, true)) {
            $data['errorMessage'] = $this->translate('error.invalidFileType');
        } elseif ($authKey === '') {
            $data['errorMessage'] = $this->translate('error.missingAuthKey.v3');
        } elseif ($this->isSameLanguagePair($data['language_source'], $data['language_ziel'])) {
            $data['errorMessage'] = $this->translate('error.sameLanguage');
        } else {
            $safeOriginalName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalName);
            $fileName = 'translated_' . date('Ymd-His') . '_' . $safeOriginalName;
            $targetDir = 'fileadmin/user_upload/translated/';
            $absoluteTargetDir = GeneralUtility::getFileAbsFileName($targetDir);

            if (!is_dir($absoluteTargetDir)) {
                GeneralUtility::mkdir_deep($absoluteTargetDir);
            }

            $sourcePath = $absoluteTargetDir . 'original_' . $fileName;
            $targetPath = $absoluteTargetDir . $fileName;

            try {
                $uploadedFile->moveTo($sourcePath);
                $this->translationService->translateDocument(
                    $authKey,
                    $sourcePath,
                    $targetPath,
                    $data['language_source'],
                    $data['language_ziel'],
                    $data['glossary_id']
                );
                $data['translatedFilePath'] = '/' . $targetDir . $fileName;
                $data['translatedFileName'] = $fileName;
            } catch (\Throwable $exception) {
                $data['errorMessage'] = $this->translate('error.documentTranslation', [$exception->getMessage()]);
            } finally {
                if (is_file($sourcePath)) {
                    unlink($sourcePath);
                }
            }
        }

        $selectFallbackGlossary = !array_key_exists('glossary_id', $body)
            || trim((string)($body['glossary_id'] ?? '')) !== '';

        return $this->withSameLanguageState($this->withGlossaryAvailability($data, $selectFallbackGlossary));
    }

    private function getDefaultTextData(): array
    {
        return $this->withSameLanguageState($this->withStyleRuleAvailability($this->withGlossaryAvailability([
            'custom_instructions' => '',
            'glossary_id' => '',
            'language_source' => DeeplLanguageService::DEFAULT_SOURCE_LANGUAGE,
            'language_ziel' => DeeplLanguageService::DEFAULT_TARGET_LANGUAGE,
            'style_rule_id' => '',
            'textarea' => '',
            'translatedText' => null,
            'translationError' => null,
        ])));
    }

    private function getDefaultFileData(): array
    {
        return $this->withSameLanguageState($this->withGlossaryAvailability([
            'errorMessage' => null,
            'glossary_id' => '',
            'language_source' => DeeplLanguageService::DEFAULT_SOURCE_LANGUAGE,
            'language_ziel' => DeeplLanguageService::DEFAULT_TARGET_LANGUAGE,
            'translatedFileName' => null,
            'translatedFilePath' => null,
        ]));
    }

    private function withGlossaryAvailability(array $data, bool $selectFallbackGlossary = true): array
    {
        $data['glossaryOptions'] = $this->glossaryService->getGlossaryOptionsForLanguagePair(
            (string)$data['language_source'],
            (string)$data['language_ziel']
        );
        $data['glossaryAvailable'] = $data['glossaryOptions'] !== [];

        if (!$this->glossaryService->isGlossaryAvailableForLanguagePair((string)($data['glossary_id'] ?? ''), (string)$data['language_source'], (string)$data['language_ziel'])) {
            $data['glossary_id'] = $selectFallbackGlossary ? (array_key_first($data['glossaryOptions']) ?? '') : '';
        }
        $data['useGlossary'] = $data['glossary_id'] !== '';

        return $data;
    }

    private function withStyleRuleAvailability(array $data): array
    {
        $data['styleRuleOptions'] = $this->styleRuleService->getStyleRuleOptionsForLanguage((string)$data['language_ziel']);
        $data['styleRuleAvailable'] = $data['styleRuleOptions'] !== [];

        if (!$this->styleRuleService->isStyleRuleAvailableForLanguage((string)($data['style_rule_id'] ?? ''), (string)$data['language_ziel'])) {
            $data['style_rule_id'] = '';
        }

        return $data;
    }

    private function withSameLanguageState(array $data): array
    {
        $data['sameLanguageSelected'] = $this->isSameLanguagePair(
            (string)$data['language_source'],
            (string)$data['language_ziel']
        );

        return $data;
    }

    private function normalizePostedLanguage(string $language, array $languages, string $fallback): string
    {
        return array_key_exists($language, $languages) ? $language : $fallback;
    }

    private function normalizePostedSourceLanguage(string $language, array $languages, string $fallback): string
    {
        if (array_key_exists($language, $languages)) {
            return $language;
        }

        $normalizedLanguage = $this->languageService->normalizeSourceLanguage($language);

        return array_key_exists($normalizedLanguage, $languages) ? $normalizedLanguage : $fallback;
    }

    private function isSameLanguagePair(string $sourceLanguage, string $targetLanguage): bool
    {
        return $this->languageService->normalizeGlossaryLanguage($sourceLanguage) === $this->languageService->normalizeGlossaryLanguage($targetLanguage);
    }

    private function markSelectedItems(array $items, array $selectedIds): array
    {
        $selectedLookup = array_fill_keys(array_map('strval', $selectedIds), true);

        foreach ($items as $index => $item) {
            $items[$index]['selected'] = isset($selectedLookup[(string)($item['id'] ?? '')]);
        }

        return $items;
    }

    private function getActiveTab(ServerRequestInterface $request, array $body): string
    {
        if (in_array(($body['active_tab'] ?? ''), ['file', 'translation'], true)) {
            return (string)$body['active_tab'];
        }

        $path = (string)$request->getUri()->getPath();

        return str_contains($path, '/file-translation') || str_contains($path, '/v3-file-translation') || str_contains($path, '/ppl-deepl-v3-file-translation')
            ? 'file'
            : 'translation';
    }

    private function getBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    private function buildRouteUrl(string $routeName): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute($routeName);
    }

    private function translate(string $key, array $arguments = []): string
    {
        return LocalizationUtility::translate($key, 'PplDeeplV3Translate', $arguments) ?? $key;
    }
}
