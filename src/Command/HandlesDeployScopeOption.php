<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Service\InvalidScopeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait HandlesDeployScopeOption
{
    /** 注册 --scope 选项（保持 CLI 签名兼容性以便检测） */
    protected function configureDeployScopeOption(): void
    {
        $this->addOption(
            'scope',
            null,
            InputOption::VALUE_REQUIRED,
            'Deploy scope (project or user). Default: project.',
        );
    }

    /**
     * 检测 --scope 是否被传入，若有则抛出废弃异常。
     * 在各命令 handle() 顶部调用。
     *
     * @throws InvalidScopeException 当 --scope 有任何值时
     */
    protected function rejectIfScopeOptionPresent(InputInterface $input): void
    {
        if ($input->getOption('scope') !== null) {
            throw InvalidScopeException::scopeOptionDeprecated();
        }
    }
}
