<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final readonly class ShowAbilityRow
{
    public function __construct(
        public string $type,
        public string $name,
        public string $status,
        public string $scopeLabel,
        public bool $dualScopeWarning,
        public string $targetsText,
    ) {}

    public function formatLine(): string
    {
        $statusPart = $this->scopeLabel === ''
            ? $this->status
            : $this->status . ' ' . $this->scopeLabel;

        $warnPart = $this->dualScopeWarning
            ? ' [warn] installed in both user and project'
            : '';

        return sprintf(
            '%s:%s  %s%s   %s',
            $this->type,
            $this->name,
            $statusPart,
            $warnPart,
            $this->targetsText,
        );
    }
}
