import { toggleButtonLoading } from '../helper.js';

// ag: logic for the file upload modal
export function initFirmUploadModal(modalEl) {
    var dropZone = document.getElementById('drop-zone');
    var dropZoneInner = document.getElementById('drop-zone-inner');
    var fileNameDiv = document.getElementById('file-name-div');
    var fileInput = document.getElementById('send_files_form_files');

    if (!dropZone || !dropZoneInner || !fileNameDiv || !fileInput) {
        return;
    }

    var defaultCheckbox = document.getElementById('default-email-notification');
    var writtenMessageCheckbox = document.getElementById('written-email-notification');
    var customMessageCheckbox = document.getElementById('custom-message-email-notification');

    var writtenEmailMessageTextarea = document.getElementById('written-email-message-textarea');
    var customMessageSelect = document.getElementById('custom-message-dropdown-div');

    // Ensure only one email option can be active at a time
    defaultCheckbox.addEventListener('change', () => {
        if (defaultCheckbox.checked) {
            writtenMessageCheckbox.checked = false;
            writtenEmailMessageTextarea.classList.add('d-none');
            writtenEmailMessageTextarea.removeAttribute('required');
            writtenEmailMessageTextarea.value = '';

            customMessageCheckbox.checked = false;
            customMessageSelect.classList.add('d-none');
            customMessageSelect.removeAttribute('required');
            customMessageSelect.value = '';
        }
    });

    // ag: if written checkbox is clicked hide the custom message dropdown/checkbox and unclick defaultcheckbox #}
    writtenMessageCheckbox.addEventListener('change', () => {
        if (writtenMessageCheckbox.checked) {
            defaultCheckbox.checked = false;
            customMessageCheckbox.checked = false;

            writtenEmailMessageTextarea.classList.remove('d-none');
            writtenEmailMessageTextarea.required = true;
            // ag: hide custom message select dropdown
            customMessageSelect.classList.add('d-none');
            customMessageSelect.removeAttribute('required');
            customMessageSelect.value = '';
        } else {
            writtenEmailMessageTextarea.classList.add('d-none');
            writtenEmailMessageTextarea.required = false;
            writtenEmailMessageTextarea.value = '';
        }
    });

    // ag: if custom message checkbox is clicked hide the written text area/checkbox and unclick defaultcheckbox #}
    customMessageCheckbox.addEventListener('change', () => {
        if (defaultCheckbox.checked) {
            defaultCheckbox.checked = false;
        }

        if (writtenMessageCheckbox.checked) {
            writtenMessageCheckbox.checked = false;

            writtenEmailMessageTextarea.classList.add('d-none');
            writtenEmailMessageTextarea.required = false;
        }

        if (customMessageCheckbox.checked) {
            customMessageSelect.classList.remove('d-none');
            customMessageSelect.required = false;
        } else {
            customMessageSelect.classList.add('d-none');
            customMessageSelect.required = true;
        }
    });

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = Array.from(e.dataTransfer.files);
        fileInput.files = e.dataTransfer.files;
        showFileList(files);
    });

    var MAX_FILES = 10;
    fileInput.addEventListener('change', () => {
        const files = Array.from(fileInput.files);
        if (files.length > MAX_FILES) {
            alert(`You can only upload a maximum of ${MAX_FILES} files.`);
            this.value = '';
        } else {
            showFileList(files);
        }
    });

    function removeFile(index) {
        document.getElementById('file-name-inner-div-' + index).remove();
        // 2. Build a new DataTransfer object
        const dt = new DataTransfer();

        // 3. Copy all files except the one we're removing
        Array.from(fileInput.files).forEach((file, i) => {
            if (i !== index) dt.items.add(file);
        });

        // 4. Replace the old FileList with the new one
        fileInput.files = dt.files;

        // 5. Optional: re-render your file list if needed
        showFileList(Array.from(fileInput.files), false);
    }

    function showFileList(files, repop = true) {
        const fileNames = files.map((f) => `<li class="me-2">${f.name}</li>`).join('');
        dropZoneInner.innerHTML = `
                                <p><strong>${files.length}</strong> file(s) selected:</p>
                                <ul class="mb-0 d-flex justify-content-evenly flex-wrap">${fileNames}</ul>
                            `;
        const filePlaceholderNameDiv = files
            .map(
                (file, index) => `
                                <div class="mb-3 col-12 d-flex justify-content-between" id="file-name-inner-div-${index}">
                                    <div class="col-6">
                                        <input type="text" class="form-control" name="file_name_${index}" placeholder="${file.name}" maxlength='100'/>
                                    </div>
                                    <button class="btn btn-danger text-white" onClick="removeFile(${index})">Remove</button>
                                </div>
                            `
            )
            .join('');

        if (repop) {
            fileNameDiv.innerHTML = `${filePlaceholderNameDiv}`;
        }
    }

    function clearFileInput(maxFileSize) {
        if (fileInput) {
            fileInput.value = ''; // Clear the selected file
        }
        dropZoneInner.innerHTML = `
                                <p class="mb-0">
                                <i class="fas fa-cloud-upload-alt me-2"></i></br>
                                <strong>Click to upload</strong> or drag and drop<br>
                                <small class="text-muted">Max file size: ${maxFileSize}</small>
                                </p>
                            `;
        fileNameDiv.innerHTML = ``;
    }

    let clearFileBtn = document.getElementById('clear-file-input');

    clearFileBtn.addEventListener('click', function () {
        const maxFileSize = $(this).data('max-file-size');

        clearFileInput(maxFileSize);
    });

    function validateFormFields(form) {
        let isValid = true;

        // Select all matching elements
        const fields = document.querySelectorAll(form + ' input, ' + form + ' select, ' + form + ' textarea');

        fields.forEach((field) => {
            const value = field.value.trim();
            const isRequired = field.hasAttribute('required');

            // Generic required field validation
            if (isRequired && value === '') {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        return isValid;
    }

    function updateProgress(percent) {
        const $pageLoader = $('#page-loader');
        const $progress = $('#upload-progress');
        const $bar = $('#upload-progress-bar');

        if (!$progress.length || !$bar.length) {
            return;
        }

        const nextPercent = Math.min(100, Math.max(currentProgress, Math.round(percent)));

        currentProgress = nextPercent;

        $pageLoader.removeClass('d-none');
        $progress.removeClass('d-none');

        $bar.css('width', `${nextPercent}%`).text(`${nextPercent}%`).attr('aria-valuenow', nextPercent);
    }

    // ag: custom testing of the progress bar. Set this to true to test feature.
    const DEBUG_UPLOAD_PROGRESS = false;
    let currentProgress = 0;

    function simulateProgress() {
        let percent = 0;
        const timer = setInterval(() => {
            if (percent < 80) {
                percent += Math.random() * 8;
            } else if (percent < 95) {
                percent += Math.random() * 2;
            }
            console.log(percent);

            updateProgress(Math.min(Math.round(percent), 95));
        }, 150);

        return timer;
    }

    function fileUploadSubmit(form, url) {
        var btnObj = document.getElementById('upload-files-btn');
        var formCheck = validateFormFields(form);
        if (formCheck) {
            // Show loading animation
            toggleButtonLoading(btnObj, true);

            // ag: test progress bar
            let fakeTimer = null;
            if (DEBUG_UPLOAD_PROGRESS) {
                fakeTimer = simulateProgress();
            }

            const formData = new FormData();
            const fields = document.querySelectorAll(form + ' input, ' + form + ' select, ' + form + ' textarea');

            fields.forEach((field, index) => {
                if (field.type === 'file') {
                    Array.from(field.files).forEach((file) => {
                        formData.append(field.name, file);
                    });
                } else if (field.type === 'checkbox') {
                    formData.append(field.name, field.checked ? 1 : 0);
                } else {
                    formData.append(field.name, field.value);
                }
            });

            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);

            // Optional: track progress
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const percent = Math.round((event.loaded / event.total) * 100);
                    updateProgress(percent);
                }
            };

            xhr.onload = () => {
                // ag: progress bar tester
                if (fakeTimer) {
                    clearInterval(fakeTimer);
                    updateProgress(100);
                }
                toggleButtonLoading(btnObj, false);
                if (xhr.status === 200) {
                    var dataResponse = JSON.parse(xhr.response);
                    if (dataResponse.success == false) {
                        window.notyf.error(dataResponse.message || 'Unable to upload file. Contact an administrator.');
                    } else {
                        window.notyf.success(dataResponse.message);
                        setTimeout(function () {
                            window.location.reload();
                        }, 4000);
                    }
                } else {
                    // window.notyf.error('Upload failed. Contact an administrator');
                    console.warn('Upload failed:', data);
                    window.notyf.error(data.message || 'Upload failed. Contact an administrator');
                }
            };

            xhr.onerror = () => {
                // ag: progress bar tester
                if (fakeTimer) {
                    clearInterval(fakeTimer);
                }

                toggleButtonLoading(btnObj, false);
                showErrorMessage('⚠️ Network error during upload.');
            };
            xhr.send(formData);
        } else {
            window.notyf.error('Please ensure all fields are filled out before you can submit.');
            return;
        }
    }

    const $modal = $(modalEl);

    $modal.off('click.upload', '#upload-files-btn');
    $modal.on('click.upload', '#upload-files-btn', function (e) {
        e.preventDefault();

        const form = $(this).data('form');
        const url = $(this).data('url');

        fileUploadSubmit(form, url);
    });
}
