import * as bootstrap from "bootstrap";
import {
	truncateText,
	handleAjaxReturnedData,
	validateFormFields,
	unlockNewMessageSound,
	playNewMessageSound,
	timeAgo,
	escapeHtml,
	startBrowserTabNotification,
	stopBrowserTabNotification,
	adjustMenuNotificationCount,
	toggleButtonLoading,
} from "../helper.js";

// ag: side menu dropdown carrot
const currentRoute = document.body.dataset.route;

$("#docu-sign-menu-parent-dropdown").on("click", function (e) {
	const dropdownCarrot = $("#docu-sign-dropdown-carrot");
	if (currentRoute !== "docu_sign_template_view" && currentRoute !== "docu_sign_request_view" && currentRoute !== "docu_sign_template_gallary_view") {
		if (dropdownCarrot.hasClass("fa-chevron-left")) {
			dropdownCarrot.removeClass("fa-chevron-left");
			dropdownCarrot.addClass("fa-chevron-down");
		} else {
			dropdownCarrot.addClass("fa-chevron-left");
			dropdownCarrot.removeClass("fa-chevron-down");
		}
	}
});

if (document.getElementById("firm-clear-client-csv-input")) {
	document.getElementById("firm-clear-client-csv-input").addEventListener("click", function () {
		const fileInput = document.getElementById("firm-client-csv-input");
		fileInput.value = ""; // reset the input
	});
}

// ag: dynamic firm active/deactive logic
const adminFirmActiveToggleModal = document.getElementById("firmToggleClientStatusModal");

if (adminFirmActiveToggleModal) {
	adminFirmActiveToggleModal.addEventListener("show.bs.modal", function (event) {
		const button = event.relatedTarget; // Button that triggered the modal
		const title = button.getAttribute("data-modal-title"); // Extract info from data-* attributes

		const modalTitle = adminFirmActiveToggleModal.querySelector(".modal-title");
		modalTitle.textContent = title || "Default Title";
	});
}

$("#firm-delete-client-submit").on("click", function (e) {
	e.preventDefault();
	let btn = $(this);

	// ag: set spinner on submit button
	btn.find(".btn-text").addClass("d-none");
	btn.find(".spinner-border").removeClass("d-none");
	btn.prop("disabled", true);

	const clientId = $("#client-id").val();
	const firmId = $("#firm-id").val();

	$.ajax({
		url: `/firm/ajax/delete/` + clientId + `/modal/submit`,
		method: "POST",
		data: {},
		// processData: false,
		// contentType: false,
		headers: { "X-Requested-With": "XMLHttpRequest" },
		success(data) {
			handleAjaxReturnedData(data, null, false);
		},
		complete: function () {
			setTimeout(function () {
				window.location.href = `/firm/client-view`;
			}, 2500);
		},
	});
});

// ag: attaching this to the window scope so it reaches the client-view page
window.removeAssignedFirmUser = function (firmUserId, clientId) {
	let btn = $(this);

	$.ajax({
		url: `/firm/ajax/remove-assigned-user/` + clientId,
		method: "POST",
		data: {
			assignedFirmUserId: firmUserId,
		},
		headers: { "X-Requested-With": "XMLHttpRequest" },
		success(data) {
			handleAjaxReturnedData(data, null, false);
			if (data.success == true) {
				// ag: remove the div upon completion
				$("#assign-firm-user-" + firmUserId + "").remove();
			}
		},
		complete: function () {},
	});
};

window.cancelDocuSignRequest = function (signatureRequestId) {
	let btn = $(this);

	document.getElementById("page-loader").classList.remove("d-none");
	$.ajax({
		url: `/firm/ajax/docu-sign/request/cancel/${signatureRequestId}`,
		// url: `/firm/ajax/docu-sign/request/cancel/` + signatureRequestId,
		method: "POST",
		headers: { "X-Requested-With": "XMLHttpRequest" },
		success(data) {
			handleAjaxReturnedData(data, null);
		},
		complete: function () {
			document.getElementById("page-loader").classList.add("d-none");
		},
	});
};

$("#docu-sign-request-select-client-checkbox").on("change", function (e) {
	if (e.currentTarget.checked) {
		$("#docu-sign-recipient-dropdown-div").removeClass("d-none");
		$("#docu-sign-request-recipients-select").val(null).prop("required", false).trigger("change");
		$("#docu-sign-request-signer-container").addClass("d-none");

		// ag: clear all manual docu signer name and email inputs.
		$(".docu-sign-request-name-input").each(function (index, element) {
			element.value = "";
			element.required = false;
		});
		$(".docu-sign-request-email-input").each(function (index, element) {
			element.value = "";
			element.required = false;
		});
	} else {
		$("#docu-sign-request-recipients-select").val(null).trigger("change");
		$("#docu-sign-request-recipients-select").val(null).prop("required", false).trigger("change");
		$("#docu-sign-request-signer-container").removeClass("d-none");
		$("#docu-sign-recipient-dropdown-div").addClass("d-none");
		// ag: clear all manual docu signer name and email inputs.
		$(".docu-sign-request-name-input").each(function (index, element) {
			element.required = true;
		});
		$(".docu-sign-request-email-input").each(function (index, element) {
			element.required = true;
		});
	}
});

