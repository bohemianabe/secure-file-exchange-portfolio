import { truncateText, handleAjaxReturnedData, validateFormFields } from "../helper.js";

// ************************************************************************************************************************* //
// *************************************************** DATATABLE LOGIC ***************************************************** //
// ************************************************************************************************************************* //

//
// DOCU SIGN REQUEST PAGE
//

// ag: use this to filter on user access. Admins can see all files, employees only see assigned clients.
let signatureStatuses = {};

$("#docu-sign-view-requests-table").DataTable({
	pageLength: 25,
	order: [[4, "desc"]], // default sort first column
	processing: true,
	serverSide: true,
	pageLength: 10, // default page size
	lengthMenu: [10, 25, 50, 100], // user can change it
	ajax: {
		url: "/firm/ajax/docu-sign-request-table/data/call",
		dataSrc: function (json) {
			signatureStatuses = json.statuses || {};
			return json.data;
		},
	},
	createdRow: function (row, data, dataIndex) {
		const status = data.status || "";

		if (status === signatureStatuses.request_canceled) {
			$(row).addClass("table-danger");
		}
	},
	columns: [
		{ data: "title" },
		{
			data: "email",
			orderable: false,
		},
		{
			data: "status",
			orderable: false,
		},
		{
			data: "viewed",
			orderable: false,
		},
		{ data: "createdAt" },
		{
			data: "fileUuid",
			orderable: false,
			searchable: false,
			title: "View",
			className: "text-center",
			render: function (id, type, row) {
				if (type !== "display") return id;

				const url = `/firm/docu-sign-request/view/${id}/modal`;
				return `
                    <a href="${url}"
                    class="btn btn-primary btn-sm text-white"
                    data-url="${url}"
                    data-bs-target="#defaultTemplateModal"
                    data-modal-title="Signature Request"
                    data-dynamic-load="true"
                    data-modal-size="modal-lg"
                    >
                        View
                    </a>
                `;
			},
		},
	],
});

//
//  DOCU SIGN TEMPLATE PAGE
//
$("#firm-docu-sign-template-table").DataTable({
	pageLength: 25,
	order: [[3, "asc"]],
	processing: true,
	serverSide: true,
	pageLength: 10, // default page size
	lengthMenu: [10, 25, 50, 100], // user can change it
	ajax: {
		url: "/firm/ajax/docu-sign/template/table/call",
	},
	order: [[2, "desc"]], // createdAt DESC by default
	columns: [
		{ data: "title" },
		{ data: "createdBy" },
		// { data: 'status' },
		{ data: "createdAt" },
		{
			data: "id",
			orderable: false,
			searchable: false,
			title: "Edit",
			className: "text-center",
			render: function (id, type, row) {
				if (type !== "display") return id; // for sort/search pipelines
				const url = `/firm/docu-sign/template/modal/${id}`;
				return `
                    <a 
                    class="btn btn-primary text-white"
                    href="${url}"
                    data-bs-target="defaultTemplateModal" 
                    data-modal-title="Edit Template" 
                    data-dynamic-load="true"
                    data-modal-size="modal-md"
                        >
                        Edit
                    </a>`;
			},
		},
	],
});

//
// SUPPORT VIEW PAGE
//
$("#firm-support-view-table").DataTable({
	pageLength: 25,
	order: [[1, "asc"]],
	processing: true,
	serverSide: true,
	pageLength: 10, // default page size
	lengthMenu: [10, 25, 50, 100], // user can change it
	ajax: {
		url: "/firm/ajax/support-table/data/call",
		data: function (d) {
			d.filter = currentFilter;
		},
	},
	order: [[4, "desc"]], // createdAt DESC by default
	columns: [
		{ data: "ticketNumber" },
		{ data: "subject" },
		{ data: "status" },
		{ data: "priority" },
		{ data: "createdAt" },
		{
			data: "id",
			orderable: false,
			searchable: false,
			title: "View",
			className: "text-center",
			render: function (id, type, row) {
				if (type !== "display") return id; // for sort/search pipelines
				const url = `/firm/support-view-detail/${id}`;
				return `
                    <a href="${url}"
                        >
                        View
                    </a>`;
			},
		},
	],
});

