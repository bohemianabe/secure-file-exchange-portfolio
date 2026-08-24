<?php

// src/Enum/EmailNotificationMode.php
namespace App\Enum;

enum NotificationEmailMode: string
{
    case Default = 'default';
    case Written = 'written';
    case CustomMessage = 'custom_message';
}
