import * as bootstrap from "bootstrap";

const originalTitle = document.title;
const originalFavicon = document.querySelector("link[rel~='icon']")?.href;

let titleIntervalId = null;

export function showModalAlert(modalEl, message) {
	const alertEl = modalEl.querySelector("[data-modal-alert]");

	if (!alertEl) {
		return;
	}

	alertEl.textContent = message;
	alertEl.classList.remove("d-none");
}

export function removeModalAlert(modalEl) {
	const alertEl = modalEl.querySelector("[data-modal-alert]:not(.d-none)");

	if (!alertEl) {
		return;
	}

	alertEl.textContent = "";
	alertEl.classList.add("d-none");
}

export function toggleButtonLoading(btnObj, isLoading, pageLoader = false) {
	const text = btnObj.querySelector(".submit-btn-text");
	const spinner = btnObj.querySelector(".spinner-border");

	if (isLoading) {
		text.classList.add("d-none");
		spinner.classList.remove("d-none");
		btnObj.disabled = true;
		if (pageLoader) {
			const $pageLoader = $("#page-loader");
			$pageLoader.removeClass("d-none");
		}
	} else {
		text.classList.remove("d-none");
		spinner.classList.add("d-none");
		btnObj.disabled = false;
		if (pageLoader) {
			const $pageLoader = $("#page-loader");
			$pageLoader.addClass("d-none");
		}
	}
}

// ag: function to toggle icons and buttons in notification side menu and dropdown
export function toggleNotificationFeatures(status) {
	// ag: if true add active bell icon to dropdown and menu
	const alertBellIcon = $("#dropdown-notification-alert-bell");
	const menuAlertBellIcon = $("#menu-bar-notification-bell-icon");
	const notificationCountDiv = $("#bell-notification-count");
	const notificationDropdownMarkAllReadBtn = $("#notification-dropdown-mark-all-read");

	if (status) {
		alertBellIcon.addClass("fa-bell");
		alertBellIcon.removeClass("fa-bell-slash");

		menuAlertBellIcon.addClass("fa-bell");
		menuAlertBellIcon.removeClass("fa-bell-slash");

		notificationCountDiv.removeClass("d-none");
		notificationDropdownMarkAllReadBtn.removeClass("d-none");
	} else {
		alertBellIcon.removeClass("fa-bell");
		alertBellIcon.addClass("fa-bell-slash");

		menuAlertBellIcon.removeClass("fa-bell");
		menuAlertBellIcon.addClass("fa-bell-slash");
	}
}

export function startBrowserTabNotification(count = 1) {
	// Change title
	let showAlert = true;

	clearInterval(titleIntervalId);

	titleIntervalId = setInterval(() => {
		document.title = showAlert ? `(${count}) New notification` : originalTitle;

		showAlert = !showAlert;
	}, 1000);
}

export function adjustMenuNotificationCount(el, increase = true) {
	if (increase) {
		let currentCount = parseInt(el.text().trim(), 10) || 0;
		el.text(currentCount + 1);
	} else {
		let currentCount = parseInt(el.text().trim(), 10) || 0;

		if (currentCount > 0) {
			el.text(currentCount - 1);
		}
	}
}

// ag: function to format the date on the notification dropdown
export function renderNotificationTimes(container = document) {
	$(container)
		.find(".notification-time")
		.each(function () {
			const timestamp = Number($(this).data("timestamp"));

			if (!timestamp) {
				return;
			}

			$(this).text(timeAgo(timestamp));
		});
}

export function stopBrowserTabNotification() {
	clearInterval(titleIntervalId);
	titleIntervalId = null;

	document.title = originalTitle;

	const favicon = document.querySelector("link[rel~='icon']");
	if (favicon && originalFavicon) {
		favicon.href = originalFavicon;
	}
}

export function flashConversation(conversationId) {
	const convoBtn = document.querySelector(`.conversation-user-trigger[data-conversation-id="${conversationId}"]`);
	const isActive = convoBtn.classList.contains("active");

	if (!convoBtn || isActive) {
		return;
	}

	// Restart animation if already present
	convoBtn.classList.remove("conversation-flash");

	// Force reflow so animation can replay
	void convoBtn.offsetWidth;

	convoBtn.classList.add("conversation-flash");

	// Optional cleanup
	convoBtn.addEventListener(
		"animationend",
		() => {
			convoBtn.classList.remove("conversation-flash");
		},
		{ once: true },
	);
}

