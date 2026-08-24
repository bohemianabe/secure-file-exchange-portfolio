import '../styles/app.css';
import '@fortawesome/fontawesome-free/css/fontawesome.min.css';
import * as bootstrap from 'bootstrap';
import 'datatables.net-bs5';
import $ from 'jquery';
import { Notyf } from 'notyf';
import 'select2/dist/css/select2.css';
import 'select2';

import { unlockNewMessageSound, initModalSelect2, initializeAccessLogTable } from './helper.js';
import { initFirmUploadModal } from './firm/file-upload-logic.js';
import { initNewClientLogicModal } from './firm/add-new-client-logic.js';
import { initFolderModal } from './firm/folder-modal-logic.js';
import { initClientUploadModal } from './client/file-upload-logic.js';
import { initUploadPdfModal } from './admin/pdf-upload-modal.js';
import { initUploadEditPdfModal } from './admin/pdf-edit-modal.js';
import { initStoragePlanModal } from './admin/storage-plan-modal.js';

const modalInitializers = {
    'firm-upload-files': initFirmUploadModal,
    'client-upload-files-modal': initClientUploadModal,
    'add-new-client-modal': initNewClientLogicModal,
    'edit-folder-modal': initFolderModal,
    'modal-upload-pdf-file': initUploadPdfModal,
    'modal-upload-pdf-file-edit': initUploadEditPdfModal,
    'modal-storage-plan': initStoragePlanModal,
};

function removeModalBodyAndFooter(modalEl) {
    modalEl.querySelectorAll('.modal-body, .modal-footer').forEach((el) => {
        // console.log(el);
        el.remove();
    });
    const alertMsg = modalEl.querySelector('.d-none[data-modal-alert]');
    alertMsg.innerHTML = '';
    alertMsg.classList.add('d-none');
}

function getModalErrorMessage(xhr) {
    // Symfony may return a JSON error response.
    if (xhr.responseJSON?.message) {
        return xhr.responseJSON.message;
    }

    switch (xhr.status) {
        case 400:
            return 'The request (400) was invalid.';
        case 401:
            return 'Your session has expired (401). Please sign in again.';
        case 403:
            return 'You do not have permission (403) to perform this action.';
        case 404:
            return 'The requested (404) content could not be found.';
        case 422:
            return 'The request (422) could not be processed.';
        case 500:
            return 'A server error (500) occurred. Please contact an administrator.';
        default:
            return 'The content could not be loaded. Please try again.';
    }
}

function messageHtmlBuild(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
}

function runModalInitializer(modalEl) {
    const modalType = modalEl.querySelector('.modal-body')?.dataset.modalType;

    if (modalType && modalInitializers[modalType]) {
        modalInitializers[modalType](modalEl);
    }
}

const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim();

$(document).ready(function () {
    $('.select2').select2({
        width: '100%',
        height: '2.25rem',
        placeholder: 'Select an option',
        allowClear: true,
    });
});

const notyf = new Notyf({
    duration: 4000,
    position: {
        x: 'right',
        y: 'top',
    },
    types: [
        {
            type: 'warning',
            background: 'orange',
            icon: {
                className: 'material-icons',
                tagName: 'i',
                text: 'warning',
            },
        },
        {
            type: 'notification_popup',
            background: primaryColor,
            icon: {
                // className: 'fas fa-comment-dots text-white',
                // tagName: 'i',
                // text: 'warning',
            },
            dismissible: true,
            duration: 0,
        },
        {
            type: 'error',
            background: 'indianred',
            icon: {
                className: 'fas fa-exclamation-circle text-white',
                tagName: 'i',
                // text: 'warning',
            },
            duration: 0,
            dismissible: true,
        },
    ],
});

// ag: attached notyf to the window object so I can access it globally.
window.notyf = notyf;

