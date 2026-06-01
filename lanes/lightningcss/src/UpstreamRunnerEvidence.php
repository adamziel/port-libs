<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

use InvalidArgumentException;

final class UpstreamRunnerEvidence
{
    /**
     * @return array{
     *     runner:string,
     *     label:string,
     *     command:string,
     *     status:string,
     *     exitCode:int,
     *     passed:int,
     *     failed:int,
     *     filtered:int,
     *     dirtyCache:bool,
     *     output:string
     * }
     */
    public static function rustCargoTest(
        string $label,
        string $command,
        int $exitCode,
        string $output,
        bool $dirtyCache = false
    ): array {
        $summary = self::parseCargoSummary($output);
        $status = $exitCode === 0 && $summary['failed'] === 0 && $summary['passed'] > 0 ? 'passed' : 'failed';

        return [
            'runner' => 'rust',
            'label' => $label,
            'command' => $command,
            'status' => $status,
            'exitCode' => $exitCode,
            'passed' => $summary['passed'],
            'failed' => $summary['failed'],
            'filtered' => $summary['filtered'],
            'dirtyCache' => $dirtyCache,
            'output' => self::compactOutput($output),
        ];
    }

    /**
     * @return array{
     *     runner:string,
     *     label:string,
     *     command:string,
     *     status:string,
     *     exitCode:int,
     *     expected:string,
     *     actual:string,
     *     output:string
     * }
     */
    public static function nodeNativeAddonSmoke(
        string $label,
        string $command,
        int $exitCode,
        string $output,
        string $expectedCss
    ): array {
        $actual = trim($output);

        return [
            'runner' => 'node-native',
            'label' => $label,
            'command' => $command,
            'status' => $exitCode === 0 && $actual === $expectedCss ? 'passed' : 'failed',
            'exitCode' => $exitCode,
            'expected' => $expectedCss,
            'actual' => $actual,
            'output' => self::compactOutput($output),
        ];
    }

    /**
     * @return array{
     *     runner:string,
     *     label:string,
     *     command:string,
     *     status:string,
     *     exitCode:int,
     *     dependency:string,
     *     blocker:string,
     *     output:string
     * }
     */
    public static function moduleDependencyBlocker(
        string $runner,
        string $label,
        string $command,
        int $exitCode,
        string $output
    ): array {
        $dependency = self::missingModuleName($output);
        $status = $exitCode !== 0 && $dependency !== null ? 'blocked' : 'failed';

        return [
            'runner' => $runner,
            'label' => $label,
            'command' => $command,
            'status' => $status,
            'exitCode' => $exitCode,
            'dependency' => $dependency ?? '',
            'blocker' => $dependency === null ? '' : "missing Node module {$dependency}",
            'output' => self::compactOutput($output),
        ];
    }

