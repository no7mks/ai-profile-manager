<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final readonly class ShowAbilityRow
{
    public function __construct(
        public string $type,
        public string $name,
        public string $status,
        public string $targetsText,
    ) {}

    /** 格式: {type}:{name}  {status}   {targets} */
    public function formatLine(): string
    {
        return sprintf(
            '%s:%s  %s   %s',
            $this->type,
            $this->name,
            $this->status,
            $this->targetsText,
        );
    }
}
