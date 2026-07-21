<?php

declare(strict_types=1);

$repositoryRoot = dirname(__DIR__, 3);
$packageRoot = dirname(__DIR__);

return [
    'keeps canonical pandoc package documentation discoverable' => static function (TestRunner $t) use (
        $repositoryRoot,
        $packageRoot
    ): void {
        $rootReadme = file_get_contents($repositoryRoot . '/README.md');
        $packageReadme = file_get_contents($packageRoot . '/README.md');
        $architecture = file_get_contents($packageRoot . '/ARCHITECTURE.md');
        $decisions = file_get_contents($packageRoot . '/DESIGN_DECISIONS.md');

        $t->true(is_string($rootReadme) && $rootReadme !== '');
        $t->true(is_string($packageReadme) && $packageReadme !== '');
        $t->true(is_string($architecture) && $architecture !== '');
        $t->true(is_string($decisions) && $decisions !== '');
        $t->true(str_contains($rootReadme, '(lanes/pandoc/README.md)'));
        $t->true(str_contains($packageReadme, '(ARCHITECTURE.md)'));
        $t->true(str_contains($packageReadme, '(DESIGN_DECISIONS.md)'));
        $t->true(str_contains($packageReadme, '(src/PandocFormatRegistry.php)'));
        $t->true(str_contains($packageReadme, "\$preflight['isValid']"));
        $t->true(!str_contains($packageReadme, "\$preflight['accepted']"));
    },
    'documents the conversion architecture and its current boundaries' => static function (TestRunner $t) use (
        $packageRoot
    ): void {
        $architecture = file_get_contents($packageRoot . '/ARCHITECTURE.md');
        if (!is_string($architecture)) {
            throw new RuntimeException('Unable to read the Pandoc architecture document.');
        }

        foreach ([
            '## System boundary',
            '## Current boundaries',
            '## Conversion pipeline',
            '## Format registry and dispatch',
            '## Shared AST',
            '## Package and archive layer',
            '## PDF semantic and visual pipeline',
            '## Media phase and ownership',
            '## Writers and WordPress blocks',
            '## Errors and diagnostics',
            '## Streaming and memory model',
            '## Test and evidence architecture',
        ] as $heading) {
            $t->true(str_contains($architecture, $heading), "Missing architecture section: {$heading}");
        }

        $normalizedArchitecture = preg_replace('/\s+/', ' ', $architecture);
        if (!is_string($normalizedArchitecture)) {
            throw new RuntimeException('Unable to normalize the Pandoc architecture document.');
        }

        foreach ([
            'readerOptions',
            'writerOptions',
            'extractMedia',
            'sourceIntegrity',
            'maxEntryCount',
            'core/html',
            'not an XSS filter',
            'not a general streaming parser',
        ] as $boundary) {
            $t->true(str_contains($normalizedArchitecture, $boundary), "Missing documented boundary: {$boundary}");
        }
    },
    'retains the complete numbered design decision register' => static function (TestRunner $t) use ($packageRoot): void {
        $decisions = file_get_contents($packageRoot . '/DESIGN_DECISIONS.md');
        if (!is_string($decisions)) {
            throw new RuntimeException('Unable to read the Pandoc design decision register.');
        }

        preg_match_all('/^## (ADR-[0-9]{3}):/m', $decisions, $matches);
        $t->same([
            'ADR-001',
            'ADR-002',
            'ADR-003',
            'ADR-004',
            'ADR-005',
            'ADR-006',
            'ADR-007',
            'ADR-008',
            'ADR-009',
            'ADR-010',
            'ADR-011',
            'ADR-012',
            'ADR-013',
        ], $matches[1] ?? []);

        foreach (['**Decision.**', '**Why.**', '**Consequences.**', '**Enforced by.**'] as $field) {
            $t->same(13, substr_count($decisions, $field), "Every ADR must retain {$field}");
        }
    },
    'keeps local links in canonical pandoc documentation valid' => static function (TestRunner $t) use ($packageRoot): void {
        foreach (['README.md', 'ARCHITECTURE.md', 'DESIGN_DECISIONS.md'] as $document) {
            $path = $packageRoot . '/' . $document;
            $markdown = file_get_contents($path);
            if (!is_string($markdown)) {
                throw new RuntimeException("Unable to read {$document}.");
            }

            preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $markdown, $matches);
            foreach ($matches[1] ?? [] as $target) {
                if (
                    str_starts_with($target, 'http://')
                    || str_starts_with($target, 'https://')
                    || str_starts_with($target, '#')
                ) {
                    continue;
                }

                $relativePath = explode('#', $target, 2)[0];
                $resolved = dirname($path) . '/' . rawurldecode($relativePath);
                $t->true(file_exists($resolved), "Broken local link in {$document}: {$target}");
            }
        }
    },
];
