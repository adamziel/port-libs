<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocUpstreamRunnerDependencyAudit;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/pandoc-runner-audit-' . bin2hex(random_bytes(6));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary audit directory');
    }

    return realpath($path) ?: $path;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }

    rmdir($path);
};

$writeFile = static function (string $root, string $relativePath, string $contents = "fixture\n"): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create fixture directory');
    }

    file_put_contents($path, $contents);
};

$writePandocRunnerSource = static function (string $root, bool $withStablePlan = true) use ($writeFile): void {
    foreach (PandocUpstreamRunnerDependencyAudit::requiredSourceFiles() as $relativePath) {
        $writeFile($root, $relativePath);
    }

    if ($withStablePlan) {
        $writeFile($root, 'cabal.project', "packages: . pandoc-lua-engine\n");
        $writeFile($root, 'cabal.project.freeze', "constraints: fixture ==1.0\n");
    }
};

return [
    'blocks upstream runner dependency closure when no local cabal source truth exists' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            mkdir($root . '/lane', 0777, true);
            mkdir($root . '/cache', 0777, true);

            $audit = PandocUpstreamRunnerDependencyAudit::fromLaneAndCacheRoots(
                $root . '/lane',
                $root . '/cache',
                ['ghc' => '9.10.3', 'cabal' => '3.12.1.0', 'stack' => null]
            );

            $t->same(PandocUpstreamRunnerDependencyAudit::STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE, $audit->status());
            $t->same(null, $audit->completeSourceRoot());
            $t->same(false, $audit->hasHydratedCheckout());
            $t->same(PandocUpstreamRunnerDependencyAudit::requiredSourceFiles(), $audit->missingSourceFiles());
            $t->same([], $audit->missingRequiredTools());
            $t->same([], $audit->stablePlanFiles());
            $t->contains('hydrate Pandoc upstream checkout', implode("\n", $audit->activationGate()));
            $t->contains('no single local checkout', $audit->summary());
        } finally {
            $removeTree($root);
        }
    },

    'blocks partial upstream runner checkout without project package manifests' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile): void {
        $root = $makeTempDir();
        try {
            $checkout = $root . '/cache/pandoc';
            mkdir($checkout, 0777, true);
            foreach ([
                'pandoc.cabal',
                'pandoc-lua-engine/pandoc-lua-engine.cabal',
                'test/test-pandoc.hs',
                'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
            ] as $relativePath) {
                $writeFile($checkout, $relativePath);
            }

            $audit = PandocUpstreamRunnerDependencyAudit::fromLaneAndCacheRoots(
                $root . '/lane',
                $root . '/cache',
                ['ghc' => '9.10.3', 'cabal' => '3.12.1.0']
            );

            $t->same(PandocUpstreamRunnerDependencyAudit::STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE, $audit->status());
            $t->same(null, $audit->completeSourceRoot());
            $t->same(false, $audit->hasHydratedCheckout());
            $t->same([
                'cabal.project',
                'pandoc-server/pandoc-server.cabal',
                'pandoc-cli/pandoc-cli.cabal',
            ], $audit->missingSourceFiles());
            $t->same([], $audit->missingRequiredTools());
            $t->contains('Pandoc package manifests', implode("\n", $audit->activationGate()));
            $t->contains('required Cabal files and Tasty test entrypoints', $audit->summary());
        } finally {
            $removeTree($root);
        }
    },

    'blocks empty source placeholders and ignores empty stable plan files in lightweight cache gate' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writePandocRunnerSource): void {
        $root = $makeTempDir();
        try {
            $checkout = $root . '/cache/pandoc';
            mkdir($checkout, 0777, true);
            $writePandocRunnerSource($checkout);
            $writeFile($checkout, 'pandoc.cabal', '');
            $writeFile($checkout, 'cabal.project.freeze', '');

            $blockedAudit = PandocUpstreamRunnerDependencyAudit::fromLaneAndCacheRoots(
                $root . '/lane',
                $root . '/cache',
                ['ghc' => '9.10.3', 'cabal' => '3.12.1.0']
            );

            $t->same(PandocUpstreamRunnerDependencyAudit::STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE, $blockedAudit->status());
            $t->same(null, $blockedAudit->completeSourceRoot());
            $t->same(false, $blockedAudit->hasHydratedCheckout());
            $t->same(['pandoc.cabal'], $blockedAudit->missingSourceFiles());
            $t->same([], $blockedAudit->stablePlanFiles());
            $t->contains('no single local checkout', $blockedAudit->summary());

            $writeFile($checkout, 'pandoc.cabal', "name: pandoc\n");

            $readyAudit = PandocUpstreamRunnerDependencyAudit::fromLaneAndCacheRoots(
                $root . '/lane',
                $root . '/cache',
                ['ghc' => '9.10.3', 'cabal' => '3.12.1.0']
            );

            $t->same(PandocUpstreamRunnerDependencyAudit::STATUS_READY_FOR_DEPENDENCY_PLAN, $readyAudit->status());
            $t->same($checkout, $readyAudit->completeSourceRoot());
            $t->same(['cabal.project'], $readyAudit->stablePlanFiles());
            $t->contains('cabal.project.freeze', implode("\n", $readyAudit->activationGate()));
        } finally {
            $removeTree($root);
        }
    },

    'requires ghc and cabal before claiming a dependency-plan-ready checkout' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePandocRunnerSource): void {
        $root = $makeTempDir();
        try {
            $checkout = $root . '/cache/pandoc';
            mkdir($checkout, 0777, true);
            $writePandocRunnerSource($checkout);

            $audit = PandocUpstreamRunnerDependencyAudit::fromLaneAndCacheRoots(
                $root . '/lane',
                $root . '/cache',
                ['ghc' => '9.10.3', 'cabal' => null]
            );

            $t->same(PandocUpstreamRunnerDependencyAudit::STATUS_BLOCKED_MISSING_HASKELL_TOOLS, $audit->status());
            $t->same($checkout, $audit->completeSourceRoot());
            $t->same(true, $audit->hasHydratedCheckout());
            $t->same([], $audit->missingSourceFiles());
            $t->same(['cabal'], $audit->missingRequiredTools());
            $t->same(['cabal.project', 'cabal.project.freeze'], $audit->stablePlanFiles());
            $t->contains('install or expose required Haskell tools: cabal', implode("\n", $audit->activationGate()));
            $t->contains('required Haskell tools are missing: cabal', $audit->summary());
        } finally {
            $removeTree($root);
        }
    },

    'marks a hydrated checkout ready only for a non-mutating cabal dependency plan' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePandocRunnerSource): void {
        $root = $makeTempDir();
        try {
            $checkout = $root . '/custom-pandoc-upstream';
            mkdir($checkout, 0777, true);
            $writePandocRunnerSource($checkout);

            $audit = PandocUpstreamRunnerDependencyAudit::fromCandidateRoots(
                [$root . '/missing', $checkout],
                ['ghc' => '9.10.3', 'cabal' => '3.12.1.0', 'stack' => null]
            );

            $t->same(PandocUpstreamRunnerDependencyAudit::STATUS_READY_FOR_DEPENDENCY_PLAN, $audit->status());
            $t->same($checkout, $audit->completeSourceRoot());
            $t->same(true, $audit->hasHydratedCheckout());
            $t->same([], $audit->missingSourceFiles());
            $t->same([], $audit->missingRequiredTools());
            $t->same(['cabal.project', 'cabal.project.freeze'], $audit->stablePlanFiles());
            $t->contains('non-mutating Cabal dependency plan', implode("\n", $audit->activationGate()));
            $t->contains('separate explicitly authorized runner slice', implode("\n", $audit->activationGate()));
            $t->contains('not runner execution', $audit->summary());
        } finally {
            $removeTree($root);
        }
    },

    'records unpinned-plan risk when cabal project freeze is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePandocRunnerSource): void {
        $root = $makeTempDir();
        try {
            $checkout = $root . '/cache/pandoc';
            mkdir($checkout, 0777, true);
            $writePandocRunnerSource($checkout, false);

            $audit = PandocUpstreamRunnerDependencyAudit::fromLaneAndCacheRoots(
                $root . '/lane',
                $root . '/cache',
                ['ghc' => '9.10.3', 'cabal' => '3.12.1.0']
            );

            $t->same(PandocUpstreamRunnerDependencyAudit::STATUS_READY_FOR_DEPENDENCY_PLAN, $audit->status());
            $t->same($checkout, $audit->completeSourceRoot());
            $t->same(['cabal.project'], $audit->stablePlanFiles());
            $t->contains('cabal.project.freeze', implode("\n", $audit->activationGate()));
            $t->contains('non-mutating Cabal dependency plan', $audit->summary());
        } finally {
            $removeTree($root);
        }
    },
];