export function handleAjaxReturnedData(data, btnObject, pageReload = true) {
	// ag: confirm btn object exist. could be just an <a> tag.
	if (btnObject !== null) {
		let modal = btnObject.closest(".modal");
		let modalInstance = bootstrap.Modal.getInstance(modal[0]);
	}

	if (data.success == true) {
		// ag: if successful clear out form, don't do it if it fails
		if (btnObject !== null) {
			let form = modal.find("form")[0];
			let modal = btnObject.closest(".modal");
			if (form) {
				form.reset();
			}

			// optional: also clear file input preview if you have one
			$(modal).find('input[type="file"]').val("");
			if (modalInstance) {
				modalInstance.hide();
			}
		}
		// ag: window.notyf set globally in root app.js file
		if (data.message) {
			window.notyf.success(data.message);
		}
		if (pageReload) {
			setTimeout(function () {
				window.location.reload();
			}, 4000);
		}
	} else {
		window.notyf.error(data.message);
	}
}

export function validateFormFields(formEl) {
	let isValid = true;

	const fields = formEl.querySelectorAll("input, select, textarea");

	fields.forEach((field) => {
		const value = field.value.trim();
		const isRequired = field.hasAttribute("required");

		let fieldIsValid = true;

		// Required field validation
		if (isRequired && value === "") {
			fieldIsValid = false;
		}

		// Email validation
		if (field.type === "email" && value !== "" && !field.validity.valid) {
			fieldIsValid = false;
		}

		// Apply Bootstrap validation class
		field.classList.toggle("is-invalid", !fieldIsValid);

		if (!fieldIsValid) {
			isValid = false;
		}
	});

	return isValid;
}

export function truncateText(str, maxLength) {
	// If the string length is less than or equal to the maxLength, return the original string
	if (str.length <= maxLength) {
		return str;
	}

	// If maxLength is too small to even contain the ellipsis,
	// we might need to adjust or simply return a truncated string with ellipsis.
	// For simplicity, this example assumes maxLength allows for the ellipsis.
	if (maxLength <= 3) {
		// If maxLength is 3 or less, we just show the ellipsis
		return "...";
	}

	// Slice the string to the desired length minus the length of the ellipsis
	// and then append the ellipsis.
	return str.slice(0, maxLength - 3) + "...";
}

export function unlockNewMessageSound() {
	const sound = document.getElementById("new-notification-sound");

	if (!sound) {
		return;
	}

	sound.muted = true;

	sound
		.play()
		.then(() => {
			sound.pause();
			sound.currentTime = 0;
			sound.muted = false;
			soundUnlocked = true;
		})
		.catch(() => {
			sound.muted = false;
		});
}

export function playNewMessageSound() {
	const sound = document.getElementById("new-notification-sound");

	if (!sound) return;

	sound.currentTime = 0;
	sound.play().catch(() => {
		// Browser may block sound until user interacts with page
	});
}

export function timeAgo(timestamp) {
	if (!timestamp) return null;

	const now = Math.floor(Date.now() / 1000);
	const diff = now - timestamp;

	if (diff < 60) return "just now";

	const minutes = Math.floor(diff / 60);
	if (minutes < 60) return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;

	const hours = Math.floor(diff / 3600);
	if (hours < 24) return `${hours} hour${hours > 1 ? "s" : ""} ago`;

	const days = Math.floor(diff / 86400);
	return `${days} day${days > 1 ? "s" : ""} ago`;
}

export function escapeHtml(value) {
	return String(value).replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
}

export function sanitizeString(str) {
	str = str.replace(/[^a-zA-Z0-9\s]/g, "");
	return str.trim();
}

export function initModalSelect2(modalEl) {
	$(modalEl)
		.find(".select2")
		.each(function () {
			if ($(this).hasClass("select2-hidden-accessible")) {
				$(this).select2("destroy");
			}

			const options = {
				width: "100%",
				dropdownParent: $(modalEl),
				placeholder: "Select an option",
				allowClear: true,
			};

			// ag: for the upload file modal set the logic for the folder dropdown indentation
			if ($(this).hasClass("file-upload-assigned-folder")) {
				options.templateResult = function (data) {
					if (!data.id) {
						return data.text;
					}

					const depth = Number(data.element.dataset.depth || 0);
					const span = document.createElement("span");
					span.style.paddingLeft = `${depth * 20}px`;
					span.textContent = data.text;

					return span;
				};
			}

			$(this).select2(options);
		});
}

// ag: logic for the datatable in the access log modal
export function initializeAccessLogTable(container) {
	const table = container.querySelector("[data-access-log-table]");
	if (!table) {
		return;
	}

	// Prevent "Cannot reinitialise DataTable" errors.
	if ($.fn.DataTable.isDataTable(table)) {
		$(table).DataTable().destroy();
	}

	$(table).DataTable({
		pageLength: 10,
		lengthMenu: [10, 25, 50, 100],
		order: [[1, "desc"]],
		autoWidth: false,
		responsive: true,
		language: {
			emptyTable: "No access activity has been recorded for this file.",
			search: "Search logs:",
			lengthMenu: "Show _MENU_ entries",
		},
		columnDefs: [
			{
				targets: [2, 3],
				orderable: true,
			},
			{
				targets: 4,
				orderable: false,
			},
		],
	});
}
