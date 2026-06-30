#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\DocxUpstreamRunnerPlan;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-docx-upstream-runner-plan.php [options]

Options:
  --json                Emit JSON instead of text.
  --repo-root PATH      Repository root. Defaults to the parent of tools/.
  --upstream-root PATH  Hydrated Pandoc upstream checkout root, relative to repo
                        root unless absolute. Defaults to .upstream-cache/pandoc-current.
  --help                Show this help.

The plan is evidence-only. It checks the DOCX-specific upstream runner source
shape and emits exact future Cabal commands plus artifact paths. It does not run
Cabal, execute upstream tests, read DOCX package bytes, or claim DOCX parity.
TEXT;
};

try {
    $repoRoot = dirname(__DIR__);
    $upstreamRoot = DocxUpstreamRunnerPlan::DEFAULT_RELATIVE_UPSTREAM_ROOT;
    $json = false;
    $args = array_slice($argv, 1);

    for ($i = 0, $count = count($args); $i < $count; ++$i) {
        $arg = $args[$i];
        $nextValue = static function (string $name) use ($args, &$i, $count): string {
            if ($i + 1 >= $count) {
                throw new InvalidArgumentException("Missing value for {$name}");
            }

            ++$i;

            return $args[$i];
        };

        if ($arg === '--help' || $arg === '-h') {
            fwrite(STDOUT, $usage() . PHP_EOL);
            exit(0);
        }
        if ($arg === '--json') {
            $json = true;
            continue;
        }
        if ($arg === '--repo-root') {
            $repoRoot = $nextValue('--repo-root');
            continue;
        }
        if (str_starts_with($arg, '--repo-root=')) {
            $repoRoot = substr($arg, strlen('--repo-root='));
            continue;
        }
        if ($arg === '--upstream-root') {
            $upstreamRoot = $nextValue('--upstream-root');
            continue;
        }
        if (str_starts_with($arg, '--upstream-root=')) {
            $upstreamRoot = substr($arg, strlen('--upstream-root='));
            continue;
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    $report = (new DocxUpstreamRunnerPlan($repoRoot, $upstreamRoot))->report();

    if ($json) {
        fwrite(STDOUT, json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . PHP_EOL);
    } else {
        fwrite(STDOUT, DocxUpstreamRunnerPlan::formatTextReport($report));
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-docx-upstream-runner-plan: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-docx-upstream-runner-plan: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
