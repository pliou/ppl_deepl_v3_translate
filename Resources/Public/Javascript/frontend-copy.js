(function () {
    'use strict';

    if (window.pplDeeplFrontendCopyInitialized) {
        return;
    }

    window.pplDeeplFrontendCopyInitialized = true;

    function getCopyTargetElement(button) {
        return document.getElementById(button.getAttribute('data-copy-target') || '');
    }

    function getCopyTargetValue(button) {
        var target = getCopyTargetElement(button);
        if (!target) {
            return '';
        }

        return String(typeof target.value === 'string' ? target.value : target.textContent || '');
    }

    function updateCopyButton(button) {
        button.disabled = getCopyTargetValue(button).trim() === '';
    }

    function focusWithoutScroll(element) {
        try {
            element.focus({ preventScroll: true });
        } catch (error) {
            element.focus();
        }
    }

    function copyFallback(text, target) {
        var activeElement;
        var textarea;
        var copied;

        if (target && typeof target.select === 'function') {
            activeElement = document.activeElement;
            focusWithoutScroll(target);
            target.select();
            if (typeof target.setSelectionRange === 'function') {
                target.setSelectionRange(0, String(target.value || '').length);
            }
            copied = document.execCommand('copy');
            if (activeElement && typeof activeElement.focus === 'function' && activeElement !== target) {
                focusWithoutScroll(activeElement);
            }
            if (copied) {
                return true;
            }
        }

        textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.top = '-1000px';
        textarea.style.left = '-1000px';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        focusWithoutScroll(textarea);
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);
        copied = document.execCommand('copy');
        document.body.removeChild(textarea);

        return copied;
    }

    async function copyText(text, target) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return true;
        }

        return copyFallback(text, target);
    }

    function getCopiedLabel(button) {
        var root = button.closest('.ppl-deepl-frontend');
        return root && root.getAttribute('data-message-copied')
            ? root.getAttribute('data-message-copied')
            : 'Copied';
    }

    async function copyField(button) {
        var target = getCopyTargetElement(button);
        var text = getCopyTargetValue(button);
        var originalText = button.textContent;

        if (text.trim() === '') {
            updateCopyButton(button);
            return;
        }

        try {
            await copyText(text, target);
        } catch (error) {
            copyFallback(text, target);
        }

        button.textContent = getCopiedLabel(button);
        window.setTimeout(function () {
            button.textContent = originalText;
            updateCopyButton(button);
        }, 1400);
    }

    function initialize() {
        document.querySelectorAll('[data-copy-target]').forEach(function (button) {
            var target = getCopyTargetElement(button);

            updateCopyButton(button);
            button.addEventListener('click', function (event) {
                event.preventDefault();
                copyField(button);
            });

            if (target) {
                ['input', 'change'].forEach(function (eventName) {
                    target.addEventListener(eventName, function () {
                        updateCopyButton(button);
                    });
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
