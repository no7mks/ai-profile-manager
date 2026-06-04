<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\DeployScope;

final class ShowStatusPresenter
{
    public function __construct(
        private readonly AbilityRegistry $registry,
        private readonly CheckService $checkService,
        private readonly InstallationProbe $probe,
        private readonly string $packageRoot,
        private readonly GitIgnoreTemplateService $gitIgnore = new GitIgnoreTemplateService(),
    ) {}

    /**
     * @param array<int, string> $targets
     * @return list<ShowAbilityRow>
     */
    public function rows(?DeployScope $scopeFilter, array $targets, ?string $typeFilter): array
    {
        $abilities = $this->enumerateAbilities($typeFilter);
        $rows = [];

        foreach ($abilities as $ability) {
            $row = $this->buildRow($ability, $scopeFilter, $targets);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, string> $targets
     * @return list<string>
     */
    public function lines(?DeployScope $scopeFilter, array $targets, ?string $typeFilter): array
    {
        return array_map(
            static fn (ShowAbilityRow $row): string => $row->formatLine(),
            $this->rows($scopeFilter, $targets, $typeFilter),
        );
    }

    /**
     * @return list<array{type: string, name: string, registryTargets: list<string>}>
     */
    private function enumerateAbilities(?string $typeFilter): array
    {
        $parsed = $this->registry->parse();
        $abilities = [];

        foreach ($parsed['skills'] as $entry) {
            $abilities[] = $this->fileAbility('skill', $entry->path, array_keys($entry->targets));
        }
        foreach ($parsed['rules'] as $entry) {
            $abilities[] = $this->fileAbility('rule', $entry->path, array_keys($entry->targets));
        }
        foreach ($parsed['agents'] as $entry) {
            $abilities[] = $this->fileAbility('agent', $entry->path, array_keys($entry->targets));
        }
        foreach ($parsed['hooks'] as $entry) {
            $abilities[] = $this->fileAbility('hook', $entry->path, array_keys($entry->targets));
        }
        foreach ($parsed['gitignore'] as $entry) {
            if (is_string($entry['marker'] ?? null)) {
                $abilities[] = $this->fileAbility('gitignore', $entry['marker'], ['project']);
            }
        }
        foreach ($parsed['prompts'] as $entry) {
            if (is_string($entry['name'] ?? null)) {
                $abilities[] = $this->fileAbility('prompt', $entry['name'], ['project']);
            }
        }

        if ($typeFilter === null) {
            return $abilities;
        }

        return array_values(array_filter(
            $abilities,
            static fn (array $a): bool => $a['type'] === $typeFilter,
        ));
    }

    /**
     * @param list<string> $registryTargets
     * @return array{type: string, name: string, registryTargets: list<string>}
     */
    private function fileAbility(string $type, string $name, array $registryTargets): array
    {
        return ['type' => $type, 'name' => $name, 'registryTargets' => $registryTargets];
    }

    /**
     * @param array{type: string, name: string, registryTargets: list<string>} $ability
     * @param array<int, string> $targets
     */
    private function buildRow(array $ability, ?DeployScope $scopeFilter, array $targets): ?ShowAbilityRow
    {
        $applicableTargets = array_values(array_intersect($targets, $ability['registryTargets']));
        if ($applicableTargets === [] && !in_array($ability['type'], ['gitignore', 'prompt'], true)) {
            return null;
        }

        $targetsText = $this->formatTargetsText($ability['registryTargets'], $targets);

        if ($scopeFilter !== null) {
            $status = $this->resolveScopeStatus($ability, $scopeFilter, $targets);

            return new ShowAbilityRow(
                type: $ability['type'],
                name: $ability['name'],
                status: $status,
                scopeLabel: '(' . $scopeFilter->value . ')',
                dualScopeWarning: false,
                targetsText: $targetsText,
            );
        }

        $userStatus = $this->resolveScopeStatus($ability, DeployScope::User, $targets);
        $projectStatus = $this->resolveScopeStatus($ability, DeployScope::Project, $targets);
        $userInstalled = $this->isInstalledStatus($userStatus);
        $projectInstalled = $this->isInstalledStatus($projectStatus);

        if ($userInstalled && $projectInstalled) {
            $status = $this->mergeStatuses($userStatus, $projectStatus);

            return new ShowAbilityRow(
                type: $ability['type'],
                name: $ability['name'],
                status: $status,
                scopeLabel: '(user+project)',
                dualScopeWarning: true,
                targetsText: $targetsText,
            );
        }

        if ($userInstalled) {
            return new ShowAbilityRow(
                type: $ability['type'],
                name: $ability['name'],
                status: $userStatus,
                scopeLabel: '(user)',
                dualScopeWarning: false,
                targetsText: $targetsText,
            );
        }

        if ($projectInstalled) {
            return new ShowAbilityRow(
                type: $ability['type'],
                name: $ability['name'],
                status: $projectStatus,
                scopeLabel: '(project)',
                dualScopeWarning: false,
                targetsText: $targetsText,
            );
        }

        return new ShowAbilityRow(
            type: $ability['type'],
            name: $ability['name'],
            status: 'not installed',
            scopeLabel: '',
            dualScopeWarning: false,
            targetsText: $targetsText,
        );
    }

    /**
     * @param array{type: string, name: string, registryTargets: list<string>} $ability
     * @param array<int, string> $targets
     */
    private function resolveScopeStatus(array $ability, DeployScope $scope, array $targets): string
    {
        if ($ability['type'] === 'gitignore') {
            return $this->isGitignorePresent($ability['name'], $scope) ? 'installed' : 'not installed';
        }

        if ($ability['type'] === 'prompt') {
            return $scope === DeployScope::Project ? 'not installed' : 'not installed';
        }

        $checkTargets = array_values(array_intersect($targets, $ability['registryTargets']));
        if ($checkTargets === []) {
            return 'not installed';
        }

        $items = $this->itemsForCheck($ability['type'], $ability['name']);
        $results = $this->checkService->checkTypedForScope($items, $checkTargets, $scope);
        $relevant = array_values(array_filter(
            $results,
            fn (array $r): bool => $r['type'] === $ability['type'] && $r['name'] === $ability['name'],
        ));

        return $this->aggregateCheckResults($relevant, $ability['type'], $ability['name'], $checkTargets, $scope);
    }

    /**
     * @param array<int, array{type: string, name: string, target: string, status: string}> $results
     * @param array<int, string> $checkTargets
     */
    private function aggregateCheckResults(
        array $results,
        string $type,
        string $name,
        array $checkTargets,
        DeployScope $scope,
    ): string {
        $hasModified = false;
        $hasInstalled = false;

        foreach ($results as $result) {
            $mapped = $this->mapCheckStatus($result['status'], $type, $name, $result['target'], $scope);
            if ($mapped === 'installed with local change') {
                $hasModified = true;
            }
            if ($mapped === 'installed' || $mapped === 'installed with local change') {
                $hasInstalled = true;
            }
        }

        if ($hasModified) {
            return 'installed with local change';
        }

        if ($hasInstalled) {
            return 'installed';
        }

        foreach ($checkTargets as $target) {
            if ($this->probe->isPresent($type, $name, $target, $scope)) {
                return 'installed';
            }
        }

        return 'not installed';
    }

    private function mapCheckStatus(
        string $internalStatus,
        string $type,
        string $name,
        string $target,
        DeployScope $scope,
    ): string {
        return match ($internalStatus) {
            'unchanged' => 'installed',
            'modified' => 'installed with local change',
            'missing' => 'not installed',
            'unknown' => $this->probe->isPresent($type, $name, $target, $scope) ? 'installed' : 'not installed',
            default => 'not installed',
        };
    }

    private function isGitignorePresent(string $marker, DeployScope $scope): bool
    {
        if ($scope !== DeployScope::Project) {
            return false;
        }

        $templatePath = $this->packageRoot . '/.gitignore';
        $rendered = $this->gitIgnore->renderManagedBlock($templatePath, [$marker], ['cursor', 'kiro']);
        if (trim($rendered) === '') {
            return false;
        }

        $gitignorePath = getcwd() . '/.gitignore';
        if (!is_file($gitignorePath)) {
            return false;
        }

        $content = (string) file_get_contents($gitignorePath);
        if (!str_contains($content, '# BEGIN apm-managed-gitignore v1')) {
            return false;
        }

        foreach (explode("\n", $rendered) as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '' && !str_contains($content, $trimmed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{skills: list<string>, rules: list<string>, agents: list<string>, hooks: list<string>}
     */
    private function itemsForCheck(string $type, string $name): array
    {
        return match ($type) {
            'skill' => ['skills' => [$name], 'rules' => [], 'agents' => [], 'hooks' => []],
            'rule' => ['skills' => [], 'rules' => [$name], 'agents' => [], 'hooks' => []],
            'agent' => ['skills' => [], 'rules' => [], 'agents' => [$name], 'hooks' => []],
            'hook' => ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => [$name]],
            default => ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => []],
        };
    }

    /**
     * @param list<string> $registryTargets
     * @param array<int, string> $requestedTargets
     */
    private function formatTargetsText(array $registryTargets, array $requestedTargets): string
    {
        $shown = array_values(array_intersect($registryTargets, $requestedTargets));
        if ($shown === []) {
            if (in_array('project', $registryTargets, true) && $registryTargets === ['project']) {
                return 'project';
            }

            return implode(', ', $registryTargets);
        }

        return implode(', ', $shown);
    }

    private function isInstalledStatus(string $status): bool
    {
        return $status === 'installed' || $status === 'installed with local change';
    }

    private function mergeStatuses(string $userStatus, string $projectStatus): string
    {
        if ($userStatus === 'installed with local change' || $projectStatus === 'installed with local change') {
            return 'installed with local change';
        }

        return 'installed';
    }
}
