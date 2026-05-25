# PPL DeepL V3 Translate

TYPO3 12.4 extension for DeepL V3 text and file translation in frontend content elements and backend modules.

The extension provides the product UI, TYPO3 controllers, templates and frontend access handling. DeepL HTTP communication and shared V3 approval storage are delegated to `ppl/ppl-deepl-v3-requests`.

## Features

- Frontend content element for text translation.
- Frontend content element for file translation.
- Backend module for text translation.
- Backend module for file translation.
- Backend configuration module for languages, glossaries, style rules and frontend access.
- Local approval workflow for fetched DeepL languages.
- Local approval workflow for fetched DeepL glossaries.
- Local approval workflow for fetched DeepL V3 style rules.
- Target-language filtering for glossaries and style rules.
- Optional custom instructions for text translation.
- Frontend access control with TYPO3/felogin login page mode by default, plus PPL inline login as an alternative.

## V3 Architecture

`ppl_deepl_v3_translate` is a standalone TYPO3 extension, but it does not talk to DeepL directly. The package uses the local adapter `V3RequestAdapter`, which calls services from `ppl_deepl_v3_requests`.

This keeps the translate package focused on TYPO3 behavior:

- Controllers and Fluid templates.
- Backend modules and frontend plugins.
- Validation and language/glossary/style-rule selection.
- UI access to shared approved data.
- Frontend login and logout handling.

The request package owns the DeepL REST details:

- API base URL.
- Auth key lookup.
- HTTP client calls.
- DeepL V3 response handling.

## Difference To V2

V2 and V3 are intentionally separate extensions.

V2 package:

- Extension key: `ppl_deepl_v2_translate`
- Composer package: `ppl/ppl-deepl-v2-translate`
- Namespace: `Ppl\PplDeeplV2Translate`
- DeepL access: `deeplcom/deepl-php`
- V3 request package: not required
- V3-only controls: not available

V3 package:

- Extension key: `ppl_deepl_v3_translate`
- Composer package: `ppl/ppl-deepl-v3-translate`
- Namespace: `Ppl\PplDeeplV3Translate`
- DeepL access: `ppl/ppl-deepl-v3-requests`
- Direct `DeepL\DeepLClient` usage: not used
- V3-only controls: style rules and custom instructions

Both extensions follow the same product surface where possible: similar modules, templates, language approval flow, glossary approval flow, frontend elements and access settings. The important difference is the API adapter and the V3-only capabilities.

## Requirements

- TYPO3 CMS 12.4 LTS
- PHP 8.2 or newer
- `ppl/ppl-deepl-v3-requests` 12.4
- A DeepL API key with access to the used V3 features

## Installation

Install the request package and the translate package:

```bash
composer require ppl/ppl-deepl-v3-requests:^12.4 ppl/ppl-deepl-v3-translate:^12.4
```

Run the TYPO3 extension setup if your deployment does not do it automatically:

```bash
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush
```

Include the shipped TypoScript setup when frontend content elements are used.

## DeepL Configuration

Configure the DeepL key in the request package, not in this translate package.

TYPO3 extension configuration:

```php
'EXTENSIONS' => [
    'ppl_deepl_v3_requests' => [
        'authKey' => 'your-deepl-auth-key',
        'apiBaseUrl' => 'https://api.deepl.com',
    ],
],
```

Use `https://api.deepl.com` for DeepL API Pro and `https://api-free.deepl.com` for DeepL API Free.

Do not commit API keys to the repository. This package ships no API key and no TypoScript auth-key fallback.

## Backend Workflow

Open the TYPO3 backend module group `PPL DeepL V3`.

Configuration module:

1. Fetch languages from DeepL.
2. Approve the languages that may be used in frontend and backend selections.
3. Fetch glossaries from DeepL.
4. Approve the glossaries that may be selectable.
5. Fetch style rules from DeepL.
6. Approve style rules and use their target-language metadata for filtering.
7. Configure frontend access behavior.

Translation module:

1. Select source and target language.
2. Enter text.
3. Optionally choose an approved glossary.
4. Optionally choose an approved V3 style rule.
5. Optionally enter custom instructions.
6. Translate and copy the output.

File translation module:

1. Select source and target language.
2. Optionally choose an approved glossary.
3. Upload a supported document.
4. Start document translation.

Supported file upload types are TXT, PDF, DOCX and PPTX.

## Frontend Workflow

Editors can create content elements for:

- DeepL V3 text translation.
- DeepL V3 file translation.

The frontend UI only exposes locally approved languages, glossaries and style rules. If no API key is configured, the UI renders a missing-key state instead of triggering a remote request.

## Frontend Access

The extension supports two frontend access modes. `Use Login by Page ID` is the default and recommended mode for most installations because it keeps authentication inside TYPO3/felogin and benefits from TYPO3 security and maintenance updates.

TYPO3/felogin login page:

- Redirects to the configured login page UID.
- Sends `return_url` and `redirect_url`.
- Requires the felogin plugin redirect mode `Defined by GET/POST Parameters`.

PPL inline login:

- Shows a login form directly before the DeepL element.
- Can authenticate allowed frontend users and, if enabled, backend users.
- Logout returns to the protected PPL page and shows the inline login again.

Custom site header login links are outside of the extension redirect flow. If they should return to the protected DeepL page, they need their own valid redirect handling.

## Shared Storage

Reusable DeepL V3 metadata is stored by `ppl_deepl_v3_requests` inside the TYPO3 var directory:

- `var/ppl_deepl_v3_requests/languages.json`
- `var/ppl_deepl_v3_requests/glossaries.json`
- `var/ppl_deepl_v3_requests/style-rules.json`
- `var/ppl_deepl_v3_requests/custom-instructions.json`

Existing files from `var/ppl_deepl_v3_translate/` are migrated by the request services on first read. New writes go to the request package storage path. These files are runtime data and should not be committed to package repositories.

## Security Notes

- Do not commit DeepL API keys.
- Do not expose backend-only modules to unauthenticated users.
- Frontend users should use TYPO3-compatible password hashes, for example passwords created or changed in the TYPO3 backend.
- The translate package does not directly depend on `deeplcom/deepl-php`.
- The translate package expects all V3 HTTP behavior to go through `ppl_deepl_v3_requests`.

## Release Line

Version `12.4.x` is the TYPO3 12.4 release line.

## License

This extension is released under the GNU General Public License v2.0 or later, matching `ppl_rights_management` and the common TYPO3 extension license. See `LICENSE`.
