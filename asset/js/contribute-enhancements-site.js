'use strict';

/**
 * Guest contribution browse/show interactions (delete only).
 * Requires common-dialog.js and Omeka global jsTranslate.
 */
$(document).ready(function () {
    function contributionDelete(button, id, urlDelete, urlRedirect) {
        if (typeof CommonDialog !== 'undefined') {
            CommonDialog.spinnerEnable(button);
        }

        const formData = new URLSearchParams({
            id: id,
            confirmform_csrf: typeof confirmFormCsrf !== 'undefined' ? confirmFormCsrf : '',
        });

        fetch(urlDelete, {
            method: 'POST',
            body: formData.toString(),
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.status || data.status !== 'success') {
                    if (typeof CommonDialog !== 'undefined') {
                        CommonDialog.jSendFail(data);
                    } else {
                        window.alert(typeof Omeka !== 'undefined' && Omeka.jsTranslate
                            ? Omeka.jsTranslate('Something went wrong')
                            : 'Something went wrong');
                    }
                    return;
                }
                window.location.href = urlRedirect;
            })
            .catch(function (error) {
                if (typeof CommonDialog !== 'undefined') {
                    CommonDialog.jSendFail(error);
                } else {
                    window.alert(String(error));
                }
            })
            .finally(function () {
                if (typeof CommonDialog !== 'undefined') {
                    CommonDialog.spinnerDisable(button);
                }
            });
    }

    $('.remove-contribution').on('click', async function (ev) {
        ev.stopPropagation();
        ev.preventDefault();

        const button = this;
        const id = $(this).data('contribution-id');
        const urlDelete = $(this).data('contribution-url');
        const urlRedirect = $(this).data('redirect-url')
            ? $(this).data('redirect-url')
            : urlDelete.slice(0, urlDelete.lastIndexOf('/')).slice(0, urlDelete.lastIndexOf('/'));
        const message = $(this).closest('.actions, .contribute-enhancements-guest-card__footer').data('message-remove-contribution')
            || (typeof Omeka !== 'undefined' && Omeka.jsTranslate
                ? Omeka.jsTranslate('Are you sure to remove this contribution?')
                : 'Are you sure to remove this contribution?');

        if (!urlDelete) {
            return false;
        }

        let confirmed = false;
        if (typeof CommonDialog !== 'undefined' && typeof CommonDialog.dialogConfirm === 'function') {
            confirmed = await CommonDialog.dialogConfirm({
                heading: typeof Omeka !== 'undefined' && Omeka.jsTranslate
                    ? Omeka.jsTranslate('Contribution')
                    : 'Contribution',
                message: message,
            });
        } else {
            confirmed = window.confirm(message);
        }

        if (confirmed) {
            contributionDelete(button, id, urlDelete, urlRedirect);
        }

        return false;
    });
});
