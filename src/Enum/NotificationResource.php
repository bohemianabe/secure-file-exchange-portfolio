<?php

// src/Enum/NotificationResource.php
namespace App\Enum;

enum NotificationResource: string
{
    case File = 'file';
    case FileRequest = 'file_request';
    case SignatureRequest = 'signature_request';
    case Conversation = 'conversation';
    case FileExportRequest = 'file_export_request';

    public function label(): string
    {
        return match ($this) {
            self::File => 'File',
            self::FileRequest => 'File Request',
            self::SignatureRequest => 'Docu Sign Request',
            self::Conversation => 'Conversation',
            self::FileExportRequest => 'File Export Request',
        };
    }
}
