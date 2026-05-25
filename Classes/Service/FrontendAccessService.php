<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class FrontendAccessService
{
    private const EXTENSION_KEY = 'ppl_deepl_v3_translate';
    private const COOKIE_NAME = 'ppl_deepl_login';
    private const LOCAL_LOGOUT_COOKIE_NAME = 'ppl_deepl_logged_out';
    private const COOKIE_TTL = 86400;
    private const MODE_PPL_LOGIN = 'ppl_login';
    private const MODE_LOGIN_PAGE = 'login_page';
    private const GLOBAL_FRONTEND_ACCESS_SETTINGS = [
        'frontendAccessMode',
        'loginPageUid',
        'allowFrontendUsers',
        'allowBackendUsers',
        'showLogout',
    ];

    public function buildAccessResponse(
        array $settings,
        ServerRequestInterface $request,
        UriBuilder $uriBuilder
    ): ?ResponseInterface {
        $allowFrontendUsers = $this->getBooleanConfigurationValue($settings, 'allowFrontendUsers', true);
        $allowBackendUsers = $this->getBooleanConfigurationValue($settings, 'allowBackendUsers', true);
        $mode = $this->getFrontendAccessMode($settings);

        if ($this->isLogoutRequest($request)) {
            if (!$this->isSameOriginRequest($request)) {
                return new HtmlResponse(
                    $this->renderNotice(
                        $this->translate('access.loginRequired'),
                        $this->translate('access.loginFailed')
                    ),
                    400
                );
            }

            $returnUrl = $this->getSubmittedSafeReturnUrl($request) ?? $this->buildSafeReturnUrl($request);
            $logoutUrl = $mode === self::MODE_PPL_LOGIN
                ? $returnUrl
                : $this->buildFrontendLogoutUrl($settings, $request, $uriBuilder, $returnUrl);

            return (new RedirectResponse($logoutUrl, 303))
                ->withHeader('Set-Cookie', $this->buildLogoutCookieHeader($request));
        }

        if ($mode === self::MODE_LOGIN_PAGE) {
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

        if ($this->isPplLoginCookieValid($request, $allowFrontendUsers, $allowBackendUsers)) {
            return null;
        }

        $localLogoutActive = $this->isLocalLogoutActive($request);

        if (!$localLogoutActive && $allowBackendUsers && $this->isBackendUserLoggedIn($request)) {
            return null;
        }

        if (!$localLogoutActive && $allowFrontendUsers && $this->isFrontendUserLoggedIn($request)) {
            return null;
        }

        $parsedBody = $request->getParsedBody();
        $body = is_array($parsedBody) ? $parsedBody : [];
        $isPplLoginAttempt = ($body['ppl_deepl_logintype'] ?? '') === 'login';

        if ($isPplLoginAttempt) {
            $authenticatedUser = $this->authenticateSubmittedLogin($body, $allowFrontendUsers, $allowBackendUsers);
            if ($authenticatedUser !== null) {
                $returnUrl = $this->getSubmittedSafeReturnUrl($request) ?? $this->buildSafeReturnUrl($request);

                return (new RedirectResponse($returnUrl, 303))
                    ->withHeader('Set-Cookie', $this->buildLoginCookieHeader($authenticatedUser, $request));
            }
        }

        if (!$allowFrontendUsers && !$allowBackendUsers) {
            return new HtmlResponse(
                $this->renderNotice(
                    $this->translate('access.loginRequired'),
                    $this->translate('access.noAllowedUserTypes')
                )
            );
        }

        return new HtmlResponse(
            $this->renderInlineLogin($request, $isPplLoginAttempt, trim((string)($body['user'] ?? '')))
        );
    }

    public function renderAccessHeader(ServerRequestInterface $request): string
    {
        if (!$this->getBooleanConfigurationValue([], 'showLogout', true)) {
            return '';
        }

        $userLabel = $this->getAuthenticatedUserLabel($request);
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

    private function isBackendUserLoggedIn(ServerRequestInterface $request): bool
    {
        return !empty($this->getRequestUserData($request, 'backend.user')['uid']);
    }

    private function getRequestUserData(ServerRequestInterface $request, string $attributeName): array
    {
        $user = $request->getAttribute($attributeName);
        $userProperties = is_object($user) ? get_object_vars($user) : [];
        $userData = $userProperties['user'] ?? null;

        return is_array($userData) ? $userData : [];
    }

    private function isPplLoginCookieValid(
        ServerRequestInterface $request,
        bool $allowFrontendUsers,
        bool $allowBackendUsers
    ): bool {
        $cookie = (string)($request->getCookieParams()[self::COOKIE_NAME] ?? '');
        $payload = $this->decodeLoginCookie($cookie);
        if ($payload === null) {
            return false;
        }

        $userType = (string)($payload['userType'] ?? '');
        $userUid = (int)($payload['uid'] ?? 0);

        if ($userType === 'frontend' && $allowFrontendUsers) {
            return $this->isUserUidActive('fe_users', $userUid);
        }

        if ($userType === 'backend' && $allowBackendUsers) {
            return $this->isUserUidActive('be_users', $userUid);
        }

        return false;
    }

    private function authenticateSubmittedLogin(array $body, bool $allowFrontendUsers, bool $allowBackendUsers): ?array
    {
        $username = trim((string)($body['user'] ?? ''));
        $password = (string)($body['pass'] ?? '');
        if ($username === '' || $password === '') {
            return null;
        }

        if ($allowFrontendUsers) {
            $frontendUser = $this->getActiveUserByUsername('fe_users', $username);
            if ($frontendUser !== null && $this->isPasswordValid($password, (string)$frontendUser['password'], 'FE')) {
                return ['userType' => 'frontend', 'uid' => (int)$frontendUser['uid']];
            }
        }

        if ($allowBackendUsers) {
            $backendUser = $this->getActiveUserByUsername('be_users', $username);
            if ($backendUser !== null && $this->isPasswordValid($password, (string)$backendUser['password'], 'BE')) {
                return ['userType' => 'backend', 'uid' => (int)$backendUser['uid']];
            }
        }

        return null;
    }

    private function getActiveUserByUsername(string $tableName, string $username): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $now = time();
        $row = $queryBuilder
            ->select('uid', 'username', 'password')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($username)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('disable', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('starttime', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->lte('starttime', $queryBuilder->createNamedParameter($now, \PDO::PARAM_INT))
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('endtime', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->gt('endtime', $queryBuilder->createNamedParameter($now, \PDO::PARAM_INT))
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    private function getUserLabelByUid(string $tableName, int $uid): string
    {
        if ($uid <= 0 || !$this->isUserUidActive($tableName, $uid)) {
            return '';
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $username = $queryBuilder
            ->select('username')
            ->from($tableName)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return is_scalar($username) ? trim((string)$username) : '';
    }

    private function isUserUidActive(string $tableName, int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $now = time();
        $count = $queryBuilder
            ->count('uid')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('disable', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('starttime', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->lte('starttime', $queryBuilder->createNamedParameter($now, \PDO::PARAM_INT))
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('endtime', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->gt('endtime', $queryBuilder->createNamedParameter($now, \PDO::PARAM_INT))
                )
            )
            ->executeQuery()
            ->fetchOne();

        return (int)$count > 0;
    }

    private function isPasswordValid(string $plainPassword, string $passwordHash, string $mode): bool
    {
        if ($plainPassword === '' || $passwordHash === '') {
            return false;
        }

        try {
            $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->get($passwordHash, $mode);

            return $hashInstance->checkPassword($plainPassword, $passwordHash)
                || password_verify($plainPassword, $passwordHash);
        } catch (\Throwable) {
            return password_verify($plainPassword, $passwordHash);
        }
    }

    private function getFrontendAccessMode(array $settings): string
    {
        $mode = $this->getConfigurationValue($settings, 'frontendAccessMode');
        if (!in_array($mode, [self::MODE_PPL_LOGIN, self::MODE_LOGIN_PAGE], true)) {
            return self::MODE_LOGIN_PAGE;
        }

        return $mode;
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
            $queryParams['ppl_deepl_logintype'],
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
            $queryParams['ppl_deepl_logintype'],
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

    private function isLocalLogoutActive(ServerRequestInterface $request): bool
    {
        $payload = $this->decodeLoginCookie((string)($request->getCookieParams()[self::COOKIE_NAME] ?? ''));

        return $payload !== null && (bool)($payload['loggedOut'] ?? false);
    }

    private function buildLoginCookieHeader(array $authenticatedUser, ServerRequestInterface $request): string
    {
        $expires = time() + self::COOKIE_TTL;
        return $this->buildSignedCookieHeader([
            'uid' => (int)$authenticatedUser['uid'],
            'userType' => (string)$authenticatedUser['userType'],
            'expires' => $expires,
        ], $expires, $request);
    }

    private function buildLogoutCookieHeader(ServerRequestInterface $request): string
    {
        $expires = time() + self::COOKIE_TTL;

        return $this->buildSignedCookieHeader([
            'uid' => 0,
            'userType' => 'logged_out',
            'loggedOut' => true,
            'expires' => $expires,
        ], $expires, $request);
    }

    private function buildExpiredLoginCookieHeader(ServerRequestInterface $request): string
    {
        $cookie = self::COOKIE_NAME . '=deleted; Path=/; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT'
            . '; HttpOnly; SameSite=Lax';

        if ($request->getUri()->getScheme() === 'https') {
            $cookie .= '; Secure';
        }

        return $cookie;
    }

    private function buildSignedCookieHeader(array $payloadData, int $expires, ServerRequestInterface $request): string
    {
        $payload = base64_encode(json_encode($payloadData, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, $this->getSigningSecret());
        $maxAge = max(0, $expires - time());

        $cookie = self::COOKIE_NAME . '=' . rawurlencode($payload . '.' . $signature)
            . '; Path=/; Max-Age=' . $maxAge
            . '; Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT'
            . '; HttpOnly; SameSite=Lax';

        if ($request->getUri()->getScheme() === 'https') {
            $cookie .= '; Secure';
        }

        return $cookie;
    }

    private function decodeLoginCookie(string $cookie): ?array
    {
        if ($cookie === '') {
            return null;
        }

        $parts = explode('.', rawurldecode($cookie), 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payload, $this->getSigningSecret());
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $decodedPayload = base64_decode($payload, true);
        $data = is_string($decodedPayload) ? json_decode($decodedPayload, true) : null;
        if (!is_array($data) || (int)($data['expires'] ?? 0) < time()) {
            return null;
        }

        return $data;
    }

    private function getSigningSecret(): string
    {
        $secret = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');

        return $secret !== '' ? $secret : self::EXTENSION_KEY;
    }

    private function getAuthenticatedUserLabel(ServerRequestInterface $request): string
    {
        $cookie = (string)($request->getCookieParams()[self::COOKIE_NAME] ?? '');
        $payload = $this->decodeLoginCookie($cookie);
        if ($payload !== null) {
            $userType = (string)($payload['userType'] ?? '');
            $userUid = (int)($payload['uid'] ?? 0);
            if ($userType === 'frontend') {
                return $this->getUserLabelByUid('fe_users', $userUid);
            }

            if ($userType === 'backend') {
                return $this->getUserLabelByUid('be_users', $userUid);
            }
        }

        $backendUserData = $this->getRequestUserData($request, 'backend.user');
        if (!empty($backendUserData['uid'])) {
            return (string)($backendUserData['username'] ?? $backendUserData['realName'] ?? 'Backend user');
        }

        $frontendUserData = $this->getRequestUserData($request, 'frontend.user');
        if (!empty($frontendUserData['uid'])) {
            return (string)($frontendUserData['username'] ?? $frontendUserData['name'] ?? 'Frontend user');
        }

        return '';
    }

    private function renderInlineLogin(ServerRequestInterface $request, bool $loginAttemptFailed, string $submittedUsername): string
    {
        $returnUrl = $this->buildSafeReturnUrl($request);

        return '<div class="ppl-deepl-frontend ppl-deepl-login-shell">'
            . '<section class="ppl-deepl-card ppl-deepl-login-card">'
            . '<div class="ppl-deepl-card__head"><strong>' . $this->escape($this->translate('access.loginRequired')) . '</strong></div>'
            . '<div class="ppl-deepl-card__body">'
            . '<p class="ppl-deepl-login__intro">' . $this->escape($this->translate('access.loginIntro')) . '</p>'
            . ($loginAttemptFailed
                ? '<div class="ppl-deepl-alert">' . $this->escape($this->translate('access.loginFailed')) . '</div>'
                : '')
            . '<form class="ppl-deepl-login__form" method="post" action="' . $this->escape($returnUrl) . '" autocomplete="on">'
            . '<input type="hidden" name="ppl_deepl_logintype" value="login">'
            . '<input type="hidden" name="return_url" value="' . $this->escape($returnUrl) . '">'
            . '<input type="hidden" name="redirect_url" value="' . $this->escape($returnUrl) . '">'
            . '<label class="ppl-deepl-login__field"><span>' . $this->escape($this->translate('access.username')) . '</span>'
            . '<input class="ppl-deepl-login__input" type="text" name="user" autocomplete="username" required="required" value="' . $this->escape($submittedUsername) . '">'
            . '</label>'
            . '<label class="ppl-deepl-login__field"><span>' . $this->escape($this->translate('access.password')) . '</span>'
            . '<input class="ppl-deepl-login__input" type="password" name="pass" autocomplete="current-password" required="required">'
            . '</label>'
            . '<button type="submit" class="ppl-deepl-button ppl-deepl-button--primary ppl-deepl-login__submit">' . $this->escape($this->translate('access.submit')) . '</button>'
            . '</form>'
            . '</div>'
            . '</section>'
            . '</div>';
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
