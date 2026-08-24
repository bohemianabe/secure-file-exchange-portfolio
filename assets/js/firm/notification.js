//
// NOTIFICATION LOGIC
//

import {
    playNewMessageSound,
    timeAgo,
    escapeHtml,
    startBrowserTabNotification,
    stopBrowserTabNotification,
    renderNotificationTimes,
    adjustMenuNotificationCount,
    toggleNotificationFeatures,
} from '../helper.js';

const currentRoute = document.body.dataset.route;

function playNotificationSound(notification) {
    // ag: maybe a future feature to add? for now sound play regardless
    // if (notification.data?.isMuted === true) {
    //     return;
    // }

    playNewMessageSound();
}

function getNotificationUrl(notification) {
    if (notification.notificationType == 'unread_conversation') {
        return notification.url + `?conversation=${notification.data.conversation_uuid}&notification=${notification.id}`;
    }

    if (notification.notificationType == 'file_uploaded') {
        return notification.url + `?ids=${notification.resourceId}`;
    }

    if (notification.url) {
        return notification.url;
    }
    return '#';
}

function buildNotificationMessage(notification) {
    const actorUser = escapeHtml(notification.actorUser || 'Someone');
    const title = escapeHtml(notification.title || 'Notification');
    const message = escapeHtml(notification.message || '');

    switch (notification.notificationType) {
        case 'unread_conversation':
            return `<a class="text-white" href="${getNotificationUrl(notification)}">${message}</a>`;

        default:
            return `<a class="text-white" href="${getNotificationUrl(notification)}">${message}</a>`;
    }
}

function adjustNotificationDropdownItems(notification) {
    // ag: check if their are any notifications at all
    const notificationBell = $('#dropdown-notification-alert-bell');
    const badge = $('#notification-length');
    const notificationDropdownCardBody = $('#notification-dropdown-card-body');

    // ag: if fa-bell-slash is set then this is the first/new notification
    if (notificationBell.hasClass('fa-bell-slash')) {
        toggleNotificationFeatures(true);

        // ag: remove the no new notification text and then append new notification html to the drop down
        $('#notification-dropdown-no-new-notification').remove();
        notificationDropdownCardBody.prepend(notification.notificationDropdownHtml);
        renderNotificationTimes(notificationDropdownCardBody);
    } else {
        // ag: count child divs so only 5 show in the dropdown at a time
        const dropdownLength = notificationDropdownCardBody.children().length;
        // ag: if there are more than five remove the last one and prepend the new one.
        if (dropdownLength > 4) {
            notificationDropdownCardBody.children().last().remove();
        }
        notificationDropdownCardBody.prepend(notification.notificationDropdownHtml);
        renderNotificationTimes(notificationDropdownCardBody);
    }
    // ag: increase badge count
    adjustMenuNotificationCount(badge, true);
}

function showPollNotification(notification) {
    const message = buildNotificationMessage(notification);
    if (!message) {
        return;
    }

    notyf.open({
        type: 'notification_popup' || 'default',
        icon: { className: notification.icon + ' text-white', tagName: 'i' },
        message: message,
    });

    // ag: add notification to dropdown list
    adjustNotificationDropdownItems(notification);
    // ag: notification number in the side menu
    adjustMenuNotificationCount($('#firm-sidebar-nav-notification-num'), true);
    playNotificationSound(notification);
}

function handlePollNotifications(data, currentRoute) {
    if (!Array.isArray(data) || data.length === 0) {
        return;
    }

    // ag: For the browser tab. Only flash tab if user is not viewing this page
    if (document.hidden) {
        startBrowserTabNotification(data.length);
    }

    data.forEach((notification, index) => {
        // ag: if the user is on the chat page and the notification is an unread_conversation skip it
        if (currentRoute == 'firm_view_detail_chat' && notification.notificationType == 'unread_conversation') {
            return;
        } else {
            setTimeout(() => {
                showPollNotification(notification);
            }, index * 2000);
        }
    });
}

function pollNotifications(currentRoute) {
    $.ajax({
        url: `/firm/ajax/notifications/poll`,
        method: 'GET',
        data: {},
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success(data) {
            if (data.success == true) {
                handlePollNotifications(data.data, currentRoute);
            }
        },
    });
}

const ACTIVE_POLL_DELAY = 10000; // 10 seconds
const HIDDEN_POLL_DELAY = 60000; // 60 seconds

let pollIntervalId = null;

// ag: pass it the currenty route so I can filter out notifications depending on the page i.e. for chat page
function startNotificationPolling(currentRoute) {
    if (pollIntervalId) {
        clearInterval(pollIntervalId);
    }

    const pollDelay = document.hidden ? HIDDEN_POLL_DELAY : ACTIVE_POLL_DELAY;

    // pollNotifications(currentRoute);

    pollIntervalId = setInterval(() => {
        pollNotifications(currentRoute);
    }, pollDelay);
}

document.addEventListener('DOMContentLoaded', startNotificationPolling(currentRoute));
document.addEventListener('visibilitychange', () => {
    startNotificationPolling(currentRoute);
    if (!document.hidden) {
        stopBrowserTabNotification();
    }
});