$("#request-files-form-clear-btn").on("click", function (e) {
	$("#file_request_form_title").val("");
	$("#file_request_form_type").val("").trigger("change");
	$("#file_request_form_email").val("");
	$("#file_request_form_recipientName").val("");
	$("#custom-message-select").val("").trigger("change");
	$("#file_request_form_isPasscode").val("");
	$("#file_request_form_isPasscode").prop("checked", false);
	$("#file-request-message-textarea").val("");
});

$("#custom-message-checkbox").on("change", function (e) {
	if (e.currentTarget.checked) {
		$("#custom-message-select-div").removeClass("d-none");
		$("#file-request-message-div").addClass("d-none");
		$("#file-request-message-textarea").val("");
		$("#custom-message-select").prop("required", true);
	} else {
		$("#custom-message-select-div").addClass("d-none");
		$("#custom-message-select").val("").prop("required", false).trigger("change");
		$("#file-request-message-div").removeClass("d-none");
	}
});

$(document).on("click", ".notify-dismiss-btn", function (e) {
	const notificationId = $(this).data("notification-id");
	const cardWrapper = $(this).closest(".notification-card-wrapper");

	const cardBody = $(this).closest(".card");
	const badge = $("#notification-length");
	const badgeRedDot = $("#notification-red-dot");
	let count = parseInt(badge.text(), 10) || 0;

	const alertBellIcon = $("#notification-alert-bell");

	$.ajax({
		url: `/firm/ajax/edit/notification/${notificationId}`,
		method: "POST",
		data: {},
		headers: { "X-Requested-With": "XMLHttpRequest" },
		success(data) {
			if (data.success == true) {
				// ag: lower the notification number on the sidebar
				const el = $("#sidebar-nav-notification-num");
				adjustMenuNotificationCount(el, false);
			}
		},
		complete: function () {
			cardWrapper.animate({ opacity: 0, width: 0, paddingLeft: 0, paddingRight: 0 }, 300, function () {
				$(this).remove();
			});
			cardBody.remove();
			if (count > 0) {
				count--;
				badge.text(count);
				// optional: hide badge when empty
				if (count === 0) {
					badge.addClass("d-none");
					badgeRedDot.addClass("d-none");
					alertBellIcon.removeClass("fa-bell");
					alertBellIcon.addClass("fa-bell-slash");
				}
			}
			// ag: remove the notification from the dropdown
			const dropdownNotificationItem = $("#notification-dropdown-item-" + notificationId);
			if (dropdownNotificationItem) {
				dropdownNotificationItem.remove();
			}
		},
	});
});

$("#docu-sign-template-create-form-btn").on("click", function (e) {
	e.preventDefault();
	let btn = $(this);

	let form = document.getElementById("docu-sign-template-create-form");
	let valid = validateFormFields(form);
	if (!valid) {
		window.notyf.error("Please make sure all required fields are filled out before submitting.");
	} else {
		document.getElementById("page-loader").classList.remove("d-none");

		const formData = new FormData(form);

		$.ajax({
			url: "/firm/ajax/docu-sign/template/create/submit",
			method: "POST",
			data: formData,
			processData: false,
			contentType: false,
			headers: { "X-Requested-With": "XMLHttpRequest" },
			success(data) {
				document.getElementById("page-loader").classList.add("d-none");

				if (data.success == true) {
					// const SIGN_URL = {{ embeddedUrl|json_encode|raw }};
					const SIGN_URL = data.data["templateUrl"];

					const client = new HelloSign({
						clientId: data.data["clientId"],
					});

					// ag: this is only for embedding the iframe onto the page.
					// const el = document.getElementById('sign-container');

					// ag: a way to listen to the sdk event on finish and how to handle the page.
					client.on("finish", function (data) {
						window.onbeforeunload = null;
						window.removeEventListener("beforeunload", () => {});

						// Give the browser one tick to clear it
						setTimeout(() => {
							window.location.reload();
						}, 3000);
					});

					client.open(SIGN_URL, {
						testMode: true,
						skipDomainVerification: true,
					});
				} else {
					window.notyf.error(data.message);
				}
			},
			complete: function () {},
		});
	}
});

$("#firm-import-client-csv-submit").on("click", function (e) {
	e.preventDefault();
	let btn = $(this);

	// ag: set spinner on submit button
	btn.find(".btn-text").addClass("d-none");
	btn.find(".spinner-border").removeClass("d-none");
	btn.prop("disabled", true);

	const formData = new FormData();
	const fileInput = document.getElementById("firm-client-csv-input");

	formData.append("file", fileInput.files[0]);

	$.ajax({
		url: "/firm/ajax/firm-client-import-csv/submit",
		method: "POST",
		data: formData,
		processData: false,
		contentType: false,
		headers: { "X-Requested-With": "XMLHttpRequest" },
		success(data) {
			handleAjaxReturnedData(data, btn);
		},
		complete: function () {
			// Restore button
			btn.find(".btn-text").removeClass("d-none");
			btn.find(".spinner-border").addClass("d-none");
			btn.prop("disabled", false);
		},
	});
});

