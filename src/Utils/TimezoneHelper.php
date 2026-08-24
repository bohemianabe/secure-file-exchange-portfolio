<?php

namespace App\Utils;

final class TimezoneHelper
{
    public const COMMON_TIMEZONES = [
        'Eastern Time (US & Canada)' => 'America/New_York',
        'Central Time (US & Canada)' => 'America/Chicago',
        'Mountain Time' => 'America/Denver',
        'Mountain Time (Arizona)' => 'America/Phoenix',
        'Pacific Time' => 'America/Los_Angeles',
        'Alaska Time' => 'America/Anchorage',
        'Hawaii Time' => 'Pacific/Honolulu',

        'Atlantic Time' => 'America/Halifax',
        'Newfoundland Time' => 'America/St_Johns',

        'Greenwich Mean Time' => 'Europe/London',
        'Central European Time' => 'Europe/Berlin',
        'Eastern European Time' => 'Europe/Bucharest',

        'India Standard Time' => 'Asia/Kolkata',
        'China Standard Time' => 'Asia/Shanghai',
        'Japan Standard Time' => 'Asia/Tokyo',
        'Korea Standard Time' => 'Asia/Seoul',
        'Singapore Time' => 'Asia/Singapore',

        'Australian Eastern Time' => 'Australia/Sydney',
        'Australian Central Time' => 'Australia/Adelaide',
        'Australian Western Time' => 'Australia/Perth',

        'New Zealand Time' => 'Pacific/Auckland',

        'UTC' => 'UTC',
    ];

    public static function choices(): array
    {
        return self::COMMON_TIMEZONES;
    }
}
