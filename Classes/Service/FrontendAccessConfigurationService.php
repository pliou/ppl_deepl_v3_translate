<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class FrontendAccessConfigurationService
{
    private const EXTENSION_KEY = 'ppl_deepl_v3_translate';
    private const MODE_PPL_LOGIN = 'ppl_login';
    private const MODE_LOGIN_PAGE = 'login_page';

    public function getConfiguration(): array
    {
        $extensionConfiguration = $this->getExtensionConfiguration();

        return [
            'frontendAccessMode' => $this->normalizeMode(
                $this->getStoredValue($extensionConfiguration, 'frontendAccessMode', self::MODE_PPL_LOGIN)
            ),
            'loginPageUid' => $this->normalizeLoginPageUidForDisplay(
                $this->getStoredValue($extensionConfiguration, 'loginPageUid', '')
            ),
            'allowFrontendUsers' => $this->normalizeBooleanForDisplay(
                $this->getStoredValue($extensionConfiguration, 'allowFrontendUsers', '1')
            ),
            'allowBackendUsers' => $this->normalizeBooleanForDisplay(
                $this->getStoredValue($extensionConfiguration, 'allowBackendUsers', '1')
            ),
            'showLogout' => $this->normalizeBooleanForDisplay(
                $this->getStoredValue($extensionConfiguration, 'showLogout', '1')
            ),
        ];
    }

    public function saveConfiguration(
        string $frontendAccessMode,
        string $loginPageUid,
        string $allowFrontendUsers,
        string $allowBackendUsers,
        string $showLogout
    ): void {
        $extensionConfiguration = $this->getExtensionConfiguration();
        unset($extensionConfiguration['frontendUserAccessEnabled']);
        $extensionConfiguration['frontendAccessMode'] = $this->normalizeMode($frontendAccessMode);
        $extensionConfiguration['allowFrontendUsers'] = $this->normalizeBooleanForStorage($allowFrontendUsers);
        $extensionConfiguration['allowBackendUsers'] = $this->normalizeBooleanForStorage($allowBackendUsers);
        $extensionConfiguration['showLogout'] = $this->normalizeBooleanForStorage($showLogout);
        $normalizedLoginPageUid = $this->normalizeLoginPageUidForStorage($loginPageUid);
        if ($normalizedLoginPageUid === '') {
            unset($extensionConfiguration['loginPageUid']);
        } else {
            $extensionConfiguration['loginPageUid'] = $normalizedLoginPageUid;
        }

        GeneralUtility::makeInstance(ExtensionConfiguration::class)->set(self::EXTENSION_KEY, $extensionConfiguration);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][self::EXTENSION_KEY] = $extensionConfiguration;
    }

    private function getStoredValue(array $extensionConfiguration, string $name, string $default): string
    {
        if (array_key_exists($name, $extensionConfiguration) && trim((string)$extensionConfiguration[$name]) !== '') {
            return trim((string)$extensionConfiguration[$name]);
        }

        $typoScriptValue = $this->getTypoScriptFallbackValue($name);
        if ($typoScriptValue !== '') {
            return $typoScriptValue;
        }

        return $default;
    }

    private function getExtensionConfiguration(): array
    {
        $extensionConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][self::EXTENSION_KEY] ?? [];

        return is_array($extensionConfiguration) ? $extensionConfiguration : [];
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, [self::MODE_PPL_LOGIN, self::MODE_LOGIN_PAGE], true)
            ? $mode
            : self::MODE_PPL_LOGIN;
    }

    private function normalizeLoginPageUidForDisplay(string $loginPageUid): string
    {
        $normalizedLoginPageUid = $this->normalizeLoginPageUidForStorage($loginPageUid);

        return $normalizedLoginPageUid === '0' ? '' : $normalizedLoginPageUid;
    }

    private function normalizeLoginPageUidForStorage(string $loginPageUid): string
    {
        $loginPageUid = trim($loginPageUid);
        if ($loginPageUid === '') {
            return '';
        }

        $loginPageUid = (int)$loginPageUid;

        return $loginPageUid > 0 ? (string)$loginPageUid : '';
    }

    private function normalizeBooleanForDisplay(string $value): bool
    {
        return $this->normalizeBooleanForStorage($value) === '1';
    }

    private function normalizeBooleanForStorage(string $value): string
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
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
}