    /**
     * @return array{
     *     runner:string,
     *     label:string,
     *     command:string,
     *     status:string,
     *     exitCode:int,
     *     tool:string,
     *     blocker:string,
     *     output:string
     * }
     */
    public static function missingCommandBlocker(
        string $runner,
        string $label,
        string $command,
        int $exitCode,
        string $output
    ): array {
        if (!preg_match('/(?:^|\\s)([A-Za-z0-9_.-]+):\\s+command not found\\b/', $output, $matches)) {
            throw new InvalidArgumentException('Output does not contain a shell command-not-found diagnostic.');
        }

        return [
            'runner' => $runner,
            'label' => $label,
            'command' => $command,
            'status' => 'blocked',
            'exitCode' => $exitCode,
            'tool' => $matches[1],
            'blocker' => "missing command {$matches[1]}",
            'output' => self::compactOutput($output),
        ];
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return array{
     *     passed:int,
     *     blocked:int,
     *     failed:int,
     *     blockers:list<string>,
     *     passedLabels:list<string>,
     *     fullRunnerStatus:string,
     *     dependencyClosure:string
     * }
     */
    public static function summarize(array $records): array
    {
        $passed = 0;
        $blocked = 0;
        $failed = 0;
        $blockers = [];
        $passedLabels = [];

        foreach ($records as $record) {
            $status = $record['status'] ?? null;
            if ($status === 'passed') {
                $passed++;
                $passedLabels[] = (string) ($record['label'] ?? '');
                continue;
            }

            if ($status === 'blocked') {
                $blocked++;
                $blocker = (string) ($record['blocker'] ?? '');
                if ($blocker !== '') {
                    $blockers[$blocker] = true;
                }
                continue;
            }

            $failed++;
        }

        return [
            'passed' => $passed,
            'blocked' => $blocked,
            'failed' => $failed,
            'blockers' => array_keys($blockers),
            'passedLabels' => $passedLabels,
            'fullRunnerStatus' => $blocked === 0 && $failed === 0 ? 'complete' : ($passed > 0 ? 'partial' : 'blocked'),
            'dependencyClosure' => self::dependencyClosureText(array_keys($blockers)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $records
     * @param array<string, string> $externalToolPackages
     * @return array{
     *     status:string,
     *     nodePackages:list<array{name:string,section:string,version:string,blocker:string}>,
     *     externalTools:list<array{name:string,package:string,scripts:list<string>,blocker:string}>,
     *     undeclared:list<string>,
     *     activationGate:string
     * }
     */
    public static function dependencyClosurePlan(
        array $records,
        string $packageJson,
        array $externalToolPackages = []
    ): array {
        $manifest = self::packageDependencies($packageJson);
        $scripts = self::packageScripts($packageJson);
        $nodePackagesByName = [];
        $externalToolsByName = [];
        $undeclared = [];

        foreach ($records as $record) {
            if (($record['status'] ?? null) !== 'blocked') {
                continue;
            }

            $dependency = (string) ($record['dependency'] ?? '');
            if ($dependency !== '' && !isset($nodePackagesByName[$dependency])) {
                $meta = $manifest[$dependency] ?? [
                    'name' => $dependency,
                    'section' => 'undeclared',
                    'version' => '',
                ];

                $nodePackagesByName[$dependency] = [
                    'name' => $dependency,
                    'section' => $meta['section'],
                    'version' => $meta['version'],
                    'blocker' => (string) ($record['blocker'] ?? ''),
                ];

                if ($meta['section'] === 'undeclared') {
                    $undeclared[$dependency] = true;
                }
            }

            $tool = (string) ($record['tool'] ?? '');
            if ($tool !== '' && !isset($externalToolsByName[$tool])) {
                $externalToolsByName[$tool] = [
                    'name' => $tool,
                    'package' => $externalToolPackages[$tool] ?? '',
                    'scripts' => self::scriptsUsingCommand($scripts, $tool),
                    'blocker' => (string) ($record['blocker'] ?? ''),
                ];
            }
        }

        $nodePackages = array_values($nodePackagesByName);
        $externalTools = array_values($externalToolsByName);

        return [
            'status' => $nodePackages === [] && $externalTools === [] ? 'complete' : 'blocked',
            'nodePackages' => $nodePackages,
            'externalTools' => $externalTools,
            'undeclared' => array_keys($undeclared),
            'activationGate' => self::activationGateText($nodePackages, $externalTools),
        ];
    }

    /**
     * @return array{passed:int, failed:int, filtered:int}
     */
    private static function parseCargoSummary(string $output): array
    {
        if (!preg_match(
            '/test result:\\s+\\w+\\.\\s+(\\d+) passed;\\s+(\\d+) failed;\\s+\\d+ ignored;\\s+\\d+ measured;\\s+(\\d+) filtered out;/',
            $output,
            $matches
        )) {
            throw new InvalidArgumentException('Cargo test output did not include a parseable test result summary.');
        }

        return [
            'passed' => (int) $matches[1],
            'failed' => (int) $matches[2],
            'filtered' => (int) $matches[3],
        ];
    }

    private static function missingModuleName(string $output): ?string
    {
        if (preg_match("/Cannot find (?:package|module) '([^']+)'/", $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function compactOutput(string $output): string
    {
        $output = trim($output);
        $output = preg_replace('/\\s+/', ' ', $output) ?? $output;

        return $output;
    }

    /**
     * @return array<string, array{name:string,section:string,version:string}>
     */
    private static function packageDependencies(string $packageJson): array
    {
        $manifest = self::decodePackageJson($packageJson);
        $dependencies = [];

        foreach (['dependencies', 'devDependencies', 'optionalDependencies', 'peerDependencies'] as $section) {
            $values = $manifest[$section] ?? [];
            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $name => $version) {
                if (!is_string($name) || (!is_string($version) && !is_numeric($version))) {
                    continue;
                }

                $dependencies[$name] = [
                    'name' => $name,
                    'section' => $section,
                    'version' => (string) $version,
                ];
            }
        }

        return $dependencies;
    }

    /**
     * @return array<string, string>
     */
    private static function packageScripts(string $packageJson): array
    {
        $manifest = self::decodePackageJson($packageJson);
        $scripts = $manifest['scripts'] ?? [];

        if (!is_array($scripts)) {
            return [];
        }

        $result = [];
        foreach ($scripts as $name => $script) {
            if (is_string($name) && is_string($script)) {
                $result[$name] = $script;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodePackageJson(string $packageJson): array
    {
        $manifest = json_decode($packageJson, true);
        if (!is_array($manifest)) {
            throw new InvalidArgumentException('Upstream package.json could not be parsed.');
        }

        return $manifest;
    }

    /**
     * @param array<string, string> $scripts
     * @return list<string>
     */
    private static function scriptsUsingCommand(array $scripts, string $command): array
    {
        $matches = [];

        foreach ($scripts as $name => $script) {
            if (preg_match('/(?:^|[\\s;&|()])' . preg_quote($command, '/') . '(?:\\s|$)/', $script) === 1) {
                $matches[] = $name;
            }
        }

        return $matches;
    }

    /**
     * @param list<string> $blockers
     */
    private static function dependencyClosureText(array $blockers): string
    {
        if ($blockers === []) {
            return 'No new support dependency is required.';
        }

        return 'No PHP support component is required; runner closure needs upstream dependency installation for '
            . implode(', ', $blockers)
            . '.';
    }

    /**
     * @param list<array{name:string,section:string,version:string,blocker:string}> $nodePackages
     * @param list<array{name:string,package:string,scripts:list<string>,blocker:string}> $externalTools
     */
    private static function activationGateText(array $nodePackages, array $externalTools): string
    {
        if ($nodePackages === [] && $externalTools === []) {
            return 'No upstream runner dependencies are missing.';
        }

        $parts = [];

        if ($nodePackages !== []) {
            $packages = [];
            foreach ($nodePackages as $package) {
                $source = $package['section'] === 'undeclared'
                    ? 'undeclared upstream package'
                    : $package['section'];
                $version = $package['version'] === '' ? '' : ' ' . $package['version'];
                $packages[] = "{$package['name']} from {$source}{$version}";
            }
            $parts[] = 'install Node packages: ' . implode('; ', $packages);
        }

        if ($externalTools !== []) {
            $tools = [];
            foreach ($externalTools as $tool) {
                $package = $tool['package'] === '' ? 'PATH tool' : $tool['package'];
                $scripts = $tool['scripts'] === [] ? '' : ' for scripts ' . implode(', ', $tool['scripts']);
                $tools[] = "{$tool['name']} via {$package}{$scripts}";
            }
            $parts[] = 'install external tools: ' . implode('; ', $tools);
        }

        return ucfirst(implode('. Then ', $parts)) . '.';
    }
}
