(function () {
    'use strict';

    function readJsonElementText(element) {
        if (!element) {
            return '';
        }

        if (element.content && typeof element.content.textContent === 'string') {
            return element.content.textContent || '';
        }

        return element.textContent || '';
    }

    function readBackendData() {
        var element = document.getElementById('pplDeeplV3BackendData');
        if (!element) {
            return {};
        }

        try {
            return JSON.parse(readJsonElementText(element) || '{}') || {};
        } catch (error) {
            return {};
        }
    }

    function getCopySourceElement(button) {
        return document.getElementById(button.getAttribute('data-copy-source') || '');
    }

    function getCopySourceValue(button) {
        var target = getCopySourceElement(button);
        if (!target) {
            return '';
        }

        return String(typeof target.value === 'string' ? target.value : target.textContent || '');
    }

    function updateCopyButton(button) {
        button.disabled = getCopySourceValue(button).trim() === '';
    }

    function focusWithoutScroll(element) {
        try {
            element.focus({ preventScroll: true });
        } catch (error) {
            element.focus();
        }
    }

    function copyFromTargetElement(target) {
        var activeElement;
        var copied;

        if (!target || typeof target.select !== 'function') {
            return false;
        }

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

        return copied;
    }

    function getParentDocument() {
        try {
            if (window.parent && window.parent !== window && window.parent.document) {
                return window.parent.document;
            }
        } catch (error) {
        }

        return null;
    }

    function copyTextInDocument(text, ownerDocument) {
        var temporaryTextarea;
        var activeElement;
        var copied;

        if (!ownerDocument || !ownerDocument.body) {
            return false;
        }

        activeElement = ownerDocument.activeElement;
        temporaryTextarea = ownerDocument.createElement('textarea');
        temporaryTextarea.value = text;
        temporaryTextarea.setAttribute('readonly', 'readonly');
        temporaryTextarea.style.position = 'fixed';
        temporaryTextarea.style.top = '-1000px';
        temporaryTextarea.style.left = '-1000px';
        temporaryTextarea.style.opacity = '0';
        ownerDocument.body.appendChild(temporaryTextarea);
        if (ownerDocument.defaultView && typeof ownerDocument.defaultView.focus === 'function') {
            ownerDocument.defaultView.focus();
        }
        focusWithoutScroll(temporaryTextarea);
        temporaryTextarea.select();
        temporaryTextarea.setSelectionRange(0, temporaryTextarea.value.length);
        copied = ownerDocument.execCommand('copy');
        ownerDocument.body.removeChild(temporaryTextarea);

        if (activeElement && typeof activeElement.focus === 'function' && activeElement !== temporaryTextarea) {
            focusWithoutScroll(activeElement);
        }

        return copied;
    }

    function copyTextFallback(text, target) {
        var parentDocument = getParentDocument();

        if (parentDocument && copyTextInDocument(text, parentDocument)) {
            return true;
        }

        if (copyFromTargetElement(target)) {
            return true;
        }

        return copyTextInDocument(text, document);
    }

    async function writeClipboardInWindow(targetWindow, text) {
        try {
            if (targetWindow.navigator.clipboard && targetWindow.isSecureContext) {
                await targetWindow.navigator.clipboard.writeText(text);
                return true;
            }
        } catch (error) {
        }

        return false;
    }

    async function copyText(text, target) {
        if (await writeClipboardInWindow(window, text)) {
            return true;
        }

        if (window.parent && window.parent !== window && await writeClipboardInWindow(window.parent, text)) {
            return true;
        }

        return copyTextFallback(text, target);
    }

    async function copyField(button, copiedLabel) {
        var target = getCopySourceElement(button);
        var text = getCopySourceValue(button);
        var originalText = button.textContent;

        if (text.trim() === '') {
            return;
        }

        try {
            await copyText(text, target);
        } catch (error) {
            copyTextFallback(text, target);
        }

        button.textContent = copiedLabel;
        window.setTimeout(function () {
            button.textContent = originalText;
            updateCopyButton(button);
        }, 1400);
    }

    function initialize() {
        var data = readBackendData();
        var copiedLabel = data.labels && data.labels.copied ? data.labels.copied : 'Copied';

        document.querySelectorAll('[data-copy-source]').forEach(function (button) {
            var target = getCopySourceElement(button);

            updateCopyButton(button);

            button.addEventListener('click', function (event) {
                event.preventDefault();
                copyField(button, copiedLabel);
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
