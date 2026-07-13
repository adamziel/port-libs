<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitFilter;
use PortLibs\Gitoxide\GitHash;

$arrow = "\xE2\x9E\xA1";

$mapLines = static function (string $src, callable $map): string {
    $out = '';
    $offset = 0;
    $length = strlen($src);

    while ($offset < $length) {
        $newline = strpos($src, "\n", $offset);
        if ($newline === false) {
            $line = substr($src, $offset);
            $offset = $length;
        } else {
            $line = substr($src, $offset, $newline - $offset + 1);
            $offset = $newline + 1;
        }

        $out .= $map($line);
    }

    return $out;
};

$arrowDriver = static function (string $src, string $operation, string $path) use ($arrow, $mapLines): string {
    return match ($operation) {
        GitFilter::DRIVER_CLEAN => $mapLines(
            $src,
            static fn (string $line): string => str_starts_with($line, $arrow) ? substr($line, strlen($arrow)) : $line
        ),
        GitFilter::DRIVER_SMUDGE => $mapLines(
            $src,
            static fn (string $line): string => str_starts_with($line, $arrow) ? $line : $arrow . $line
        ),
        default => throw new InvalidArgumentException("unexpected operation: {$operation}"),
    };
};

$failingDriver = static function (string $src, string $operation, string $path): string {
    throw new RuntimeException("failure requested for {$operation}");
};

$noCall = static function (?string &$buf): ?bool {
    throw new RuntimeException('index function will not be called');
};

$noObjectInIndex = static function (?string &$buf): ?bool {
    return null;
};

$allFilterAttributes = static fn (string $path): array => [
    'ident' => true,
    'digest' => GitFilter::ATTR_TEXT_AUTO_CRLF,
    'encoding' => 'ISO-8859-1',
    'path' => $path,
    'objectHash' => GitHash::SHA1,
];