//
// REQUEST FILES PAGE
//

// ag: use this to filter on user access. Admins can see all files, employees only see assigned clients.
$("#firm-request-files-view-table").DataTable({
	pageLength: 25,
	order: [[1, "asc"]],
	processing: true,
	serverSide: true,
	pageLength: 10, // default page size
	lengthMenu: [10, 25, 50, 100], // user can change it
	ajax: {
		url: "/firm/ajax/file-request-table/data/call",
	},
	createdRow: function (row, data, dataIndex) {
		if (data.status === "Invitation Expired") {
			$(row).addClass("table-danger");
		}

		if (data.status === "Cancelled") {
			$(row).addClass("table-warning");
		}
	},
	order: [[5, "desc"]], // createdAt DESC by default
	columns: [
		{ data: "fileRequestUser" },
		{ data: "title" },
		{ data: "recipientName" },
		{ data: "email" },
		// { data: 'requestType' },
		{ data: "status" },
		{ data: "createdAt" },
		{
			data: "id",
			orderable: false,
			searchable: false,
			title: "View",
			className: "text-center",
			render: function (id, type, row) {
				if (type !== "display") return id; // for sort/search pipelines
				const url = `/firm/file-request/view/${id}/modal`;
				return `
                    <a href="${url}"
                        class="btn btn-primary text-white"
                        data-url="${url}"
                        data-bs-target="defaultTemplateModal" 
                        data-modal-title="File Request" 
                        data-dynamic-load="true"
                        >
                        View
                    </a>`;
			},
		},
	],
});

//
// client-view-detail page logic
//

let clientViewDetailId = $("#client-detail-view-file-table").data("client-id");

$("#client-detail-view-file-table").DataTable({
	processing: true,
	serverSide: true,
	pageLength: 10, // default page size
	lengthMenu: [10, 25, 50, 100], // user can change it
	ajax: {
		url: "/firm/ajax/client-view-detail-table/data/call/" + clientViewDetailId,
		data: function (d) {
			d.filter = currentFilter;
		},
	},
	order: [[5, "desc"]], // createdAt DESC by default
	columns: [
		{
			data: "id",
			orderable: false,
			searchable: false,
			className: "text-center",
			render: function (id, type, row) {
				if (type !== "display") return id; // keep search/sort pipelines clean
				return `
                    <input type="checkbox" class="file-checkbox" value="${id}">
                `;
			},
		},
		{
			data: "fileName",
			render: function (data, type, row) {
				if (type === "display") {
					return '<i class="' + row.fileIcon + ' me-1"></i> <span data-toggle="tooltip" title="' + data + '">' + truncateText(data, 20) + "</span>";
				}
				return data;
			},
		},
		{ data: "uploadedBy" },
		{ data: "assignedTo" },
		{ data: "createdAt" },
		{ data: "lastAccessedAt" },
		{
			data: "uuid",
			orderable: false,
			searchable: false,
			title: "View",
			className: "text-center",
			render: function (uuid, type, row) {
				if (type !== "display") return uuid; // for sort/search pipelines
				const url = `/firm/${uuid}/edit/modal`;
				return `
                    <a href="${url}"
                        class="btn btn-primary text-white"
                        data-url="${url}"
                        data-bs-target="defaultTemplateModal" 
                        data-modal-title="File" 
                        data-dynamic-load="true"
                        >
                        View
                    </a>`;
			},
		},
	],
});

$("#firm-client-view-table").DataTable({
	pageLength: 25,
	order: [[1, "asc"]],
});

//
// client-view page logic
//
$("#client-view-clients-table").DataTable({
	pageLength: 25,
	order: [[1, "asc"]], // default sort first column
});

//
// trash-view page logic
//

