# Changelog

## Unreleased

- Changed the frontend access default to `Use Login by Page ID`.
- Marked TYPO3/felogin login page mode as the recommended frontend access mode.

## 12.4.1

- Delegated V3 language, glossary, style-rule and custom-instruction configuration to `ppl_deepl_v3_requests`.
- Kept the existing Translate controllers and templates as product UI consumers of the shared request configuration.
- Added migration-compatible shared storage documentation.

## 12.4.0

- Release for TYPO3 12.4 LTS.
- Adds frontend text and file translation content elements.
- Adds backend text translation, file translation and configuration modules.
- Adds approved DeepL V3 language, glossary and style rule handling.
- Adds frontend access control with PPL inline login or TYPO3/felogin login page redirects.
- Uses `ppl/ppl-deepl-v3-requests` for direct DeepL REST requests.
