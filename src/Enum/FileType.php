<?php

namespace App\Enum;

enum FileType: string
{
    case pdf = 'pdf';
    case word = 'word';
    case excel = 'excel';
    case image = 'images';
    case zip = 'zip';
    case text = 'text';
    case video = 'video';
    case audio = 'audio';
    case rar = 'rar';
    case exe = 'exe';
    case dwg = 'dwg';
    case unknown = 'unknown';

    public function icon(): string
    {
        return match ($this) {
            self::pdf       => 'fas fa-file-pdf text-danger',
            self::word      => 'fas fa-file-word text-primary',
            self::excel     => 'fas fa-file-excel text-success',
            self::image     => 'fas fa-file-image text-info',
            self::zip       => 'fas fa-file-archive text-warning',
            self::text      => 'fas fa-file-alt text-secondary',
            self::video     => 'fas fa-file-video text-purple',
            self::audio     => 'fas fa-file-audio text-primary',
            self::rar       => 'fab fa-stack-overflow',
            self::exe       => 'fas fa-robot',
            self::dwg       => 'fas fa-file',
            self::unknown   => 'fas fa-file',
        };
    }

    public function getBackgroundClass(): string
    {
        return match ($this) {
            self::pdf       => 'bg-primary-subtle text-primary',
            self::word      => 'bg-primary-subtle text-primary',
            self::excel     => 'bg-success-subtle text-success',
            self::image     => 'bg-info-subtle text-info',
            self::zip       => 'bg-danger-subtle text-danger',
            self::text      => 'bg-secondry-subtle text-secondary',
            self::video     => 'bg-danger-subtle text-danger',
            self::audio     => 'bg-info-subtle text-info',
            self::rar       => 'bg-warning-subtle text-warning',
            self::exe       => 'bg-danger-subtle text-danger',
            self::dwg   => 'bg-secondary-subtle text-secondary',
            self::unknown   => 'bg-secondary-subtle text-secondary',
        };
    }

    public static function fromExtension(string $extension): self
    {
        return match (strtolower($extension)) {
            'pdf'                                                   => self::pdf,
            'doc', 'docx', 'documents'                              => self::word,
            'xls', 'xlsx', 'csv'                                    => self::excel,
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'images'    => self::image,
            'zip', 'rar', '7z'                                      => self::zip,
            'txt'                                                   => self::text,
            'mp4', 'avi', 'mov', 'mkv', 'webm'                      => self::video,
            'mp3', 'wav', 'aac', 'ogg'                              => self::audio,
            'exe'                                                   => self::exe,
            'dwg'                                                   => self::dwg,
            default                                                 => self::unknown,
        };
    }

    public function isPreviewable(): bool
    {
        return match ($this) {
            self::zip,
            self::rar,
            self::dwg,
            self::exe => false,
            default => true,
        };
    }

    public static function getNonPreviewableExtensions(): array
    {
        return array_map(
            fn(self $type) => $type->value,
            array_filter(
                self::cases(),
                fn(self $type) => !$type->isPreviewable()
            )
        );
    }

    public static function getBrowserPreviewableExtensions(): array
    {
        return [
            // Documents
            'pdf',
            'txt',

            // Images
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'svg',

            // Video
            'mp4',
            'webm',
            'ogg',

            // Audio
            'mp3',
            'wav',
            'ogg',
        ];
    }
}
