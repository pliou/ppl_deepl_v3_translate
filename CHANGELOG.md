# Changelog

## Unreleased

- Normalizes frontend access to TYPO3/felogin login-page redirects only; stored legacy `ppl_login` values fall back to `login_page`.
- Removes effective PPL inline login, direct frontend/backend user password checks and backend-session-based frontend unlocking.
- Keeps the frontend plugin signatures `ppldeeplv3translate_deepl` and `ppldeeplv3translate_deeplfile` registered as classic `list_type` plugins.
- Adds TYPO3 FormProtection token validation to backend configuration, approval, text translation and file translation POST actions.
- Adds server-side document upload validation for TXT, PDF, DOCX and PPTX with a 10 MiB per-file limit, MIME checks and magic-byte checks.
- Moves executable frontend inline JavaScript into `Resources/Public/Javascript/frontend-controls.js`; Fluid templates keep JSON data containers only.
- Documents the remaining public-download risk under `fileadmin/user_upload/translated/`.
- Updates release documentation to reference HDA DeepL V2 Translate as `hda-ppl/hda-deepl-v2-translate` and documents that V3 builds on the HDA V2 product and workflow foundation without depending on historical V2 package names.
- Notes TYPO3 v12 EOL / ELTS status with the official TYPO3 documentation link: https://docs.typo3.org/m/typo3/tutorial-getting-started/12.4/en-us/Installation/SystemRequirements/Index.html.

## 12.4.1

- Delegated V3 language, glossary, style-rule and custom-instruction configuration to `ppl_deepl_v3_requests`.
- Kept the existing Translate controllers and templates as product UI consumers of the shared request configuration.
- Added migration-compatible shared storage documentation.

## 12.4.0

- Release for TYPO3 12.4 LTS.
- Adds frontend text and file translation content elements.
- Adds backend text translation, file translation and configuration modules.
- Adds approved DeepL V3 language, glossary and style rule handling.
- Adds frontend access control.
- Uses `ppl/ppl-deepl-v3-requests` for direct DeepL REST requests.
