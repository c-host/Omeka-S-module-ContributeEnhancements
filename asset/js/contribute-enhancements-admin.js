'use strict';

(function () {
    function releaseButton(button) {
        button.disabled = false;
        if (typeof CommonDialog !== 'undefined') {
            CommonDialog.spinnerDisable(button);
        }
    }

    function lockButton(button) {
        if (typeof CommonDialog !== 'undefined') {
            CommonDialog.spinnerEnable(button);
        }
        button.disabled = true;
    }

    function fetchJson(url, options) {
        return fetch(url, Object.assign({
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        }, options || {})).then(async response => {
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('Server returned a non-JSON response. Try refreshing the page and signing in again.');
            }

            return response.json();
        });
    }

    function fetchJsonGet(url) {
        return fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        }).then(async response => {
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('Server returned a non-JSON response. Try refreshing the page and signing in again.');
            }

            return response.json();
        });
    }

    function revisionLanguageInput(button) {
        const actions = button ? button.closest('.contribute-enhancements-value-actions') : null;
        if (!actions) {
            return null;
        }

        return actions.querySelector('.contribute-enhancements-revision-language__input');
    }

    function appendLanguageQuery(url, button) {
        const input = revisionLanguageInput(button);
        if (!input) {
            return url;
        }

        const language = (input.value || '').trim();
        const separator = url.includes('?') ? '&' : '?';

        return url + separator + 'language=' + encodeURIComponent(language);
    }

    function revisionLanguageValidationError(input) {
        if (!input) {
            return null;
        }

        const value = (input.value || '').trim();
        if (!value) {
            return null;
        }

        if (value.includes(',')) {
            return typeof Omeka !== 'undefined' && Omeka.jsTranslate
                ? Omeka.jsTranslate('Enter a single language tag only. Multiple tags separated by commas are not allowed.')
                : 'Enter a single language tag only. Multiple tags separated by commas are not allowed.';
        }

        return null;
    }

    function updateRevisionLanguageFeedback(button, result) {
        const container = button.closest('.contribute-enhancements-revision-language');
        if (!container || !result) {
            return;
        }

        const status = container.querySelector('.contribute-enhancements-revision-language__status');
        const input = container.querySelector('.contribute-enhancements-revision-language__input');
        const dd = container.closest('dd.value');

        if (status && result.message) {
            status.textContent = result.message;
            status.classList.toggle('is-set', !!result.language);
            status.classList.toggle('is-empty', !result.language);
            status.classList.add('is-updated');
        }

        if (input) {
            input.value = result.language || '';
        }

        if (dd) {
            let langSpan = dd.querySelector('span.language');
            if (result.language) {
                dd.setAttribute('lang', result.language);
                if (!langSpan) {
                    langSpan = document.createElement('span');
                    langSpan.className = 'language';
                    dd.insertBefore(langSpan, dd.firstChild);
                }
                langSpan.textContent = result.language;
            } else if (langSpan) {
                langSpan.remove();
                dd.removeAttribute('lang');
            }
        }

        container.classList.add('contribute-enhancements-revision-language--confirmed');
        window.setTimeout(function () {
            container.classList.remove('contribute-enhancements-revision-language--confirmed');
            if (status) {
                status.classList.remove('is-updated');
            }
        }, 2500);
    }

    function bindRevisionLanguageSave(button, url) {
        const input = revisionLanguageInput(button);
        const validationError = revisionLanguageValidationError(input);
        if (validationError) {
            if (typeof CommonDialog !== 'undefined') {
                CommonDialog.jSendFail({ message: validationError });
            }
            return;
        }

        lockButton(button);

        fetchJson(url)
            .then(function (data) {
                if (!data.status || data.status !== 'success') {
                    if (typeof CommonDialog !== 'undefined') {
                        CommonDialog.jSendFail(data);
                    }
                    releaseButton(button);
                    return;
                }

                const result = data.data ? data.data.contribution : null;
                updateRevisionLanguageFeedback(button, result);
                releaseButton(button);
            })
            .catch(function (error) {
                releaseButton(button);
                if (typeof CommonDialog !== 'undefined') {
                    CommonDialog.jSendFail(error);
                }
            });
    }

    function languageWarningsUrl(contributionElement) {
        if (!contributionElement) {
            return null;
        }

        return contributionElement.dataset.languageWarningsUrl || null;
    }

    function formatLanguageWarningMessage(warnings) {
        if (!warnings || !warnings.length) {
            return '';
        }

        const lines = [
            'Some accepted metadata values do not have a language tag. Values without tags may not appear correctly when visitors filter the site by language.',
            '',
            'Values without language tags:',
        ];

        warnings.forEach(function (warning) {
            lines.push('- ' + warning.label + ': ' + warning.value);
        });

        lines.push('');
        lines.push('Language tags are optional. You can validate now, or add tags in the contribution values below first.');

        return lines.join('\n');
    }

    function promptLanguageWarnings(contributionElement) {
        const url = languageWarningsUrl(contributionElement);
        if (!url || typeof CommonDialog === 'undefined') {
            return Promise.resolve(true);
        }

        return fetchJsonGet(url)
            .then(function (data) {
                const warnings = data && data.data ? data.data.warnings : [];
                if (!data.status || data.status !== 'success' || !warnings.length) {
                    return true;
                }

                return CommonDialog.dialogConfirm({
                    message: formatLanguageWarningMessage(warnings),
                    nl2br: true,
                    textOk: typeof Omeka !== 'undefined' && Omeka.jsTranslate
                        ? Omeka.jsTranslate('Continue')
                        : 'Continue',
                    textCancel: typeof Omeka !== 'undefined' && Omeka.jsTranslate
                        ? Omeka.jsTranslate('Cancel')
                        : 'Cancel',
                });
            })
            .catch(function () {
                return true;
            });
    }

    function reloadWithNotice(status) {
        const url = new URL(window.location.href);
        url.searchParams.set('contribution_action', status);
        url.hash = 'contribution';
        window.location.href = url.toString();
    }

    function handleButtonResponse(button, data, reloadStatus) {
        if (!data.status || data.status !== 'success') {
            if (typeof CommonDialog !== 'undefined') {
                CommonDialog.jSendFail(data);
            }
            releaseButton(button);
            return;
        }

        if (reloadStatus) {
            reloadWithNotice(reloadStatus);
            return;
        }

        const contribution = data.data ? data.data.contribution : null;
        if (contribution && contribution.message && typeof CommonDialog !== 'undefined') {
            CommonDialog.dialogAlert({
                message: contribution.message,
            }).then(function () {
                window.location.reload();
            });
            return;
        }

        releaseButton(button);
    }

    function bindContributionValueAction(button, url, reloadStatus) {
        lockButton(button);

        fetchJson(url)
            .then(data => handleButtonResponse(button, data, reloadStatus))
            .catch(error => {
                releaseButton(button);
                if (typeof CommonDialog !== 'undefined') {
                    CommonDialog.jSendFail(error);
                }
            });
    }

    function bindArchiveToggle(button) {
        lockButton(button);

        fetchJson(button.dataset.url)
            .then(data => {
                if (!data.status || data.status !== 'success') {
                    if (typeof CommonDialog !== 'undefined') {
                        CommonDialog.jSendFail(data);
                    }
                    releaseButton(button);
                    return;
                }

                window.location.reload();
            })
            .catch(error => {
                releaseButton(button);
                if (typeof CommonDialog !== 'undefined') {
                    CommonDialog.jSendFail(error);
                }
            });
    }

    function enhanceBrowseRows() {
        document.querySelectorAll('tr.contribution[data-id]').forEach(function (row) {
            if (row.dataset.contributeEnhancementsArchiveReady) {
                return;
            }

            row.dataset.contributeEnhancementsArchiveReady = '1';
            const contributionId = row.dataset.id;
            const actions = row.querySelector('ul.actions');
            if (!actions || !contributionId) {
                return;
            }

            const archiveUrl = '/admin/contribute-enhancements/contribution/' + contributionId + '/archive';
            const unarchiveUrl = '/admin/contribute-enhancements/contribution/' + contributionId + '/unarchive';
            const isArchivedView = new URL(window.location.href).searchParams.get('contribute_enhancements_archived') === '1';

            if (!actions.querySelector('.contribute-enhancements-archive-toggle')) {
                const li = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'single-action contribute-enhancements-archive-toggle';
                button.dataset.url = isArchivedView ? unarchiveUrl : archiveUrl;
                button.dataset.archived = isArchivedView ? '1' : '0';
                button.dataset.spinner = 'true';
                const label = isArchivedView ? 'Unarchive contribution' : 'Archive contribution';
                button.title = label;
                button.setAttribute('aria-label', label);
                const icon = document.createElement('span');
                icon.className = 'fas contribute-enhancements-icon-archive';
                icon.setAttribute('aria-hidden', 'true');
                button.appendChild(icon);
                li.appendChild(button);
                actions.insertBefore(li, actions.firstChild);
            }

            if (isArchivedView) {
                row.classList.add('contribute-enhancements-archived');
                actions.querySelectorAll('.undertaking-toggle, .status-toggle, .validate, .send-message, .contribute-enhancements-status-select').forEach(function (el) {
                    const parent = el.closest('li');
                    if (parent) {
                        parent.remove();
                    }
                });
            }
        });
    }

    function contributionIdFromContext(element) {
        const row = element ? element.closest('tr.contribution, .contribution[data-contribution-id]') : null;
        if (!row) {
            return null;
        }

        return row.dataset.id || row.dataset.contributionId || null;
    }

    function currentContributionIdFromDialog(dialog) {
        const form = dialog ? dialog.querySelector('form') : null;
        const action = form ? form.getAttribute('action') || '' : '';
        const match = action.match(/\/contribution\/(\d+)\/send-message/);
        return match ? match[1] : null;
    }

    function resetSpinners() {
        document.querySelectorAll('[data-spinner="true"]').forEach(releaseButton);
    }

    function loadEmailTemplate(dialog, type) {
        const contributionId = currentContributionIdFromDialog(dialog);
        if (!contributionId || !type) {
            return Promise.resolve();
        }

        const subjectField = dialog.querySelector('#subject');
        const bodyField = dialog.querySelector('#body');
        const url = '/admin/contribute-enhancements/contribution/' + contributionId + '/email-template?type=' + encodeURIComponent(type);

        return fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        })
            .then(async response => {
                const contentType = response.headers.get('content-type') || '';
                if (!response.ok || !contentType.includes('application/json')) {
                    return null;
                }

                return response.json();
            })
            .then(data => {
                if (!data || !data.status || data.status !== 'success' || !data.data || !data.data.template) {
                    return;
                }

                if (subjectField) {
                    subjectField.value = data.data.template.subject || '';
                }
                if (bodyField) {
                    bodyField.value = data.data.template.body || '';
                }
            })
            .catch(function () {
                // Keep custom message on failure.
            });
    }

    function openSendMessageDialog(sendUrl, templateType) {
        const dialog = document.querySelector('dialog.dialog-send-message.dialog-contribute');
        if (!dialog) {
            return;
        }

        const form = dialog.querySelector('form');
        if (form && sendUrl) {
            form.setAttribute('action', sendUrl);
        }

        const select = dialog.querySelector('.contribute-enhancements-email-template');
        if (select) {
            select.value = templateType || '';
        }

        dialog.showModal();
        dialog.dispatchEvent(new Event('o:dialog-opened'));

        if (templateType) {
            loadEmailTemplate(dialog, templateType);
        }
    }

    function bindEmailTemplateSelector() {
        document.addEventListener('change', function (event) {
            const select = event.target.closest('.contribute-enhancements-email-template');
            if (!select) {
                return;
            }

            const dialog = select.closest('dialog.dialog-send-message');
            loadEmailTemplate(dialog, select.value);
        });
    }

    function promptSendEmailAfterStatusChange(contextElement, newStatus) {
        const contributionRow = contextElement
            ? contextElement.closest('tr.contribution, .contribution[data-contribution-id]')
            : null;
        const sendButton = contributionRow ? contributionRow.querySelector('.send-message') : null;
        const contributionId = contributionIdFromContext(contextElement);

        const templateMap = {
            validated: 'accepted',
            'not-validated': 'rejected',
            undetermined: 'needs-changes',
        };
        const templateType = templateMap[newStatus] || null;

        if (!templateType || typeof CommonDialog === 'undefined') {
            return Promise.resolve();
        }

        const reminderMessages = {
            validated: 'You marked this contribution as Validated. Contributors are not notified automatically when the status changes.\n\nThis is a reminder that you may want to email the contributor about your decision.',
            'not-validated': 'You marked this contribution as Rejected. Contributors are not notified automatically when the status changes.\n\nThis is a reminder that you may want to email the contributor about your decision.',
            undetermined: 'You set this contribution to Pending review. Contributors are not notified automatically when the status changes.\n\nThis is a reminder that you may want to email the contributor about your decision.',
        };

        return CommonDialog.dialogConfirm({
            message: reminderMessages[newStatus] || reminderMessages.undetermined,
            nl2br: true,
            textOk: typeof Omeka !== 'undefined' && Omeka.jsTranslate
                ? Omeka.jsTranslate('Send email')
                : 'Send email',
            textCancel: typeof Omeka !== 'undefined' && Omeka.jsTranslate
                ? Omeka.jsTranslate('Ignore')
                : 'Ignore',
        }).then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            const sendUrl = sendButton
                ? sendButton.dataset.url
                : (contributionId ? '/admin/contribution/' + contributionId + '/send-message' : null);

            if (!sendUrl) {
                return;
            }

            const sendDialog = document.querySelector('dialog.dialog-send-message.dialog-contribute');
            openSendMessageDialog(sendUrl, templateType);

            if (!sendDialog) {
                return;
            }

            return new Promise(function (resolve) {
                sendDialog.addEventListener('close', function onClose() {
                    sendDialog.removeEventListener('close', onClose);
                    resolve();
                });
            });
        });
    }

    function promptSendEmailAfterStatusToggle(button, newStatus) {
        return promptSendEmailAfterStatusChange(button, newStatus);
    }

    function bindStatusTogglePrompt() {
        document.addEventListener('o:jsend-success', function (event) {
            const detail = event.detail || {};
            const data = detail.data || {};
            let button = detail.context && detail.context.target ? detail.context.target : null;
            if (button && button.closest) {
                button = button.closest('.status-toggle, .contribute-enhancements-status-select');
            }

            if (!button || !button.matches('.status-toggle') || !data.data || !data.data.contribution) {
                return;
            }

            promptSendEmailAfterStatusToggle(button, data.data.contribution.status);
        });
    }

    document.addEventListener('click', function (event) {
        const restoreButton = event.target.closest('.contribution .restore-value');
        const approveButton = event.target.closest('.contribution .approve-removal');
        const reproposeButton = event.target.closest('.contribution .repropose-removal');
        const acceptRevisionButton = event.target.closest('.contribution .accept-revision');
        const rejectRevisionButton = event.target.closest('.contribution .reject-revision');
        const reviewRevisionButton = event.target.closest('.contribution .review-revision');
        const setRevisionLanguageButton = event.target.closest('.contribution .set-revision-language');
        const archiveButton = event.target.closest('.contribute-enhancements-archive-toggle');
        const button = restoreButton || approveButton || reproposeButton || acceptRevisionButton || rejectRevisionButton || reviewRevisionButton || setRevisionLanguageButton || archiveButton;

        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (archiveButton) {
            bindArchiveToggle(button);
            return;
        }

        let url = null;
        let reloadStatus = null;

        if (restoreButton) {
            url = restoreButton.dataset.restoreValueUrl;
            reloadStatus = 'removal-restored';
        } else if (approveButton) {
            url = approveButton.dataset.approveRemovalUrl;
            reloadStatus = 'approved';
        } else if (reproposeButton) {
            url = reproposeButton.dataset.reproposeRemovalUrl;
            reloadStatus = 'removal-review';
        } else if (acceptRevisionButton) {
            const validationError = revisionLanguageValidationError(revisionLanguageInput(acceptRevisionButton));
            if (validationError) {
                if (typeof CommonDialog !== 'undefined') {
                    CommonDialog.jSendFail({ message: validationError });
                }
                return;
            }
            url = appendLanguageQuery(acceptRevisionButton.dataset.acceptRevisionUrl, acceptRevisionButton);
            reloadStatus = 'revision-accepted';
        } else if (rejectRevisionButton) {
            url = rejectRevisionButton.dataset.rejectRevisionUrl;
            reloadStatus = 'revision-rejected';
        } else if (reviewRevisionButton) {
            url = reviewRevisionButton.dataset.reviewRevisionUrl;
            reloadStatus = 'revision-review';
        } else if (setRevisionLanguageButton) {
            bindRevisionLanguageSave(setRevisionLanguageButton, appendLanguageQuery(
                setRevisionLanguageButton.dataset.setRevisionLanguageUrl,
                setRevisionLanguageButton
            ));
            return;
        }

        if (!url) {
            return;
        }

        bindContributionValueAction(button, url, reloadStatus);
    }, true);

    function bindStatusSelect() {
        document.addEventListener('change', function (event) {
            const select = event.target.closest('.contribute-enhancements-status-select');
            if (!select || !select.dataset.url) {
                return;
            }

            const url = select.dataset.url + '?status=' + encodeURIComponent(select.value);
            const previous = select.dataset.currentStatus || select.value;
            const contributionRow = select.closest('.contribution[data-contribution-id]');

            select.disabled = true;

            const proceed = function () {
                return fetchJson(url)
                    .then(function (data) {
                        if (!data.status || data.status !== 'success') {
                            if (typeof CommonDialog !== 'undefined') {
                                CommonDialog.jSendFail(data);
                            }
                            select.value = previous;
                            select.disabled = false;
                            return Promise.reject();
                        }

                        const contribution = data.data ? data.data.contribution : null;
                        if (contribution && contribution.status) {
                            select.dataset.currentStatus = contribution.status;
                        }

                        return promptSendEmailAfterStatusChange(select, contribution ? contribution.status : select.value);
                    })
                    .then(function () {
                        window.location.reload();
                    });
            };

            const run = select.value === 'validated'
                ? promptLanguageWarnings(contributionRow).then(function (confirmed) {
                    if (!confirmed) {
                        select.value = previous;
                        select.disabled = false;
                        return Promise.reject();
                    }

                    return proceed();
                })
                : proceed();

            run.catch(function (error) {
                if (!error) {
                    return;
                }
                select.value = previous;
                select.disabled = false;
                if (typeof CommonDialog !== 'undefined') {
                    CommonDialog.jSendFail(error);
                }
            });
        });
    }

    function bindValidateButtonPrompt() {
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.contribution .validate');
            if (!button || !button.dataset.url) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            const contributionRow = button.closest('.contribution[data-contribution-id]');
            promptLanguageWarnings(contributionRow).then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                lockButton(button);
                fetchJson(button.dataset.url)
                    .then(function (data) {
                        if (!data.status || data.status !== 'success') {
                            if (typeof CommonDialog !== 'undefined') {
                                CommonDialog.jSendFail(data);
                            }
                            releaseButton(button);
                            return;
                        }

                        window.location.reload();
                    })
                    .catch(function (error) {
                        releaseButton(button);
                        if (typeof CommonDialog !== 'undefined') {
                            CommonDialog.jSendFail(error);
                        }
                    });
            });
        }, true);
    }

    document.addEventListener('DOMContentLoaded', function () {
        resetSpinners();
        enhanceBrowseRows();
        bindEmailTemplateSelector();
        bindStatusTogglePrompt();
        bindStatusSelect();
        bindValidateButtonPrompt();
    });

    window.addEventListener('pageshow', resetSpinners);
})();
