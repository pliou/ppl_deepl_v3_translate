# Changelog

## Unreleased

- Normalizes frontend access to TYPO3/felogin login-page redirects only; stored legacy `ppl_login` values fall back to `login_page`.
- Removes effective PPL inline login, direct frontend/backend user password checks and backend-session-based frontend unlocking.
- Keeps the frontend plugin signatures `ppldeeplv3translate_deepl` and `ppldeeplv3translate_deeplfile` registered as TYPO3 14 content element types.
- Adds TYPO3 FormProtection token validation to backend configuration, approval, text translation and file translation POST actions.
- Adds server-side document upload validation for TXT, PDF, DOCX and PPTX with a 10 MiB per-file limit, MIME checks and magic-byte checks.
- Moves executable frontend inline JavaScript into `Resources/Public/Javascript/frontend-controls.js`; Fluid templates keep JSON data containers only.
- Serves translated documents via short-lived HMAC-signed download tokens from private var/transient storage instead of the public fileadmin path; adds a per-frontend-user/IP translation rate limit and a `ppl:deepl-v3:cleanup-downloads` command.
- Closes the previous public-download risk under `fileadmin/user_upload/translated/`: translated files are no longer written to the public path and are downloaded only through a private signed token.
- Updates release documentation to reference HDA DeepL V2 Translate as `hda-ppl/hda-deepl-v2-translate` and documents that V3 builds on the HDA V2 product and workflow foundation without depending on historical V2 package names.
- Removes TYPO3 scanner findings for direct upload-size calls, legacy Page TSconfig registration and deprecated plugin-type constants while keeping the existing frontend plugin signatures stable.
- Updates release documentation for TYPO3 14 with the official TYPO3 documentation link: https://docs.typo3.org/m/typo3/tutorial-getting-started/main/en-us/Installation/SystemRequirements/Index.html.

## 14.0.0

- Ports Composer metadata and extension constraints to TYPO3 14.
- Delegated V3 language, glossary, style-rule and custom-instruction configuration to `ppl_deepl_v3_requests`.
- Kept the existing Translate controllers and templates as product UI consumers of the shared request configuration.
- Added migration-compatible shared storage documentation.
- Adds frontend text and file translation content elements.
- Adds backend text translation, file translation and configuration modules.
- Adds approved DeepL V3 language, glossary and style rule handling.
- Adds frontend access control.
- Uses `ppl/ppl-deepl-v3-requests` for direct DeepL REST requests.