$("#firm-trash-restore-selected-files").on("click", function () {
	let selected = [];

	$(".file-checkbox:checked").each(function () {
		selected.push($(this).val());
	});

	if (selected.length === 0) {
		alert("Please select at least one file.");
		return;
	}

	// Send to backend to create ZIP or single download
	window.location = "/firm/restore/deleted/files?ids=" + selected.join(",");
});

$("#firm-trash-delete-selected-files").on("click", function () {
	let selected = [];

	$(".file-checkbox:checked").each(function () {
		selected.push($(this).val());
	});

	if (selected.length === 0) {
		alert("Please select at least one file.");
		return;
	}

	// Send to backend to create ZIP or single download
	window.location = "/firm/file/delete/permenant?ids=" + selected.join(",");
});

$("#firm-files-soft-delete-btn").on("click", function () {
	let selected = [];

	$(".file-checkbox:checked").each(function () {
		selected.push($(this).val());
	});

	if (selected.length === 0) {
		alert("Please select at least one file.");
		return;
	}

	//
	window.location = "/firm/file/soft/delete?ids=" + selected.join(",");
});

//
// DOCUSIGN TEMPLATES PAGE LOGIC
//

$(".docu-sign-gallery-template-checkbox").on("change", function (e) {
	if (e.currentTarget.checked == true) {
		$("#docu-sign-template-create-file-input-div").addClass("d-none");
		$("#signature-template-gallery-selection-form").removeClass("d-none");
		$('[name="docu_sign_new_template_file"]').val("");
		$('[name="docu_sign_new_template_file"]').removeAttr("required");
		$("#docu-sign-template-gallery-select-dropdown").attr("required", true);
	} else {
		$("#docu-sign-template-create-file-input-div").removeClass("d-none");
		$("#signature-template-gallery-selection-form").addClass("d-none");
		$("#docu-sign-template-gallery-select-dropdown").val("");
		$("#docu-sign-template-gallery-select-dropdown").removeAttr("requied");
		$('[name="docu_sign_new_template_file"]').attr("required", true);
	}
});

// ag: use template button in the view modal of pdf templates
$(document).on("click", "[data-use-signature-template]", function (e) {
	e.preventDefault();

	const btn = $(this);
	const url = btn.data("url");

	toggleButtonLoading(this, true, true);

	$.ajax({
		url: url,
		method: "POST",
		headers: {
			"X-Requested-With": "XMLHttpRequest",
		},
		success(data) {
			if (!data.success) {
				window.notyf.error(data.message ?? "Unable to use this template.");
				return;
			}

			const signUrl = data.data.fileUrl;

			const client = new HelloSign({
				clientId: data.data.dropboxSignClientId,
			});

			client.on("finish", function () {
				window.notyf.success("Request successfully submitted, and emails sent to signers.");

				setTimeout(() => {
					window.location.reload();
				}, 3000);
			});

			client.open(signUrl, {
				testMode: true,
				skipDomainVerification: true,
			});
		},
		error(xhr) {
			window.notyf.error(xhr.responseJSON?.message ?? "Unable to create the signature request.");
		},
		complete() {
			toggleButtonLoading(btn[0], false, true);
		},
	});
});

// ag: file upload loagic for the docu-sign-request page. Only render this code when user is on the page.
$(function () {
	if (window.location.pathname === "/firm/docu-sign-request") {
		// Page-specific JS here
		const dropZone = document.getElementById("drop-zone");
		const fileInput = document.getElementById("docu-sign-request-file");
		const dropZoneInner = document.getElementById("drop-zone-inner");

		// Open file picker on click
		dropZone.addEventListener("click", () => {
			fileInput.click();
		});

		// Visual feedback
		dropZone.addEventListener("dragover", (e) => {
			e.preventDefault();
			dropZone.classList.add("dragover");
		});

		dropZone.addEventListener("dragleave", () => {
			dropZone.classList.remove("dragover");
		});

		dropZone.addEventListener("drop", (e) => {
			e.preventDefault();
			dropZone.classList.remove("dragover");

			if (!e.dataTransfer.files.length) return;

			fileInput.files = e.dataTransfer.files;
			showFile(e.dataTransfer.files[0]);
		});

		// Handle manual selection
		fileInput.addEventListener("change", () => {
			if (!fileInput.files.length) return;
			showFile(fileInput.files[0]);
		});

		// Display selected file
		function showFile(file) {
			dropZoneInner.innerHTML = `
                <i class="fas fa-cloud-upload-alt me-2"></i><br>
                <strong>${file.name}</strong><br>
                <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
            `;
		}
	}
});

// ag: for the time display in the notification.
document.querySelectorAll(".notification-time").forEach((el) => {
	el.innerHTML = timeAgo(parseInt(el.dataset.timestamp, 10));
});
