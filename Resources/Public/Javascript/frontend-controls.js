(function () {
    'use strict';

    var scrollStateKey = 'pplDeeplV3FrontendScrollState';

    function readJsonElementText(element) {
        if (!element) {
            return '';
        }

        return element.textContent || '';
    }

    function readControlData(root) {
        var element = root.querySelector('script[type="application/json"]');
        try {
            return JSON.parse(readJsonElementText(element) || '{}') || {};
        } catch (error) {
            return {};
        }
    }

    function getLabels(root, data) {
        return Object.assign({}, root.dataset || {}, data.labels || {});
    }

    function getLabel(labels, name) {
        return labels[name] || labels['label' + name.charAt(0).toUpperCase() + name.slice(1)] || labels['message' + name.charAt(0).toUpperCase() + name.slice(1)] || '';
    }

    function normalizeLanguage(language) {
        var normalizedLanguage = String(language || '').toUpperCase();

        if (normalizedLanguage === 'DE-DE') {
            return 'DE';
        }

        if (normalizedLanguage === 'EN-GB' || normalizedLanguage === 'EN-US') {
            return 'EN';
        }

        if (normalizedLanguage.indexOf('ES-') === 0) {
            return 'ES';
        }

        if (normalizedLanguage === 'PT-PT' || normalizedLanguage === 'PT-BR') {
            return 'PT';
        }

        if (normalizedLanguage === 'ZH-HANS' || normalizedLanguage === 'ZH-HANT') {
            return 'ZH';
        }

        return normalizedLanguage;
    }

    function normalizeStyleRuleLanguage(language) {
        var normalizedLanguage = String(language || '').toUpperCase();

        if (normalizedLanguage.indexOf('EN') === 0) {
            return 'EN';
        }

        if (normalizedLanguage === 'DE' || normalizedLanguage === 'DE-DE') {
            return 'DE';
        }

        if (normalizedLanguage.indexOf('ES') === 0) {
            return 'ES';
        }

        if (normalizedLanguage.indexOf('FR') === 0) {
            return 'FR';
        }

        if (normalizedLanguage.indexOf('IT') === 0) {
            return 'IT';
        }

        if (normalizedLanguage.indexOf('JA') === 0) {
            return 'JA';
        }

        if (normalizedLanguage.indexOf('KO') === 0) {
            return 'KO';
        }

        if (normalizedLanguage.indexOf('ZH') === 0) {
            return 'ZH';
        }

        return normalizedLanguage.split('-')[0] || normalizedLanguage;
    }

    function isSameLanguageValue(sourceLanguage, targetLanguage) {
        return sourceLanguage !== ''
            && targetLanguage !== ''
            && normalizeLanguage(sourceLanguage) === normalizeLanguage(targetLanguage);
    }

    function setBadgeState(element, text, active) {
        if (!element) {
            return;
        }

        element.textContent = text;
        element.classList.toggle('ppl-deepl-pill--success', active);
        element.classList.toggle('ppl-deepl-pill--danger', !active);
    }

    function updateTargetLanguageAvailability(source, target) {
        var selectedValue;
        var fallbackValue = '';

        if (!source || !target) {
            return;
        }

        selectedValue = target.value;
        Array.prototype.forEach.call(target.options, function (option) {
            var blocked = isSameLanguageValue(source.value, option.value);
            option.disabled = blocked;
            option.hidden = blocked;
            if (blocked) {
                option.setAttribute('aria-disabled', 'true');
            } else {
                option.removeAttribute('aria-disabled');
                if (option.value !== '' && fallbackValue === '') {
                    fallbackValue = option.value;
                }
            }
        });

        if (isSameLanguageValue(source.value, selectedValue) && fallbackValue !== '') {
            target.value = fallbackValue;
        }
    }

    function updateLanguageGuard(form, labels) {
        var source = form.querySelector('#language_source, #file_language_source');
        var target = form.querySelector('#language_ziel, #file_language_ziel');
        var submit = form.querySelector('#translateButton, #translateFileButton');
        var warning = form.querySelector('#languagePairWarning, #fileLanguageWarning');
        var message = getLabel(labels, 'sameLanguage');
        var sameLanguage;

        if (!source || !target || !submit) {
            return true;
        }

        updateTargetLanguageAvailability(source, target);
        sameLanguage = isSameLanguageValue(source.value, target.value);

        submit.disabled = sameLanguage;
        submit.title = sameLanguage ? message : '';
        source.setCustomValidity(sameLanguage ? message : '');
        target.setCustomValidity(sameLanguage ? message : '');

        if (warning) {
            warning.hidden = !sameLanguage;
            if (message !== '') {
                warning.textContent = message;
            }
        }

        return !sameLanguage;
    }

    function updateGlossaryOptions(form, data, labels, hint) {
        var source = form.querySelector('#language_source, #file_language_source');
        var target = form.querySelector('#language_ziel, #file_language_ziel');
        var glossary = form.querySelector('#glossary_id, #file_glossary_id');
        var optionsByCombination = data.glossaryOptionsByCombination || {};
        var combinationKey;
        var options;
        var selectedValue;
        var optionIds;
        var emptyOption;

        if (!source || !target || !glossary) {
            return;
        }

        combinationKey = normalizeLanguage(source.value) + ':' + normalizeLanguage(target.value);
        options = optionsByCombination[combinationKey] || {};
        selectedValue = glossary.value;
        optionIds = Object.keys(options);
        glossary.innerHTML = '';

        emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = getLabel(labels, 'noGlossary');
        glossary.appendChild(emptyOption);

        optionIds.forEach(function (id) {
            var option = document.createElement('option');
            option.value = id;
            option.textContent = options[id];
            if (id === selectedValue) {
                option.selected = true;
            }
            glossary.appendChild(option);
        });

        if (!Object.prototype.hasOwnProperty.call(options, selectedValue)) {
            glossary.value = optionIds[0] || '';
        }

        glossary.disabled = optionIds.length === 0;

        if (hint) {
            hint.textContent = optionIds.length > 0
                ? getLabel(labels, 'glossaryAvailable')
                : getLabel(labels, 'noGlossaryApproved') || getLabel(labels, 'noGlossaryHint');
        }
    }

    function updateStyleRuleOptions(form, data, labels) {
        var target = form.querySelector('#language_ziel');
        var styleRule = form.querySelector('#style_rule_id');
        var hint = form.querySelector('#styleRuleHint');
        var optionsByLanguage = data.styleRuleOptionsByLanguage || {};
        var allOptions = data.styleRuleOptions || {};
        var language;
        var options;
        var selectedValue;
        var optionIds;
        var emptyOption;

        if (!target || !styleRule) {
            return;
        }

        language = normalizeStyleRuleLanguage(target.value);
        options = language !== '' && optionsByLanguage[language] ? optionsByLanguage[language] : {};
        selectedValue = styleRule.value;
        optionIds = Object.keys(options);
        styleRule.innerHTML = '';

        emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = getLabel(labels, 'disabled');
        styleRule.appendChild(emptyOption);

        optionIds.forEach(function (id) {
            var option = document.createElement('option');
            option.value = id;
            option.textContent = options[id];
            if (id === selectedValue) {
                option.selected = true;
            }
            styleRule.appendChild(option);
        });

        if (!Object.prototype.hasOwnProperty.call(options, selectedValue)) {
            styleRule.value = '';
        }

        styleRule.disabled = optionIds.length === 0;

        if (hint) {
            hint.textContent = optionIds.length > 0
                ? getLabel(labels, 'styleRuleAvailable')
                : (Object.keys(allOptions).length > 0 ? getLabel(labels, 'noStyleRuleForTarget') : getLabel(labels, 'noStyleRule'));
        }
    }

    function updateTextBadges(form, labels) {
        var source = form.querySelector('#language_source');
        var target = form.querySelector('#language_ziel');
        var glossary = form.querySelector('#glossary_id');
        var styleRule = form.querySelector('#style_rule_id');
        var sourceBadge = form.querySelector('#sourceLanguageBadge');
        var targetBadge = form.querySelector('#targetLanguageBadge');
        var glossaryBadge = form.querySelector('#glossaryStatusBadge');
        var selectionBadge = form.querySelector('#selectionStatusBadge');
        var sourceLabel = source && source.selectedOptions[0] ? source.selectedOptions[0].textContent : '';
        var targetLabel = target && target.selectedOptions[0] ? target.selectedOptions[0].textContent : '';
        var styleRuleLabel = styleRule && styleRule.selectedOptions[0] ? styleRule.selectedOptions[0].textContent : '';

        setBadgeState(sourceBadge, getLabel(labels, 'source') + ': ' + sourceLabel, true);
        setBadgeState(targetBadge, getLabel(labels, 'target') + ': ' + targetLabel, true);
        setBadgeState(
            glossaryBadge,
            glossary && glossary.value !== '' ? getLabel(labels, 'glossaryActive') : getLabel(labels, 'glossaryInactive'),
            glossary && glossary.value !== ''
        );
        setBadgeState(
            selectionBadge,
            styleRule && styleRule.value !== '' ? getLabel(labels, 'styleRulePrefix') + ': ' + styleRuleLabel : getLabel(labels, 'styleRuleInactive'),
            styleRule && styleRule.value !== ''
        );
    }

    function clearServerErrors(form) {
        form.querySelectorAll('[data-role="deepl-server-error"]').forEach(function (message) {
            message.hidden = true;
        });
    }

    function updateFileState(form, data, labels) {
        var fileInput = form.querySelector('#userfile');
        var selectedFileBox = form.querySelector('#selectedFileBox');
        var selectedFileName = form.querySelector('#selectedFileName');
        var submit = form.querySelector('#translateFileButton');
        var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
        var languageIsValid = updateLanguageGuard(form, labels);

        if (selectedFileBox) {
            selectedFileBox.classList.toggle('is-visible', hasFile);
        }

        if (selectedFileName) {
            selectedFileName.textContent = hasFile ? fileInput.files[0].name : '';
        }

        if (submit) {
            submit.disabled = !hasFile || !languageIsValid;
        }

        updateGlossaryOptions(form, data, labels, form.querySelector('#fileGlossaryStatus'));
    }

    function saveScrollState(anchor) {
        try {
            window.sessionStorage.setItem(scrollStateKey, JSON.stringify({
                href: window.location.pathname + window.location.search,
                anchor: anchor || '',
                scrollY: window.scrollY || window.pageYOffset || 0
            }));
        } catch (error) {
            // Session storage can be unavailable in hardened browser contexts.
        }
    }

    function restoreScrollState() {
        var state;
        var target;

        try {
            state = JSON.parse(window.sessionStorage.getItem(scrollStateKey) || '{}');
        } catch (error) {
            state = {};
        }

        if (!state || state.href !== window.location.pathname + window.location.search) {
            return;
        }

        if (state.anchor) {
            target = document.querySelector('[data-scroll-anchor="' + state.anchor + '"]');
        }

        if (target && target.scrollIntoView) {
            target.scrollIntoView({block: 'center'});
            return;
        }

        if (typeof state.scrollY === 'number') {
            window.scrollTo(0, state.scrollY);
        }
    }

    function initializeText(root) {
        var form = root.closest('form');
        var data = readControlData(root);
        var labels = getLabels(root, data);

        if (!form) {
            return;
        }

        function update() {
            updateGlossaryOptions(form, data, labels, form.querySelector('#glossarySelectHint'));
            updateStyleRuleOptions(form, data, labels);
            updateLanguageGuard(form, labels);
            updateTextBadges(form, labels);
        }

        update();
        form.addEventListener('submit', function (event) {
            if (!updateLanguageGuard(form, labels)) {
                event.preventDefault();
                clearServerErrors(form);
                form.querySelector('#language_ziel').reportValidity();
                form.querySelector('#language_ziel').focus();
                return;
            }

            saveScrollState(event.submitter && event.submitter.getAttribute ? event.submitter.getAttribute('data-scroll-anchor') : '');
        });

        form.querySelectorAll('#language_source, #language_ziel, #glossary_id, #style_rule_id, textarea').forEach(function (field) {
            field.addEventListener('change', function () {
                clearServerErrors(form);
                update();
            });
            field.addEventListener('input', function () {
                clearServerErrors(form);
            });
        });
    }

    function initializeFile(root) {
        var form = root.closest('form');
        var data = readControlData(root);
        var labels = getLabels(root, data);
        var fileInput;
        var removeButton;

        if (!form) {
            return;
        }

        fileInput = form.querySelector('#userfile');
        removeButton = form.querySelector('#removeSelectedFileButton');

        updateFileState(form, data, labels);
        form.addEventListener('submit', function (event) {
            if (!updateLanguageGuard(form, labels)) {
                event.preventDefault();
                clearServerErrors(form);
                form.querySelector('#file_language_ziel').reportValidity();
                form.querySelector('#file_language_ziel').focus();
                return;
            }

            saveScrollState('');
        });

        form.querySelectorAll('#file_language_source, #file_language_ziel, #file_glossary_id, #userfile').forEach(function (field) {
            field.addEventListener('change', function () {
                clearServerErrors(form);
                updateFileState(form, data, labels);
            });
            field.addEventListener('input', function () {
                clearServerErrors(form);
            });
        });

        if (removeButton && fileInput) {
            removeButton.addEventListener('click', function () {
                fileInput.value = '';
                clearServerErrors(form);
                updateFileState(form, data, labels);
                fileInput.focus();
            });
        }
    }

    function initialize() {
        document.querySelectorAll('.ppl-deepl-frontend--text').forEach(initializeText);
        document.querySelectorAll('.ppl-deepl-frontend--file').forEach(initializeFile);
        restoreScrollState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
}());