$("#firm-trash-view-file-table").DataTable({
	processing: true,
	serverSide: true,
	pageLength: 10, // default page size
	lengthMenu: [10, 25, 50, 100], // user can change it
	ajax: {
		url: "/firm/ajax/trash-file-table/data/call",
		data: function (d) {
			d.filter = currentFilter;
		},
	},
	order: [[5, "desc"]], // createdAt DESC by default
	columns: [
		{
			data: "id",
			orderable: false,
			searchable: false,
			className: "text-center",
			render: function (id, type, row) {
				if (type !== "display") return id; // keep search/sort pipelines clean
				return `
                    <input type="checkbox" class="file-checkbox" value="${id}">
                `;
			},
		},
		{ data: "name" },
		{
			data: "fileName",
			render: function (data, type, full, meta) {
				if (type === "display") {
					return '<span data-toggle="tooltip" title="' + data + '">' + truncateText(data, 20) + "</span>";
				}
				return data;
			},
		},
		{ data: "uploadedBy" },
		{ data: "assignedTo" },
		{ data: "createdAt" },
		{ data: "expirationDate" },
		{
			data: "id",
			orderable: false,
			searchable: false,
			title: "Restore",
			className: "text-center",
			render: function (id, type, row) {
				if (type !== "display") return id; // for sort/search pipelines
				const url = `/firm/restore/deleted/files?ids=${id}`;
				// const url = `/firm/file-edit/${id}`;
				return `
                    <a href="${url}"
                        class="btn btn-primary text-white"
                        data-url="${url}"
                        >
                        Restore
                    </a>`;
			},
		},
	],
});

//
// file manager table logic
//

let fileManagerFilter = "all";

let fileManagerTable = $("#firm-file-manager-view-table").DataTable({
	processing: true,
	serverSide: true,
	pageLength: 10, // default page size
	lengthMenu: [10, 25, 50, 100], // user can change it
	ajax: {
		url: "/firm/ajax/file-manager-table/data/call",
		data: function (d) {
			d.client = new URLSearchParams(window.location.search).get("client");
			d.filter = fileManagerFilter;
		},
	},
	order: [[3, "desc"]], // createdAt DESC by default
	columns: [
		{
			data: "id",
			orderable: false,
			searchable: false,
			className: "text-center",
			render: function (id, type, row) {
				if (type !== "display") return id; // keep search/sort pipelines clean
				return `
                    <input type="checkbox" class="file-checkbox" value="${id}">
                `;
			},
		},
		{
			data: "originalName",
			render: function (data, type, row) {
				if (type === "display") {
					return '<i class="' + row.fileIcon + ' me-1"></i> <span data-toggle="tooltip" title="' + data + '">' + truncateText(data, 20) + "</span>";
				}
				return data;
			},
		},
		{
			data: "uploadedBy",
			orderable: false,
		},
		{ data: "createdAt" },
		{
			data: "uuid",
			orderable: false,
			searchable: false,
			title: "View",
			className: "text-center",
			render: function (uuid, type, row) {
				if (type !== "display") return uuid; // for sort/search pipelines
				const url = `/firm/${uuid}/edit/modal`;
				return `
                    <a href="${url}"
                        class="btn btn-primary text-white"
                        data-url="${url}"
                        data-bs-target="defaultTemplateModal" 
                        data-modal-title="File" 
                        data-dynamic-load="true"
                        >
                        View
                    </a>`;
			},
		},
		{
			data: "accessLog",
			orderable: false,
			searchable: false,
			title: "Access Log",
			className: "text-center",
			render: function (uuid, type, row) {
				if (type !== "display") return uuid; // for sort/search pipelines
				const url = `/firm/${uuid}/accesslog/modal`;
				return `
                    <a href="${url}"
                        data-bs-toggle="offcanvasRight"
                        data-bs-target="#offcanvasRight" 
                        aria-controls="offcanvasRight"

                        class="btn btn-success text-white"
                        data-url="${url}"
                        data-modal-title="Access Log" 
                        data-dynamic-offcanvas-rightload="true"
                        >
                        <i class="far fa-edit"></i>
                    </a>`;
			},
		},
	],
});

