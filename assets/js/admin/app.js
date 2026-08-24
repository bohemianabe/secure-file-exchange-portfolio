import * as bootstrap from "bootstrap";

// ag: file for all the JS logic for admin view
function validateFormFields(selector, firmAccountUrl = null) {
	let isValid = true;

	$(selector).each(function (e) {
		const value = $(this).val().trim();
		const fieldName = $(this).attr("name");
		const fieldType = $(this).attr("type");

		// Custom validation for firm account URL
		if (firmAccountUrl != null) {
			if ($(this).attr("name") === "accountName" && !/^[a-z0-9]+$/.test(firmAccountUrl)) {
				$(this).addClass("is-invalid");
				isValid = false;
				alert("Firm Account URL must be a single word, lowercase letters and numbers only – no spaces or special characters.");
			}
		}

		// ag: add check. only make state required if country is US or Canada

		// Generic required field validation
		if (value === "" && $(this).prop("required")) {
			isValid = false;
			$(this).addClass("is-invalid");
		} else {
			$(this).removeClass("is-invalid");
		}

		// --- Email validation ---
		if ((fieldType === "email" || fieldName === "email") && value !== "") {
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if (!emailRegex.test(value)) {
				isValid = false;
				$(this).addClass("is-invalid");
				alert("Please enter a valid email address.");
			} else {
				$(this).removeClass("is-invalid");
			}
		}
	});

	return isValid;
}

// ag: function to handle returned ajax data
function handleAjaxReturnedData(data, btnObject, pageReload = true) {
	let modal = btnObject.closest(".modal");
	let modalInstance = bootstrap.Modal.getInstance(modal[0]);

	if (data.success == true) {
		// ag: if successful clear out form, don't do it if it fails
		let form = modal.find("form")[0];
		if (form) {
			form.reset();
		}

		// optional: also clear file input preview if you have one
		$(modal).find('input[type="file"]').val("");
		if (modalInstance) {
			modalInstance.hide();
		}
		// ag: window.notyf set globally in root app.js file
		window.notyf.success(data.message);
		if (pageReload) {
			setTimeout(function () {
				window.location.reload();
			}, 4000);
		}
	} else {
		window.notyf.error(data.message);
	}
}

// ag: attaching this to the window scope so it reaches the client-view page
window.removeAssignedFirmUser = function (firmUserId, clientId) {
	let btn = $(this);

	$.ajax({
		url: `/admin/ajax/remove-assigned-user/` + clientId,
		method: "POST",
		data: {
			assignedFirmUserId: firmUserId,
		},
		headers: { "X-Requested-With": "XMLHttpRequest" },
		success(data) {
			handleAjaxReturnedData(data, btn, false);
			if (data.success == true) {
				// ag: remove the div upon completion
				$("#assign-firm-user-" + firmUserId + "").remove();
			}
		},
		complete: function () {},
	});
};

if (document.getElementById("admin-clear-firm-csv-input")) {
	document.getElementById("admin-clear-firm-csv-input").addEventListener("click", function () {
		const fileInput = document.getElementById("admin-firm-csv-input");
		fileInput.value = ""; // reset the input
	});
}

// ag: since button is in a modal that isn't visible must pass function to the DOM
$(document).on("click", "#admin-reset-user-password", function (e) {
	const btn = $(this);
	const userId = e.currentTarget.getAttribute("data-user-id");

	$.ajax({
		url: `/admin/ajax/reset-user-password`,
		method: "POST",
		data: {
			userId: userId,
		},
		success(data) {
			handleAjaxReturnedData(data, btn, false);
		},
	});
});

$(document).on("click", "#admin-restore-soft-delete-firm-submit", function (e) {
	e.preventDefault();
	let btn = $(this);

	// ag: set spinner on submit button
	btn.find(".btn-text").addClass("d-none");
	btn.find(".spinner-border").removeClass("d-none");
	btn.prop("disabled", true);

	const firmId = $("#firm-id").val();

	$.ajax({
		url: "/admin/ajax/restore-soft-delete-firm/" + firmId + "/modal/submit",
		method: "POST",
		data: {},
		headers: { "X-Requested-With": "XMLHttpRequest" },
		success(data) {
			handleAjaxReturnedData(data, btn, false);
		},
		complete: function () {
			setTimeout(function () {
				window.location.href = "/admin/firm-view/" + firmId;
			}, 4000);
		},
	});
});

$(document).on("click", "#admin-delete-storage-plan", function (e) {
	e.preventDefault();
	let btn = $(this);
	const storagePlanId = $(this).attr("data-storage-plan-id");

	$.ajax({
		url: "/admin/ajax/delete-storage-plan/" + storagePlanId + "/submit",
		method: "POST",
		data: {},
		headers: { "X-Requested-With": "XMLHttpRequest" },
		success(data) {
			handleAjaxReturnedData(data, btn, true);
		},
		complete: function () {},
	});
});