$(document).on('click', '[data-dynamic-load]', function (e) {
    e.preventDefault();

    const btn = $(e.currentTarget);
    let modalSelector = $(btn.data('bs-target'));
    let modalEl = document.getElementById('defaultTemplateModal');

    if (!modalEl) {
        console.error(`Modal element ${modalSelector} was not found.`);
        return;
    }

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const targetUrl = btn.attr('href');
    const modalTitle = btn.attr('data-modal-title') ?? '';
    const modalSize = btn.attr('data-modal-size') ?? 'modal-lg';

    const modalDialog = modalEl.querySelector('.modal-dialog');
    const modalContent = modalEl.querySelector('.modal-content');
    const modalTitleEl = modalEl.querySelector('.modal-title');

    if (!targetUrl) {
        console.error('No AJAX URL was provided.');
        return;
    }

    if (!modalDialog || !modalContent) {
        console.error('The modal is missing its dialog or content element.');
        return;
    }

    // Scope the title change to this modal only.
    if (modalTitleEl) {
        modalTitleEl.textContent = modalTitle;
    }

    // Clear old size classes.
    modalDialog.classList.remove('modal-sm', 'modal-lg', 'modal-xl', 'modal-fullscreen', 'modal-fullscreen-lg-down');

    if (modalSize === 'modal-xl') {
        modalDialog.classList.add('modal-fullscreen-lg-down');
    }

    modalDialog.classList.add(modalSize);
    // Remove content left behind by a previous request.
    removeModalBodyAndFooter(modalEl);

    // Show loading state.
    modalContent.insertAdjacentHTML(
        'beforeend',
        `
            <div class="modal-body">
                <div class="d-flex justify-content-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        `
    );
    bsModal.show();
    $.ajax({
        url: targetUrl,
        type: 'GET',
        dataType: 'html',
        success: function (data) {
            removeModalBodyAndFooter(modalEl);
            modalContent.insertAdjacentHTML('beforeend', data);

            initModalSelect2(modalEl);
            runModalInitializer(modalEl);
        },

        error: function (xhr, textStatus, errorThrown) {
            console.error('Modal AJAX request failed:', {
                status: xhr.status,
                textStatus,
                errorThrown,
                response: xhr.responseText,
            });

            removeModalBodyAndFooter(modalEl);

            const message = getModalErrorMessage(xhr);
            modalContent.insertAdjacentHTML(
                'beforeend',
                `
                    <div class="modal-body">
                        <div class="alert alert-danger mb-0 text-center" role="alert">
                            ${messageHtmlBuild(message)}
                        </div>
                    </div>
                `
            );
            // It may already be open because of the loading state, but calling show again is safe.
            bsModal.show();
        },
    });
});

