<?php

declare(strict_types=1);

namespace AiProfileManager;

final class AppConfig
{
    public const KNOWN_ABILITIES = [
        'skills',
        'steerings',
        'rules',
        'custom-agents',
    ];

    public const DEFAULT_ABILITIES = [
        'skills',
        'rules',
    ];

    public const KNOWN_TARGETS = [
        'cursor',
        'kiro',
        'claude-code',
    ];

    public const DEFAULT_TARGETS = [
        'cursor',
    ];
}