$(".file-manager-sort-by-btn").on("click", function (e) {
	let sortBy = e.currentTarget.dataset.sortBy;
	let sortByText = e.currentTarget.dataset.sortByText;
	$("#file-manager-description-view").text(sortByText);
	fileManagerFilter = sortBy;
	fileManagerTable.ajax.reload();
});

//
// dashboard table logic
//

// ag: use this to filter on user access. Admins can see all files, employees only see assigned clients.
// let userRole = $('#firm-dashboard-view-file-table').data('user-role');

let currentFilter = "all"; // default

let dashboardTable = $("#firm-dashboard-view-file-table").DataTable({
	processing: true,
	serverSide: true,
	pageLength: 10, // default page size
	lengthMenu: [10, 25, 50, 100], // user can change it
	ajax: {
		url: "/firm/ajax/file-table/data/call",
		data: function (d) {
			d.filter = currentFilter;
		},
	},
	order: [[4, "desc"]], // createdAt DESC by default
	columns: [
		{
			data: "id",
			orderable: false,
			searchable: false,
			className: "text-center",
			render: function (id, type, row) {
				if (type !== "display") return id; // keep search/sort pipelines clean
				return `
                    <input type="checkbox" class="file-checkbox" value="${id}">
                `;
			},
		},
		{
			data: "originalName",
			render: function (data, type, row) {
				if (type === "display") {
					return '<i class="' + row.fileIcon + ' me-1"></i> <span data-toggle="tooltip" title="' + data + '">' + truncateText(data, 20) + "</span>";
				}
				return data;
			},
		},
		{
			data: "uploadedBy",
			orderable: false,
		},
		{
			data: "assignedTo",
			orderable: false,
		},
		{ data: "createdAt" },
		{ data: "lastAccessedAt" },
		{
			data: "uuid",
			orderable: false,
			searchable: false,
			title: "View",
			className: "text-center",
			render: function (uuid, type, row) {
				if (type !== "display") return uuid; // for sort/search pipelines
				const url = `/firm/${uuid}/edit/modal`;
				return `
                    <a href="${url}"
                        class="btn btn-primary text-white"
                        data-url="${url}"
                        data-bs-target="defaultTemplateModal" 
                        data-modal-title="File" 
                        data-dynamic-load="true"
                        >
                        View
                    </a>`;
			},
		},
	],
});

// ag: upon repopulating the datatable clear all the checkboxes
dashboardTable.on("draw", function () {
	$("#check-all").prop("checked", false);
});

// ag: logic for check boxes
$("#file-table-check-all-checkbox").on("change", function () {
	const checked = this.checked;
	$(".file-checkbox").prop("checked", checked);
});

$("#firm-download-table-selected-files").on("click", function () {
	let selected = [];

	$(".file-checkbox:checked").each(function () {
		selected.push($(this).val());
	});

	if (selected.length === 0) {
		alert("Please select at least one file.");
		return;
	}

	// Send to backend to create ZIP or single download
	window.location = "/firm/files/download?ids=" + selected.join(",");
});

$("#dashboard-files-received-btn").on("click", function () {
	$("#dashboard-file-description-view").text("Received Files (past 30 days)");
	currentFilter = "received";
	dashboardTable.ajax.reload();
});

$("#dashboard-files-sent-btn").on("click", function () {
	$("#dashboard-file-description-view").text("Sent Files (past 30 days)");

	currentFilter = "sent";
	dashboardTable.ajax.reload();
});

$("#dashboard-files-all-btn").on("click", function () {
	$("#dashboard-file-description-view").text("All Files");
	currentFilter = "all";
	dashboardTable.ajax.reload();
});

$("#firm-canned-messages-view-table").DataTable({
	pageLength: 25,
	order: [[0, "asc"]], // default sort first column
});
