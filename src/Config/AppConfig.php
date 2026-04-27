<?php

declare(strict_types=1);

namespace AiProfileManager\Config;

final class AppConfig
{
    public const DEFAULT_SKILLS = [
        'graphify',
    ];

    public const DEFAULT_RULES = [];

    public const DEFAULT_AGENTS = [];

    public const KNOWN_PRESETS = [
        'gitflow',
        'kiro-spec',
    ];

    /**
     * @var array<string, array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>}>
     */
    public const PRESET_ITEMS = [
        'gitflow' => [
            'skills' => ['gitflow'],
            'rules' => [],
            'agents' => ['flow-starter', 'flow-finisher'],
        ],
        'kiro-spec' => [
            'skills' => ['kiro-spec-planning', 'kiro-spec-execution'],
            'rules' => ['kiro-spec-steering'],
            'agents' => ['gatekeeper'],
        ],
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
