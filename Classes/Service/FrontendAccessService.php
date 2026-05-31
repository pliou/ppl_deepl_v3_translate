<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class FrontendAccessService
{
    private const EXTENSION_KEY = 'ppl_deepl_v3_translate';
    private const MODE_LOGIN_PAGE = 'login_page';
    private const GLOBAL_FRONTEND_ACCESS_SETTINGS = [
        'frontendAccessMode',
        'loginPageUid',
        'showLogout',
    ];

    public function buildAccessResponse(
        array $settings,
        ServerRequestInterface $request,
        UriBuilder $uriBuilder
    ): ?ResponseInterface {
        if ($this->isLogoutRequest($request)) {
            if (!$this->isSameOriginRequest($request)) {
                return new HtmlResponse(
                    $this->renderNotice(
                        $this->translate('access.loginRequired'),
                        $this->translate('access.invalidLogoutRequest')
                    ),
                    400
                );
            }

            $returnUrl = $this->getSubmittedSafeReturnUrl($request) ?? $this->buildSafeReturnUrl($request);

            return new RedirectResponse(
                $this->buildFrontendLogoutUrl($settings, $request, $uriBuilder, $returnUrl),
                303
            );
        }

        if ($this->isFrontendUserLoggedIn($request)) {
            return null;
        }

        $loginPageUid = $this->getLoginPageUid($settings);
        $currentPageUid = $this->getCurrentPageUid($request);

        if ($loginPageUid > 0 && $loginPageUid !== $currentPageUid) {
            $returnUrl = $this->buildSafeReturnUrl($request);
            $loginUrl = $uriBuilder
                ->reset()
                ->setTargetPageUid($loginPageUid)
                ->setCreateAbsoluteUri(true)
                ->setArguments([
                    'return_url' => $returnUrl,
                    'redirect_url' => $returnUrl,
                ])
                ->build();

            return new RedirectResponse($loginUrl, 303);
        }

        return new HtmlResponse(
            $this->renderNotice(
                $this->translate('access.loginRequired'),
                $this->translate('access.loginPageMissing')
            )
        );
    }

    public function renderAccessHeader(ServerRequestInterface $request): string
    {
        if (!$this->getBooleanConfigurationValue([], 'showLogout', true)) {
            return '';
        }

        $userLabel = $this->getAuthenticatedFrontendUserLabel($request);
        if ($userLabel === '') {
            return '';
        }

        return '<div class="ppl-deepl-accessbar">'
            . '<span>' . sprintf($this->escape($this->translate('access.signedInAs')), $this->escape($userLabel)) . '</span>'
            . '<button class="ppl-deepl-button ppl-deepl-button--secondary" type="submit" name="ppl_deepl_logout" value="1" formmethod="post" formaction="' . $this->escape($this->buildSafeReturnUrl($request)) . '" formenctype="application/x-www-form-urlencoded" formnovalidate="formnovalidate">'
            . $this->escape($this->translate('access.logout'))
            . '</button>'
            . '</div>';
    }

    private function getCurrentPageUid(ServerRequestInterface $request): int
    {
        $routing = $request->getAttribute('routing');
        if (is_object($routing) && method_exists($routing, 'getPageId')) {
            return max(0, (int)$routing->getPageId());
        }

        $pageInformation = $request->getAttribute('frontend.page.information');
        foreach (['getId', 'getPageId'] as $methodName) {
            if (is_object($pageInformation) && method_exists($pageInformation, $methodName)) {
                return max(0, (int)$pageInformation->{$methodName}());
            }
        }

        $queryPageUid = (int)($request->getQueryParams()['id'] ?? 0);

        return max(0, $queryPageUid);
    }

    private function isFrontendUserLoggedIn(ServerRequestInterface $request): bool
    {
        return !empty($this->getRequestUserData($request, 'frontend.user')['uid']);
    }

    private function getRequestUserData(ServerRequestInterface $request, string $attributeName): array
    {
        $user = $request->getAttribute($attributeName);
        $userProperties = is_object($user) ? get_object_vars($user) : [];
        $userData = $userProperties['user'] ?? null;

        return is_array($userData) ? $userData : [];
    }

    private function getLoginPageUid(array $settings): int
    {
        $loginPageUid = (int)$this->getConfigurationValue($settings, 'loginPageUid');

        return $loginPageUid > 0 ? $loginPageUid : 0;
    }

    private function getBooleanConfigurationValue(array $settings, string $name, bool $default): bool
    {
        $value = $this->getConfigurationValue($settings, $name);
        if ($value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private function getConfigurationValue(array $settings, string $name): string
    {
        if ($name === 'frontendAccessMode') {
            return self::MODE_LOGIN_PAGE;
        }

        $extensionConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][self::EXTENSION_KEY] ?? [];
        if (in_array($name, self::GLOBAL_FRONTEND_ACCESS_SETTINGS, true)
            && is_array($extensionConfiguration)
            && array_key_exists($name, $extensionConfiguration)
            && trim((string)$extensionConfiguration[$name]) !== ''
        ) {
            return trim((string)$extensionConfiguration[$name]);
        }

        if (array_key_exists($name, $settings) && trim((string)$settings[$name]) !== '') {
            return trim((string)$settings[$name]);
        }

        if (is_array($extensionConfiguration)
            && array_key_exists($name, $extensionConfiguration)
            && trim((string)$extensionConfiguration[$name]) !== ''
        ) {
            return trim((string)$extensionConfiguration[$name]);
        }

        return $this->getTypoScriptFallbackValue($name);
    }

    private function getTypoScriptFallbackValue(string $settingName): string
    {
        $constantsFile = ExtensionManagementUtility::extPath(self::EXTENSION_KEY) . 'Configuration/TypoScript/constants.typoscript';
        if (!is_file($constantsFile)) {
            return '';
        }

        $contents = (string)file_get_contents($constantsFile);
        if (!preg_match('/^plugin\\.tx_ppldeeplv3translate\\.settings\\.' . preg_quote($settingName, '/') . '\\s*=\\s*(.*?)\\s*$/m', $contents, $matches)) {
            return '';
        }

        return trim((string)($matches[1] ?? ''));
    }

    private function buildSafeReturnUrl(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $queryParams = [];
        parse_str($uri->getQuery(), $queryParams);
        unset(
            $queryParams['logintype'],
            $queryParams['ppl_deepl_logout'],
            $queryParams['return_url'],
            $queryParams['redirect_url']
        );

        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $path = $uri->getPath() !== '' ? $uri->getPath() : '/';

        return $path . ($query !== '' ? '?' . $query : '');
    }

    private function getSubmittedSafeReturnUrl(ServerRequestInterface $request): ?string
    {
        $parsedBody = $request->getParsedBody();
        $body = is_array($parsedBody) ? $parsedBody : [];

        foreach (['return_url', 'redirect_url'] as $fieldName) {
            $returnUrl = trim((string)($body[$fieldName] ?? ''));
            if ($this->isSafeLocalReturnUrl($returnUrl)) {
                return $returnUrl;
            }
        }

        return null;
    }

    private function isSafeLocalReturnUrl(string $returnUrl): bool
    {
        if ($returnUrl === '' || $returnUrl[0] !== '/' || str_starts_with($returnUrl, '//')) {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $returnUrl)) {
            return false;
        }

        $parts = parse_url($returnUrl);

        return is_array($parts) && !isset($parts['scheme'], $parts['host']);
    }

    private function buildTypo3LogoutUrl(
        array $settings,
        ServerRequestInterface $request,
        UriBuilder $uriBuilder,
        string $returnUrl
    ): string {
        $loginPageUid = $this->getLoginPageUid($settings);
        if ($loginPageUid <= 0) {
            return '';
        }

        return $uriBuilder
            ->reset()
            ->setTargetPageUid($loginPageUid)
            ->setCreateAbsoluteUri(true)
            ->setArguments([
                'logintype' => 'logout',
                'return_url' => $returnUrl,
                'redirect_url' => $returnUrl,
            ])
            ->build();
    }

    private function buildFrontendLogoutUrl(
        array $settings,
        ServerRequestInterface $request,
        UriBuilder $uriBuilder,
        string $returnUrl
    ): string {
        $logoutUrl = $this->buildTypo3LogoutUrl($settings, $request, $uriBuilder, $returnUrl);
        if ($logoutUrl !== '') {
            return $logoutUrl;
        }

        return $this->buildCurrentPageTypo3LogoutUrl($request, $returnUrl);
    }

    private function buildCurrentPageTypo3LogoutUrl(ServerRequestInterface $request, string $returnUrl): string
    {
        $uri = $request->getUri();
        $queryParams = [];
        parse_str($uri->getQuery(), $queryParams);
        unset(
            $queryParams['logintype'],
            $queryParams['ppl_deepl_logout'],
            $queryParams['return_url'],
            $queryParams['redirect_url']
        );

        $queryParams['logintype'] = 'logout';
        $queryParams['return_url'] = $returnUrl;
        $queryParams['redirect_url'] = $returnUrl;

        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $path = $uri->getPath() !== '' ? $uri->getPath() : '/';

        return $path . ($query !== '' ? '?' . $query : '');
    }

    private function isLogoutRequest(ServerRequestInterface $request): bool
    {
        $parsedBody = $request->getParsedBody();
        $body = is_array($parsedBody) ? $parsedBody : [];

        return (string)($request->getQueryParams()['ppl_deepl_logout'] ?? '') === '1'
            || (string)($body['ppl_deepl_logout'] ?? '') === '1';
    }

    private function isSameOriginRequest(ServerRequestInterface $request): bool
    {
        $origin = trim($request->getHeaderLine('Origin'));
        if ($origin !== '') {
            return $this->isSameHostUrl($origin, $request);
        }

        $referer = trim($request->getHeaderLine('Referer'));
        if ($referer !== '') {
            return $this->isSameHostUrl($referer, $request);
        }

        return true;
    }

    private function isSameHostUrl(string $url, ServerRequestInterface $request): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $requestHost = strtolower($request->getUri()->getHost());
        $originHost = strtolower((string)$parts['host']);

        return $requestHost !== '' && hash_equals($requestHost, $originHost);
    }

    private function getAuthenticatedFrontendUserLabel(ServerRequestInterface $request): string
    {
        $frontendUserData = $this->getRequestUserData($request, 'frontend.user');
        if (!empty($frontendUserData['uid'])) {
            return (string)($frontendUserData['username'] ?? $frontendUserData['name'] ?? 'Frontend user');
        }

        return '';
    }

    private function renderNotice(string $title, string $message): string
    {
        return '<div class="ppl-deepl-frontend ppl-deepl-login-shell">'
            . '<section class="ppl-deepl-card ppl-deepl-login-card">'
            . '<div class="ppl-deepl-card__head"><strong>' . $this->escape($title) . '</strong></div>'
            . '<div class="ppl-deepl-card__body"><p class="ppl-deepl-login__intro">' . $this->escape($message) . '</p></div>'
            . '</section>'
            . '</div>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function translate(string $key): string
    {
        $label = LocalizationUtility::translate($key, 'PplDeeplV3Translate');

        return is_string($label) ? $label : $key;
    }
}