// ag: logic for the firm forms to check if a country is either US/Canada and if the state dropdown info is required
$(document).on("change", 'select[name="country"]', function (e) {
	const stateDropdown = document.querySelector('select[name="state"]');
	const countryDropDown = document.querySelector('select[name="country"]');

	const countrySelected = countryDropDown.selectedOptions[0].textContent;
	const needsState = countrySelected === "United states" || countrySelected === "Canada";

	if (!needsState) {
		stateDropdown.required = false;
		stateDropdown.setAttribute("disabled", true);
		stateDropdown.value = "";
	} else {
		stateDropdown.required = true;
		stateDropdown.removeAttribute("disabled");
	}
});

jQuery(function ($) {
	$(".checkbox-toggle").on("change", function () {
		$(this).val($(this).is(":checked") ? "1" : "0");
	});

	// ag: this resets ALL modals to empty fields when closed
	$(document).on("hidden.bs.modal", ".modal", function () {
		// Reset all form fields inside the modal
		$(this)
			.find("form")
			.each(function () {
				this.reset(); // native reset
			});

		// Remove validation classes if you use them
		$(this).find(".is-invalid, .is-valid").removeClass("is-invalid is-valid");
	});

	$("#admin-import-firm-csv-submit").on("click", function (e) {
		e.preventDefault();
		let btn = $(this);

		// ag: set spinner on submit button
		btn.find(".btn-text").addClass("d-none");
		btn.find(".spinner-border").removeClass("d-none");
		btn.prop("disabled", true);

		const formData = new FormData();
		const fileInput = document.getElementById("admin-firm-csv-input");

		formData.append("file", fileInput.files[0]);

		$.ajax({
			url: "/admin/firm-import-csv/submit",
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

	$("#admin-soft-delete-firm-submit").on("click", function (e) {
		e.preventDefault();
		let btn = $(this);

		// ag: set spinner on submit button
		btn.find(".btn-text").addClass("d-none");
		btn.find(".spinner-border").removeClass("d-none");
		btn.prop("disabled", true);

		const firmId = $("#firm-id").val();

		$.ajax({
			url: "/admin/ajax/soft-delete-firm/modal/submit",
			method: "POST",
			data: {
				firmId: firmId,
			},
			// processData: false,
			// contentType: false,
			headers: { "X-Requested-With": "XMLHttpRequest" },
			success(data) {
				handleAjaxReturnedData(data, btn, false);
			},
			complete: function () {
				setTimeout(function () {
					window.location.href = "/admin/dashboard";
				}, 4000);
			},
		});
	});

	$("#admin-delete-client-submit").on("click", function (e) {
		e.preventDefault();
		let btn = $(this);

		// ag: set spinner on submit button
		btn.find(".btn-text").addClass("d-none");
		btn.find(".spinner-border").removeClass("d-none");
		btn.prop("disabled", true);

		const clientId = $("#client-id").val();
		const firmId = $("#firm-id").val();

		$.ajax({
			url: `/admin/ajax/delete/` + clientId + `/modal/submit`,
			method: "POST",
			data: {},
			// processData: false,
			// contentType: false,
			headers: { "X-Requested-With": "XMLHttpRequest" },
			success(data) {
				handleAjaxReturnedData(data, btn, false);
			},
			complete: function () {
				setTimeout(function () {
					window.location.href = `/admin/firm-view/` + firmId;
				}, 4000);
			},
		});
	});

	// ag: dynamic firm active/deactive logic
	const adminFirmActiveToggleModal = document.getElementById("adminToggleFirmStatusModal");

	if (adminFirmActiveToggleModal) {
		adminFirmActiveToggleModal.addEventListener("show.bs.modal", function (event) {
			const button = event.relatedTarget; // Button that triggered the modal
			const title = button.getAttribute("data-modal-title"); // Extract info from data-* attributes

			const modalTitle = adminFirmActiveToggleModal.querySelector(".modal-title");
			modalTitle.textContent = title || "Default Title";
		});
	}

	// *************************************************** DATATABLE LOGIC ***************************************************** //
	$("#admin-messenger-view-table").DataTable({
		pageLength: 25,
		order: [[1, "asc"]],
		processing: true,
		serverSide: true,
		pageLength: 10, // default page size
		lengthMenu: [10, 25, 50, 100], // user can change it
		ajax: {
			url: "/admin/ajax/messenger-log/data/call",
		},
		order: [[2, "desc"]], // createdAt DESC by default
		columns: [{ data: "id" }, { data: "body" }, { data: "createdAt" }],
	});

	$("#admin-ticket-view-table").DataTable({
		pageLength: 25,
		order: [[1, "asc"]],
		processing: true,
		serverSide: true,
		pageLength: 10, // default page size
		lengthMenu: [10, 25, 50, 100], // user can change it
		ajax: {
			url: "/admin/ajax/ticket-table/data/call",
		},
		order: [[5, "desc"]], // createdAt DESC by default
		columns: [
			{ data: "ticketNumber" },
			{ data: "subject" },
			{ data: "status" },
			{ data: "priority" },
			{ data: "firm" },
			{ data: "createdAt" },
			{ data: "updatedAt" },
			{
				data: "id",
				orderable: false,
				searchable: false,
				title: "View",
				className: "text-center",
				render: function (id, type, row) {
					if (type !== "display") return id; // for sort/search pipelines
					const url = `/admin/ticket-view-detail/${id}`;
					return `
                    <a href="${url}"
                        >
                        View
                    </a>`;
				},
			},
		],
	});

	$("#admin-dashboard-view-table").DataTable({
		processing: true,
		serverSide: true,
		pageLength: 10, // default page size
		lengthMenu: [10, 25, 50, 100], // user can change it
		ajax: "/admin/ajax/firm-table/data/call",
		order: [[6, "desc"]], // createdAt DESC by default
		columns: [
			{ data: "name" },
			{ data: "account" },
			{ data: "storagePlan" },
			{ data: "phone" },
			{
				data: "active",
				title: "Active",
				render: function (data, type, row) {
					if (type === "display" && data == true) {
						return `<span>Active</span>`;
					} else {
						return `<span>Inactive</span>`;
					}
					return data;
				},
			},
			{ data: "createdAt" },
			{ data: "updatedAt" },
			{
				data: "id",
				orderable: false,
				searchable: false,
				title: "View",
				className: "text-center",
				render: function (id, type, row) {
					if (type !== "display") return id; // for sort/search pipelines
					const url = `/admin/firm-view/${id}`;
					return `
                    <a href="${url}"
                        class=""
                        data-url="${url}"
                        >
                        View
                    </a>`;
				},
			},
		],
	});

	$("#admin-blocked-ips-view-table").DataTable({
		processing: true,
		serverSide: true,
		pageLength: 10, // default page size
		lengthMenu: [10, 25, 50, 100], // user can change it
		ajax: "/admin/ajax/blocked-ips/data/call",
		order: [[7, "desc"]], // createdAt DESC by default
		columns: [
			{ data: "id" },
			{ data: "ipAddress" },
			{ data: "blockedUntil" },
			{ data: "createdAt" },
			{
				data: "id",
				orderable: false,
				searchable: false,
				title: "View",
				className: "text-center",
				render: function (id, type, row) {
					if (type !== "display") return id; // for sort/search pipelines
					const url = `/admin/blocked-ip/modal/${id}`;
					return `
                    <a href="${url}"
                        class="btn btn-sm btn-primary text-white"
                        data-bs-toggle="modal"
                        data-modal-title="Blocked Ip" 
                        data-bs-target="adminBlockedIpModal" 
                        data-dynamic-load="true"
                        >
                        View
                    </a>`;
				},
			},
		],
	});

	$("#admin-dashboard-view-soft-delete-table").DataTable({
		pageLength: 25,
		order: [[0, "asc"]], // default sort first column
	});

	$("#admin-firm-view-table").DataTable({
		rowCallback: function (row, data) {
			// assuming "User Type" is column index 5 (0-based index, adjust as needed)
			if (data[4] === "primary") {
				$(row).addClass("table-success primary-row");
			}
		},
	});

	$("#admin-client-view-table").DataTable({
		rowCallback: function (row, data) {},
	});

	$("#admin-administrator-view-table").DataTable({
		pageLength: 10,
		order: [[0, "asc"]],
	});

	$("#admin-announcement-view-table").DataTable({
		pageLength: 10,
		order: [[0, "asc"]],
	});

	$("#admin-storage-plan-view-table").DataTable({
		pageLength: 10,
	});

	function truncate(str, n) {
		if (str == null) return "";
		return str.length > n ? str.slice(0, n) + "…" : str;
	}

	$("#admin-audit-log-view-table").DataTable({
		processing: true,
		serverSide: true,
		pageLength: 10, // default page size
		lengthMenu: [10, 25, 50, 100], // user can change it
		ajax: "/admin/ajax/audit-log/data/call",
		order: [[0, "desc"]], // occurredAt DESC by default
		columns: [
			{ data: "id" },
			{ data: "userEmail" }, // email (resolved server-side)
			{
				data: "action",
				title: "Action",
				render: function (data, type, row) {
					if (type === "display") {
						return `<span title="${data}">${truncate(data, 10)}</span>`;
					}
					return data;
				},
			},
			{ data: "resourceId" },
			{ data: "ip" },
			{ data: "success" },
			{ data: "severity" },
			{ data: "occurredAt" },
			{
				data: "id",
				orderable: false,
				searchable: false,
				title: "Metadata",
				className: "text-center",
				render: function (id, type, row) {
					if (type !== "display") return id; // for sort/search pipelines
					const url = `/admin/audit-log/${id}/view/modal`;
					return `
                    <a href="${url}"
                        class="btn btn-primary text-white"
                        data-url="${url}"
                        data-bs-target="defaultTemplateModal" 
                        data-modal-title="Metadata" 
                        data-dynamic-load="true"
                        >
                        View
                    </a>`;
				},
			},
		],
	});
}); // ag: end of jQuery