// ag: modal for the OFFCANVAS END
$(document).on('click', '[data-dynamic-offcanvas-rightload]', function (e) {
    e.preventDefault();
    const btn = $(e.currentTarget);
    const offcanvasSelector = btn.data('bs-target');
    const offcanvasEl = document.querySelector(offcanvasSelector);
    const targetUrl = btn.attr('href');
    const offcanvasTitle = btn.attr('data-modal-title');

    if (!offcanvasEl) {
        console.error(`Offcanvas element ${offcanvasSelector} was not found.`);
        return;
    }
    const titleEl = offcanvasEl.querySelector('.offcanvas-title');
    const contentEl = offcanvasEl.querySelector('.offcanvas-dynamic-content');
    if (titleEl) {
        titleEl.textContent = offcanvasTitle ?? 'File Access Log';
    }

    if (contentEl) {
        contentEl.innerHTML = `
            <div class="d-flex justify-content-center py-5">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
    }

    const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
    $.ajax({
        url: targetUrl,
        type: 'GET',
        success: function (data) {
            if (contentEl) {
                contentEl.innerHTML = data;
                initializeAccessLogTable(contentEl);
            }
            bsOffcanvas.show();
        },
        error: function (xhr, textStatus, errorThrown) {
            console.error('Modal AJAX request failed:', {
                status: xhr.status,
                textStatus,
                errorThrown,
                response: xhr.responseText,
            });
            removeModalBodyAndFooter(offcanvasEl);
            const message = getModalErrorMessage(xhr);
            contentEl.insertAdjacentHTML(
                'beforeend',
                `
                    <div class="modal-body">
                        <div class="alert alert-danger mb-0 text-center" role="alert">
                            ${messageHtmlBuild(message)}
                        </div>
                    </div>
                `
            );
            // It may already be open because of the loading state, but calling show again is safe.
            bsOffcanvas.show();
        },
    });
});

document.getElementById('offcanvasRight')?.addEventListener('shown.bs.offcanvas', function () {
    const table = this.querySelector('[data-access-log-table]');
    if (table && $.fn.DataTable.isDataTable(table)) {
        $(table).DataTable().columns.adjust();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    let modalEl = document.querySelector('[data-auto-show="true"]');
    if (!modalEl) {
        return;
    }
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
});

window.checkStateInput = function (el) {
    const wrapper = el.closest('.js-region-state-wrapper');
    if (!wrapper) return;

    const regionNameDiv = wrapper.querySelector('.js-region-name-div');
    const regionNameInput = wrapper.querySelector('[name$="[regionName]"]');
    const stateDiv = wrapper.querySelector('.js-state-div');
    const stateInput = wrapper.querySelector('[name$="[state]"]');

    if (!regionNameDiv || !regionNameInput || !stateDiv || !stateInput) {
        return;
    }

    const countryName = el.options[el.selectedIndex]?.text ?? '';
    const usesState = ['United States', 'Canada', 'Mexico'].includes(countryName);

    if (usesState) {
        regionNameDiv.classList.add('d-none');
        regionNameInput.value = '';
        regionNameInput.required = false;

        stateDiv.classList.remove('d-none');
        stateInput.required = true;
    } else {
        stateDiv.classList.add('d-none');
        stateInput.required = false;
        stateInput.value = '';

        regionNameDiv.classList.remove('d-none');
        regionNameInput.required = true;
    }
};

document.addEventListener('DOMContentLoaded', function () {
    const banner = document.querySelector('[data-announcement-id]');
    const closeBtn = document.querySelector('.announcement-dismiss-btn');

    if (!banner || !closeBtn) {
        return;
    }

    closeBtn.addEventListener('click', async function () {
        const dismissUrl = banner.dataset.dismissUrl;

        try {
            await fetch(dismissUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
        } catch (e) {
            console.error('Failed to dismiss announcement.', e);
        }

        banner.remove();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    let header = null;

    if (document.querySelector('.sidebar-nav') !== null) {
        header = document.querySelector('.sidebar-nav');
    } else {
        header = document.querySelector('header.navbar');
    }

    const themeLightBtns = document.querySelectorAll('.theme-light');
    const themeDarkBtns = document.querySelectorAll('.theme-dark');

    const savedMode = localStorage.getItem('dark-mode');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    function updateActiveButton(isDark) {
        themeLightBtns.forEach((btn) => {
            btn.classList.toggle('btn-primary', !isDark);
            btn.classList.toggle('text-white', !isDark);
        });

        themeDarkBtns.forEach((btn) => {
            btn.classList.toggle('btn-primary', isDark);
            btn.classList.toggle('text-white', isDark);
        });
    }

    function applyDarkMode(enabled) {
        document.body.classList.toggle('dark-mode', enabled);
        const adminToggler = document.getElementById('admin-navbar-toggler');
        if (adminToggler) {
            adminToggler.classList.toggle('navbar-dark', enabled);
        }
    }

    function saveDarkMode(enabled) {
        localStorage.setItem('dark-mode', enabled ? 'enabled' : 'disabled');
    }

    const enableDarkMode = savedMode === 'enabled' || (savedMode === null && prefersDark);

    applyDarkMode(enableDarkMode);
    updateActiveButton(enableDarkMode);

    themeDarkBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            applyDarkMode(true);
            saveDarkMode(true);
            updateActiveButton(true);
        });
    });

    themeLightBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            applyDarkMode(false);
            saveDarkMode(false);
            updateActiveButton(false);
        });
    });
});

document.addEventListener('click', unlockNewMessageSound, { once: true });
document.addEventListener('keydown', unlockNewMessageSound, { once: true });

hugerte.init({
    selector: '.hugerte',
    height: 350,
});
