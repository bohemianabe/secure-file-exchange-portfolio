<?php

namespace App\Enum;

enum NotificationType: string
{
    case ChatMessage                    = 'unread_conversation';

    case FileUploaded                   = 'file_uploaded';
    case FileDownloaded                 = 'file_downloaded';

    case FileRequestCompleted           = 'file_request_completed';
    case FileRequestCreated             = 'file_request_created';
    case FileRequestCanceled            = 'file_request_canceled';

    case NewTicketComment               = 'new_ticket_comment';
    case ClientInviteAccepted           = 'client_invitation_accepted';
    case FileExpiringSoon               = 'file_expiring_soon';

    case SignatureRequestCreated        = 'signature_request_created';
    case SignatureRequestCompleted      = 'signature_request_completed';

    case FileExportRequestCompleted     = 'file_export_request_completed';

    public function label(): string
    {
        return match ($this) {
            self::ChatMessage                   => 'Unread Conversation',
            self::FileUploaded                  => 'File Uploaded',
            self::FileDownloaded                => 'File Downloaded',
            self::FileRequestCompleted          => 'File Request Completed',
            self::FileRequestCreated            => 'File Request Created',
            self::FileRequestCanceled           => 'File Request Canceled',
            self::NewTicketComment              => 'New Ticket Comment',
            self::ClientInviteAccepted          => 'Client Invitation Accepted',
            self::FileExpiringSoon              => 'File Expiring Soon',
            self::SignatureRequestCreated       => 'Signature Request Created',
            self::SignatureRequestCompleted     => 'Signature Request Completed',
            self::FileExportRequestCompleted    => 'File Export Request Completed',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::FileUploaded                  => 'fas fa-file',
            self::ChatMessage                   => 'far fa-comment-dots',
            self::FileDownloaded                => 'fas fa-download',
            self::FileRequestCompleted          => 'fas fa-exchange-alt',
            self::FileExportRequestCompleted    => 'far fa-file-archive',
            self::NewTicketComment              => 'far fa-life-ring',
            self::SignatureRequestCompleted     => 'far fa-file-pdf',
            self::ClientInviteAccepted          => 'fas fa-user-check',
            default                             => 'fas fa-bullhorn',
        };
    }
}
