<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

/**
 * 单个 ability 条目的值对象。
 */
final readonly class AbilityEntry
{
    /**
     * @param string $path 源文件相对路径（相对于 baseline root）
     * @param string $description 描述
     * @param array<string, string> $targets platform => target_path 映射
     * @param string $type 类型（rule|agent|skill|hook）
     */
    public function __construct(
        public string $path,
        public string $description,
        public array $targets,
        public string $type,
    ) {}
}