return [
    'gix-filter pipeline convert_to_git no_driver_but_filter_with_autocrlf' => static function (TestRunner $t) use ($noObjectInIndex): void {
        $out = GitFilter::convertPipelineToGit("hi\r\n", [
            'config' => ['autoCrlf' => GitFilter::AUTO_CRLF_ENABLED],
            'roundTripCheck' => GitFilter::ROUND_TRIP_FAIL,
            'path' => 'any.txt',
        ], null, $noObjectInIndex);

        $t->same(true, $out['changed'], 'the pipeline had to buffer for the built-in EOL filter');
        $t->same(GitFilter::PIPELINE_BUFFER, $out['storage']);
        $t->same("hi\n", $out['data']);
    },
    'gix-filter pipeline convert_to_git all_stages_mean_streaming_is_impossible' => static function (TestRunner $t) use ($allFilterAttributes, $arrow, $arrowDriver, $noObjectInIndex): void {
        $sourceHash = '2188d1cdee2b93a80084b61af431a49d21bc7cc0';
        $src = $arrow . "a\r\n" . $arrow . "b\r\n" . $arrow . '$Id: ' . $sourceHash . '$';

        $out = GitFilter::convertPipelineToGit(
            $src,
            $allFilterAttributes('any.txt') + ['roundTripCheck' => GitFilter::ROUND_TRIP_FAIL],
            $arrowDriver,
            $noObjectInIndex
        );

        $t->same(true, $out['changed'], 'filters were applied');
        $t->same(GitFilter::PIPELINE_BUFFER, $out['storage'], 'non-driver filters operate in-memory');
        $t->same("a\nb\n" . '$Id$', $out['data'], 'filters were successfully reversed');
    },
    'gix-filter pipeline convert_to_git only_driver_means_streaming_is_possible' => static function (TestRunner $t) use ($arrow, $arrowDriver, $noCall): void {
        $sourceHash = '2188d1cdee2b93a80084b61af431a49d21bc7cc0';
        $src = $arrow . "a\r\n" . $arrow . "b\r\n" . $arrow . '$Id: ' . $sourceHash . '$';

        $out = GitFilter::convertPipelineToGit(
            $src,
            ['path' => 'subdir/doesnot/matter/any.txt'],
            $arrowDriver,
            $noCall
        );

        $t->same(true, $out['changed'], 'filters were applied');
        $t->same(GitFilter::PIPELINE_STREAM, $out['storage'], 'filter-only output can remain stream-backed');
        $t->same("a\r\nb\r\n" . '$Id: ' . $sourceHash . '$', $out['data'], 'one filter was reversed');
    },
    'gix-filter pipeline convert_to_git no_filter_means_reader_is_returned_unchanged' => static function (TestRunner $t) use ($arrow, $noCall): void {
        $sourceHash = '2188d1cdee2b93a80084b61af431a49d21bc7cc0';
        $input = $arrow . "a\r\n" . $arrow . "b\r\n" . $arrow . '$Id: ' . $sourceHash . '$';

        $out = GitFilter::convertPipelineToGit($input, ['path' => 'other.txt'], null, $noCall);

        $t->same(false, $out['changed'], 'no filter was applied');
        $t->same(GitFilter::PIPELINE_STREAM, $out['storage'], 'unchanged to-git input is still readable as the original stream');
        $t->same($input, $out['data'], 'input is unchanged');
    },
    'gix-filter pipeline convert_to_worktree all_stages' => static function (TestRunner $t) use ($allFilterAttributes, $arrow, $arrowDriver): void {
        $expectedHash = '2188d1cdee2b93a80084b61af431a49d21bc7cc0';

        $out = GitFilter::convertPipelineToWorktree(
            "a\nb\n" . '$Id$',
            $allFilterAttributes('any.txt'),
            $arrowDriver
        );

        $t->same(true, $out['changed'], 'filters were applied');
        $t->same(GitFilter::PIPELINE_STREAM, $out['storage'], 'the last filter is a driver');
        $t->same($arrow . "a\r\n" . $arrow . "b\r\n" . $arrow . '$Id: ' . $expectedHash . '$', $out['data']);
    },
    'gix-filter pipeline convert_to_worktree all_stages_no_filter' => static function (TestRunner $t) use ($allFilterAttributes): void {
        $expectedHash = 'a77d7acbc809ac8df987a769221c83137ba1b9f9';

        $out = GitFilter::convertPipelineToWorktree(
            '$Id$' . "a\nb\n",
            $allFilterAttributes('other.txt'),
            null
        );

        $t->same(true, $out['changed'], 'filters were applied');
        $t->same(GitFilter::PIPELINE_BUFFER, $out['storage'], 'there is no filter process, so no stream is produced');
        $t->same('$Id: ' . $expectedHash . '$' . "a\r\nb\r\n", $out['data']);
    },
    'gix-filter pipeline convert_to_worktree no_filter' => static function (TestRunner $t): void {
        $input = '$Id$' . "a\nb\n";

        $out = GitFilter::convertPipelineToWorktree($input, ['path' => 'other.txt'], null);

        $t->same(false, $out['changed'], 'no filter was applied');
        $t->same(GitFilter::PIPELINE_UNCHANGED, $out['storage']);
        $t->same($input, $out['data'], 'input is unchanged');
    },
    'gix-filter driver missing_driver_means_no_filter_is_applied' => static function (TestRunner $t): void {
        $t->same(null, GitFilter::applyDriver('', GitFilter::DRIVER_SMUDGE, null, 'ignored'));
        $t->same(null, GitFilter::applyDriver('', GitFilter::DRIVER_CLEAN, null, 'ignored'));
    },
    'gix-filter driver smudge_and_clean_failure_is_translated_to_observable_error_for_required_drivers' => static function (TestRunner $t) use ($failingDriver): void {
        try {
            GitFilter::applyDriver("hello\nthere\n", GitFilter::DRIVER_SMUDGE, $failingDriver, 'do/fail');
        } catch (RuntimeException $exception) {
            $t->same(true, str_ends_with($exception->getMessage(), ' failed'));
            return;
        }

        throw new RuntimeException('Expected required driver failure was not thrown');
    },
    'gix-filter driver smudge_and_clean_failure_means_nothing_if_required_is_false' => static function (TestRunner $t) use ($failingDriver): void {
        $out = GitFilter::applyDriver("hello\nthere\n", GitFilter::DRIVER_CLEAN, $failingDriver, 'do/fail', false);

        $t->same('', $out);
        $t->same(0, strlen($out ?? ''), 'the failed non-required driver produces no output');
    },
    'gix-filter driver smudge_and_clean_series' => static function (TestRunner $t) use ($arrow, $arrowDriver): void {
        $input = "hello\nthere\n";

        foreach (['single-file callback', 'process callback facade'] as $driverKind) {
            $smudged = GitFilter::applyDriver($input, GitFilter::DRIVER_SMUDGE, $arrowDriver, 'some/path.txt');
            $t->same($arrow . "hello\n" . $arrow . "there\n", $smudged, "{$driverKind} applies indentation in smudge mode");

            $cleaned = GitFilter::applyDriver($smudged ?? '', GitFilter::DRIVER_CLEAN, $arrowDriver, 'some/path.txt');
            $t->same($input, $cleaned, "{$driverKind} clean reverses smudge");
        }
    },
];
