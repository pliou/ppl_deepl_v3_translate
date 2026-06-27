<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Controller;

use Ppl\PplDeeplV3Translate\Service\FrontendAccessConfigurationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

#[AsController]
final class BackendFrontendAccessController
{
    private const FORM_NAME = 'ppl_deepl_v3_translate_frontend_access';
    private const FORM_ACTION = 'frontend_access';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly PageRenderer $pageRenderer,
        private readonly FormProtectionFactory $formProtectionFactory,
        private readonly FrontendAccessConfigurationService $frontendAccessConfigurationService
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->getBody($request);
        $messages = [];
        $action = (string)($body['module_action'] ?? '');
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $formToken = $formProtection->generateToken(self::FORM_NAME, self::FORM_ACTION);

        if ($action !== ''
            && !$formProtection->validateToken((string)($body['form_token'] ?? ''), self::FORM_NAME, self::FORM_ACTION)
        ) {
            $action = '';
            $messages[] = [
                'type' => 'error',
                'text' => $this->translate('message.invalidFormToken'),
            ];
        }

        if ($action === 'save_frontend_access') {
            try {
                $this->frontendAccessConfigurationService->saveConfiguration(
                    (string)($body['login_page_uid'] ?? ''),
                    (string)($body['show_logout'] ?? '0')
                );
                $messages[] = [
                    'type' => 'success',
                    'text' => $this->translate('message.frontendAccessSaved'),
                ];
            } catch (\Throwable $exception) {
                $messages[] = [
                    'type' => 'error',
                    'text' => $this->translate('message.frontendAccessSaveFailed', [$exception->getMessage()]),
                ];
            }
        }

        $this->pageRenderer->addCssFile('EXT:ppl_deepl_v3_translate/Resources/Public/Css/site.css');
        $this->pageRenderer->addCssFile('EXT:ppl_deepl_v3_translate/Resources/Public/Css/backend.css');

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setModuleClass('ppl-deepl-v3-config-module');
        $moduleTemplate->setTitle($this->translate('config.frontendAccess'));
        $moduleTemplate->assignMultiple([
            'frontendAccessConfiguration' => $this->frontendAccessConfigurationService->getConfiguration(),
            'formToken' => $formToken,
            'messages' => $messages,
            'routeFrontendAccess' => (string)$this->uriBuilder->buildUriFromRoute('ppl_deepl_v3_frontend_access'),
        ]);

        return $moduleTemplate->renderResponse('Backend/FrontendAccess');
    }

    private function getBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    private function translate(string $key, array $arguments = []): string
    {
        $label = LocalizationUtility::translate($key, 'PplDeeplV3Translate');
        if (!is_string($label)) {
            return $key;
        }

        return $arguments !== [] ? sprintf($label, ...$arguments) : $label;
    }
}
