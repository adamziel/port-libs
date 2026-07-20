<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocMediaExtractor;

require_once __DIR__ . '/bootstrap.php';

if (!defined('PDF_CORPUS_REPORT_LIBRARY_ONLY')) {
    ini_set('display_errors', 'stderr');
    ini_set('memory_limit', '512M');
    error_reporting(E_ALL & ~E_DEPRECATED);
    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function (): void {
            throw new RuntimeException('PDF corpus conversion timed out.');
        });
    }

$root = dirname(__DIR__);
$manifestPath = $argv[1] ?? ($root . '/tools/pdf-corpus-table-manifest.json');
$workDir = $argv[2] ?? ($root . '/.port-libs/pdf-corpus');
$artifactCacheDir = $argv[3] ?? ($root . '/.port-libs/pdf-corpus-pinned');
$configuredReviewEvidenceDir = trim((string) (getenv('PDF_CORPUS_REVIEW_EVIDENCE_DIR') ?: ''));
$reviewEvidenceDir = $argv[4] ?? ($configuredReviewEvidenceDir !== ''
    ? $configuredReviewEvidenceDir
    : ($workDir . '/review-evidence'));
$manifest = read_manifest($manifestPath);
assert_report_manifest_semantic_states($manifest);
$onlyId = trim((string) getenv('PDF_CORPUS_ONLY_ID'));
if ($onlyId !== '') {
    $manifest = array_values(array_filter(
        $manifest,
        static fn (array $entry): bool => (string) ($entry['id'] ?? '') === $onlyId
    ));
    if ($manifest === []) {
        throw new RuntimeException('PDF_CORPUS_ONLY_ID did not match a manifest entry.');
    }
}

ensure_dir($workDir);
ensure_dir($workDir . '/outputs');

$modes = [
    'geometry-on' => [
        'pdfFastTextOnly' => false,
        'pdfGeometryTables' => true,
        'pdfRepairProseText' => true,
        'pdfCollectImagePlacements' => true,
        'maxTextBytes' => PHP_INT_MAX,
    ],
    'repair-only' => [
        'pdfFastTextOnly' => false,
        'pdfGeometryTables' => false,
        'pdfRepairProseText' => true,
        'pdfCollectImagePlacements' => true,
        'maxTextBytes' => PHP_INT_MAX,
    ],
];

$records = [];
foreach ($manifest as $entry) {
    $id = (string) $entry['id'];
    $entryOutDir = $workDir . '/outputs/' . $id;
    ensure_dir($entryOutDir);

    fwrite(STDERR, "== {$id}\n");
    $artifact = resolve_pinned_artifact($entry, $root, $artifactCacheDir);
    if (($artifact['excluded'] ?? false) === true) {
        $records[] = [
            'id' => $id,
            'label' => $entry['label'] ?? $id,
            'kind' => $entry['kind'] ?? '',
            'url' => $entry['url'],
            'artifact' => $artifact,
            'download' => $artifact,
            'excluded' => true,
            'executionState' => 'excluded_license_blocked',
            'manualReview' => manual_review_not_applicable('excluded_license_blocked'),
            'semanticExpectations' => $entry['semanticExpectations'] ?? null,
            'semanticVerification' => semantic_verification_not_executed($entry, 'excluded_license_blocked'),
            'modes' => [],
        ];
        continue;
    }
    if (($artifact['ok'] ?? false) !== true) {
        $executionState = (string) ($artifact['availabilityState'] ?? 'artifact_verification_failed');
        $records[] = [
            'id' => $id,
            'label' => $entry['label'] ?? $id,
            'kind' => $entry['kind'] ?? '',
            'url' => $entry['url'],
            'artifact' => $artifact,
            'download' => $artifact,
            'executionState' => $executionState,
            'manualReview' => manual_review_pending($entry, $executionState),
            'semanticExpectations' => $entry['semanticExpectations'] ?? null,
            'semanticVerification' => semantic_verification_not_executed($entry, $executionState),
            'modes' => [],
        ];
        continue;
    }

    $bytes = file_get_contents((string) $artifact['path']);
    if (!is_string($bytes)) {
        $records[] = [
            'id' => $id,
            'label' => $entry['label'] ?? $id,
            'kind' => $entry['kind'] ?? '',
            'url' => $entry['url'],
            'artifact' => ['ok' => false, 'error' => 'Unable to read verified PDF artifact.'],
            'download' => ['ok' => false, 'error' => 'Unable to read cached PDF.'],
            'executionState' => 'artifact_read_failed',
            'manualReview' => manual_review_pending($entry, 'artifact_read_failed'),
            'semanticExpectations' => $entry['semanticExpectations'] ?? null,
            'semanticVerification' => semantic_verification_not_executed($entry, 'artifact_read_failed'),
            'modes' => [],
        ];
        continue;
    }
    if (!pdf_corpus_bytes_match_artifact_identity($bytes, $artifact)) {
        $failedArtifact = array_replace($artifact, [
            'ok' => false,
            'verified' => false,
            'availabilityState' => 'artifact_verification_failed',
            'actualBytes' => strlen($bytes),
            'actualSha256' => hash('sha256', $bytes),
            'error' => 'Verified PDF artifact changed before conversion.',
        ]);
        $records[] = [
            'id' => $id,
            'label' => $entry['label'] ?? $id,
            'kind' => $entry['kind'] ?? '',
            'url' => $entry['url'],
            'artifact' => $failedArtifact,
            'download' => $failedArtifact,
            'executionState' => 'artifact_verification_failed',
            'manualReview' => manual_review_pending($entry, 'artifact_verification_failed'),
            'semanticExpectations' => $entry['semanticExpectations'] ?? null,
            'semanticVerification' => semantic_verification_not_executed($entry, 'artifact_verification_failed'),
            'modes' => [],
        ];
        continue;
    }

    $modeRecords = [];
    foreach ($modes as $mode => $readerOptions) {
        fwrite(STDERR, "   {$mode}\n");
        $modeRecords[$mode] = convert_pdf_for_review_with_watchdog(
            $bytes,
            $entry,
            $entryOutDir,
            $mode,
            $readerOptions
        );
    }

    $allModesExecuted = count($modeRecords) === count($modes)
        && array_reduce(
            $modeRecords,
            static fn (bool $ok, array $modeRecord): bool => $ok && (($modeRecord['ok'] ?? false) === true),
            true
        );
    $executionState = match (true) {
        !$allModesExecuted => ($artifact['pinStatus'] ?? '') === 'remote-hash-pinned'
            ? 'remote_fetched_verified_execution_failed'
            : 'checked_in_verified_execution_failed',
        ($artifact['pinStatus'] ?? '') === 'remote-hash-pinned' => 'remote_fetched_verified_executed',
        default => 'checked_in_verified_executed',
    };
    $executionReport = write_pdf_corpus_execution_report(
        $id,
        $artifact,
        $modeRecords,
        $executionState,
        $entryOutDir
    );
    $manualReview = evaluate_pdf_corpus_manual_review(
        $entry,
        $artifact,
        $executionReport,
        $reviewEvidenceDir,
        $executionState
    );

    $records[] = [
        'id' => $id,
        'label' => $entry['label'] ?? $id,
        'kind' => $entry['kind'] ?? '',
        'url' => $entry['url'],
        'expectedTables' => $entry['expectedTables'] ?? null,
        'expectedPhysicalTables' => $entry['expectedPhysicalTables'] ?? null,
        'expectedLogicalInstances' => $entry['expectedLogicalInstances'] ?? null,
        'expectedLogicalFamilies' => $entry['expectedLogicalFamilies'] ?? null,
        'notes' => $entry['notes'] ?? '',
        'artifact' => $artifact,
        'executionState' => $executionState,
        'executionReport' => $executionReport,
        'manualReview' => $manualReview,
        'semanticExpectations' => $entry['semanticExpectations'] ?? null,
        // Keep the old report field for downstream readers while making its
        // value an immutable-pin verification record rather than a download.
        'download' => $artifact,
        'modes' => $modeRecords,
    ];
}

$report = [
    'generatedAt' => gmdate('c'),
    'manifest' => $manifestPath,
    'workDir' => $workDir,
    'summary' => summarize_records($records),
    'records' => $records,
];

$jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
file_put_contents($workDir . '/report.json', json_encode($report, $jsonFlags) . "\n");
file_put_contents($workDir . '/report.html', render_html_report($report));
fwrite(STDERR, "Wrote {$workDir}/report.json\n");
fwrite(STDERR, "Wrote {$workDir}/report.html\n");
$strictFailures = (int) ($report['summary']['failedArtifactVerification'] ?? 0)
    + (int) ($report['summary']['remotePinnedNotFetched'] ?? 0)
    + (int) ($report['summary']['failedConversions'] ?? 0)
    + (int) ($report['summary']['semanticExpectationFailures'] ?? 0)
    + (int) ($report['summary']['humanReviewedFailDocuments'] ?? 0)
    + (int) ($report['summary']['invalidManualReviewEvidenceDocuments'] ?? 0);
if ($strictFailures > 0 && getenv('PDF_CORPUS_ALLOW_FAILURES') !== '1') {
    fwrite(STDERR, "PDF corpus strict gate failed with {$strictFailures} unfetched, artifact, conversion, or semantic failures.\n");
    exit(1);
}
}

/**
 * @return list<array<string, mixed>>
 */
function read_manifest(string $path): array
{
    $json = file_get_contents($path);
    if (!is_string($json)) {
        throw new RuntimeException("Unable to read manifest {$path}.");
    }
    $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) {
        throw new RuntimeException("Manifest {$path} did not decode to an array.");
    }

    return array_values(array_filter($manifest, static fn ($entry): bool => is_array($entry)));
}

/** @param list<array<string,mixed>> $manifest */
function assert_report_manifest_semantic_states(array $manifest): void
{
    $categoryKeys = [
        'headings',
        'paragraphs',
        'listStarts',
        'tableHeaders',
        'tableCells',
        'spans',
        'order',
        'links',
        'pageCoverage',
        'mediaOccurrences',
        'unresolvedDispositions',
    ];
    sort($categoryKeys);
    foreach ($manifest as $entry) {
        $id = (string) ($entry['id'] ?? 'unknown');
        $semantic = is_array($entry['semanticExpectations'] ?? null) ? $entry['semanticExpectations'] : [];
        if (($semantic['schemaVersion'] ?? null) !== 1) {
            throw new RuntimeException($id . ' has no supported semantic expectation schema.');
        }
        $pinStatus = (string) ($entry['artifact']['pinStatus'] ?? '');
        $requiredStatus = match ($pinStatus) {
            'checked-in' => 'verified_baseline',
            'remote-hash-pinned' => 'pending_manual_review',
            'blocked-license-review' => 'excluded_license_blocked',
            default => 'invalid',
        };
        if (($semantic['status'] ?? null) !== $requiredStatus) {
            throw new RuntimeException($id . ' semantic expectation state does not match its artifact state.');
        }
        $review = is_array($entry['review'] ?? null) ? $entry['review'] : [];
        $requiredReview = match ($pinStatus) {
            'checked-in' => ['baseline-recorded', 'screenshots-recorded'],
            'remote-hash-pinned' => ['candidate', 'required'],
            'blocked-license-review' => ['blocked', 'blocked'],
            default => ['invalid', 'invalid'],
        };
        if (($review['status'] ?? null) !== $requiredReview[0]
            || ($review['visual'] ?? null) !== $requiredReview[1]) {
            throw new RuntimeException($id . ' review claim does not match its artifact state.');
        }
        foreach (['expected', 'forbidden'] as $side) {
            $container = is_array($semantic[$side] ?? null) ? $semantic[$side] : [];
            $keys = array_keys($container);
            sort($keys);
            if ($keys !== $categoryKeys) {
                throw new RuntimeException($id . ' semantic ' . $side . ' assertions do not use the complete schema.');
            }
        }
        if ($requiredStatus === 'verified_baseline') {
            $expectedMedia = is_array($semantic['expected']['mediaOccurrences'] ?? null)
                ? $semantic['expected']['mediaOccurrences']
                : [];
            if ((int) ($semantic['exactCounts']['mediaOccurrences'] ?? -1) !== count($expectedMedia)) {
                throw new RuntimeException($id . ' does not enumerate every expected media occurrence.');
            }
        }
    }
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create {$dir}.");
    }
}

/**
 * @return array<string, mixed>
 */
function resolve_pinned_artifact(array $entry, string $root, string $artifactCacheDir): array
{
    $id = (string) ($entry['id'] ?? 'unknown');
    $artifact = is_array($entry['artifact'] ?? null) ? $entry['artifact'] : [];
    $pinStatus = (string) ($artifact['pinStatus'] ?? '');
    if ($pinStatus === 'blocked-license-review') {
        return [
            'ok' => false,
            'pinned' => false,
            'fetched' => false,
            'excluded' => true,
            'pinStatus' => $pinStatus,
            'availabilityState' => 'excluded_license_blocked',
            'reason' => 'blocked-license-review',
        ];
    }

    $expectedBytes = (int) ($artifact['bytes'] ?? 0);
    $expectedSha256 = (string) ($artifact['sha256'] ?? '');
    if ($expectedBytes < 5 || preg_match('/^[a-f0-9]{64}$/', $expectedSha256) !== 1) {
        return [
            'ok' => false,
            'pinned' => false,
            'fetched' => false,
            'pinStatus' => $pinStatus,
            'availabilityState' => 'artifact_pin_invalid',
            'error' => $id . ' has no valid immutable artifact pin.',
        ];
    }

    if ($pinStatus === 'checked-in') {
        $path = realpath($root . '/' . ltrim((string) ($artifact['localPath'] ?? ''), '/'));
        $resolvedRoot = realpath($root);
        if (!is_string($path)
            || !is_string($resolvedRoot)
            || !str_starts_with($path, $resolvedRoot . DIRECTORY_SEPARATOR)) {
            return [
                'ok' => false,
                'pinned' => true,
                'fetched' => false,
                'pinStatus' => $pinStatus,
                'availabilityState' => 'artifact_verification_failed',
                'error' => $id . ' checked-in artifact path is missing or unsafe.',
            ];
        }
    } elseif ($pinStatus === 'remote-hash-pinned') {
        $path = rtrim($artifactCacheDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $id
            . '-'
            . substr($expectedSha256, 0, 16)
            . '.pdf';
        if (!is_file($path)) {
            return [
                'ok' => false,
                'pinned' => true,
                'fetched' => false,
                'pinStatus' => $pinStatus,
                'availabilityState' => 'remote_pinned_not_fetched',
                'expectedBytes' => $expectedBytes,
                'expectedSha256' => $expectedSha256,
                'error' => $id . ' is not in the verified artifact cache; run fetch-pinned-pdf-corpus-artifact.mjs first.',
            ];
        }
    } else {
        return [
            'ok' => false,
            'pinned' => false,
            'fetched' => false,
            'pinStatus' => $pinStatus,
            'availabilityState' => 'artifact_pin_invalid',
            'error' => $id . ' has unsupported pin status ' . $pinStatus . '.',
        ];
    }

    $bytes = file_get_contents($path);
    if (!is_string($bytes)) {
        return [
            'ok' => false,
            'pinned' => true,
            'fetched' => true,
            'pinStatus' => $pinStatus,
            'availabilityState' => 'artifact_read_failed',
            'error' => 'Unable to read pinned artifact ' . $path . '.',
        ];
    }
    $actualBytes = strlen($bytes);
    $actualSha256 = hash('sha256', $bytes);
    if ($actualBytes !== $expectedBytes || !hash_equals($expectedSha256, $actualSha256)) {
        return [
            'ok' => false,
            'pinned' => true,
            'fetched' => true,
            'pinStatus' => $pinStatus,
            'availabilityState' => 'artifact_verification_failed',
            'error' => $id . ' immutable artifact identity mismatch.',
            'expectedBytes' => $expectedBytes,
            'actualBytes' => $actualBytes,
            'expectedSha256' => $expectedSha256,
            'actualSha256' => $actualSha256,
        ];
    }

    if (!str_starts_with($bytes, '%PDF-')) {
        return [
            'ok' => false,
            'pinned' => true,
            'fetched' => true,
            'pinStatus' => $pinStatus,
            'availabilityState' => 'artifact_verification_failed',
            'error' => $id . ' pinned artifact is not a PDF.',
        ];
    }

    return [
        'ok' => true,
        'pinned' => true,
        'fetched' => true,
        'verified' => true,
        'pinStatus' => $pinStatus,
        'availabilityState' => $pinStatus === 'remote-hash-pinned'
            ? 'remote_fetched_verified'
            : 'checked_in_verified',
        'path' => $path,
        'bytes' => $actualBytes,
        'sha256' => $actualSha256,
    ];
}

/** @param array<string,mixed> $artifact */
function pdf_corpus_bytes_match_artifact_identity(string $bytes, array $artifact): bool
{
    $expectedBytes = $artifact['bytes'] ?? null;
    $expectedSha256 = $artifact['sha256'] ?? null;

    return is_int($expectedBytes)
        && is_string($expectedSha256)
        && preg_match('/^[a-f0-9]{64}$/', $expectedSha256) === 1
        && strlen($bytes) === $expectedBytes
        && hash_equals($expectedSha256, hash('sha256', $bytes));
}

/**
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $readerOptions
 * @return array<string, mixed>
 */
function convert_pdf_for_review_with_watchdog(
    string $bytes,
    array $entry,
    string $outDir,
    string $mode,
    array $readerOptions
): array {
    $timeoutSeconds = pdf_corpus_timeout_seconds();

    return run_pdf_corpus_with_watchdog(
        static fn (): array => convert_pdf_for_review($bytes, $entry, $outDir, $mode, $readerOptions),
        $timeoutSeconds,
        $mode,
        $outDir
    );
}

function pdf_corpus_timeout_seconds(): int
{
    $configured = getenv('PDF_CORPUS_TIMEOUT_SECONDS');
    if (!is_string($configured) || preg_match('/^\d+$/', trim($configured)) !== 1) {
        return 180;
    }

    return max(1, min(900, (int) trim($configured)));
}

/** @return list<string> */
function pdf_corpus_watchdog_missing_capabilities(): array
{
    $requiredFunctions = [
        'hrtime',
        'pcntl_fork',
        'pcntl_get_last_error',
        'pcntl_waitpid',
        'pcntl_wifexited',
        'pcntl_wexitstatus',
        'pcntl_wifsignaled',
        'pcntl_wtermsig',
        'posix_kill',
    ];
    $missing = [];
    foreach ($requiredFunctions as $function) {
        if (!function_exists($function)) {
            $missing[] = $function . '()';
        }
    }
    foreach (['WNOHANG', 'SIGTERM', 'SIGKILL', 'PCNTL_EINTR', 'PCNTL_ECHILD'] as $constant) {
        if (!defined($constant)) {
            $missing[] = $constant;
        }
    }

    return $missing;
}

/**
 * Run one corpus mode in a disposable worker while the parent owns the wall-clock deadline.
 * A file-backed result channel avoids blocking when a mode produces a large semantic snapshot.
 *
 * @param callable():array<string,mixed> $operation
 * @return array<string,mixed>
 */
function run_pdf_corpus_with_watchdog(
    callable $operation,
    int $timeoutSeconds,
    string $mode,
    string $resultDirectory
): array {
    $timeoutSeconds = max(1, min(900, $timeoutSeconds));
    $watchdog = [
        'enforced' => true,
        'mechanism' => 'parent-process-wall-clock',
        'mode' => $mode,
        'timeoutSeconds' => $timeoutSeconds,
        'timedOut' => false,
    ];
    $missingCapabilities = pdf_corpus_watchdog_missing_capabilities();
    if ($missingCapabilities !== []) {
        return [
            'ok' => false,
            'seconds' => 0.0,
            'error' => 'RuntimeException: PDF corpus parent watchdog is unavailable; missing '
                . implode(', ', $missingCapabilities)
                . '.',
            'watchdog' => array_replace($watchdog, ['enforced' => false]),
        ];
    }
    $startedNanoseconds = hrtime(true);
    if (!is_dir($resultDirectory) || !is_writable($resultDirectory)) {
        return [
            'ok' => false,
            'seconds' => 0.0,
            'error' => 'RuntimeException: PDF corpus parent watchdog result directory is not writable.',
            'watchdog' => $watchdog,
        ];
    }

    $resultPath = tempnam($resultDirectory, '.pdf-corpus-worker-');
    if (!is_string($resultPath) || !chmod($resultPath, 0600)) {
        if (is_string($resultPath)) {
            unlink($resultPath);
        }
        return [
            'ok' => false,
            'seconds' => 0.0,
            'error' => 'RuntimeException: Unable to create a private PDF corpus watchdog result file.',
            'watchdog' => $watchdog,
        ];
    }

    $workerPid = pcntl_fork();
    if ($workerPid === -1) {
        unlink($resultPath);
        return [
            'ok' => false,
            'seconds' => 0.0,
            'error' => 'RuntimeException: Unable to fork the PDF corpus watchdog worker.',
            'watchdog' => $watchdog,
        ];
    }
    if ($workerPid === 0) {
        $workerStarted = microtime(true);
        try {
            $result = $operation();
            if (!is_array($result)) {
                throw new UnexpectedValueException('PDF corpus watchdog operation returned a non-array result.');
            }
        } catch (Throwable $throwable) {
            $result = [
                'ok' => false,
                'seconds' => round(microtime(true) - $workerStarted, 3),
                'error' => $throwable::class . ': ' . $throwable->getMessage(),
            ];
        }

        try {
            $payload = json_encode(
                ['schemaVersion' => 1, 'result' => $result],
                JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            $payload = json_encode([
                'schemaVersion' => 1,
                'result' => [
                    'ok' => false,
                    'seconds' => round(microtime(true) - $workerStarted, 3),
                    'error' => $throwable::class . ': Unable to encode PDF corpus worker result: '
                        . $throwable->getMessage(),
                ],
            ], JSON_THROW_ON_ERROR);
        }
        $written = file_put_contents($resultPath, $payload, LOCK_EX);
        exit($written === strlen($payload) ? 0 : 74);
    }

    $status = 0;
    $deadlineNanoseconds = $startedNanoseconds + ($timeoutSeconds * 1_000_000_000);
    $workerKnownGone = false;
    try {
        while (true) {
            $waitedPid = pdf_corpus_waitpid_nonblocking($workerPid, $status);
            if ($waitedPid === $workerPid) {
                $workerKnownGone = true;
                return pdf_corpus_watchdog_worker_result($resultPath, $status, $watchdog, $startedNanoseconds);
            }
            if ($waitedPid === -1) {
                $waitError = pcntl_get_last_error();
                $termination = $waitError === PCNTL_ECHILD
                    ? ['termSent' => false, 'killSent' => false, 'reaped' => true]
                    : pdf_corpus_terminate_watchdog_worker($workerPid);
                $workerKnownGone = $termination['reaped'];

                return [
                    'ok' => false,
                    'seconds' => pdf_corpus_elapsed_seconds($startedNanoseconds),
                    'error' => 'RuntimeException: Unable to wait for the PDF corpus watchdog worker'
                        . pdf_corpus_wait_error_suffix($waitError)
                        . '.',
                    'watchdog' => $watchdog + $termination,
                ];
            }

            $remainingNanoseconds = $deadlineNanoseconds - hrtime(true);
            if ($remainingNanoseconds <= 0) {
                $termination = pdf_corpus_terminate_watchdog_worker($workerPid);
                $workerKnownGone = $termination['reaped'];
                $reapFailure = $workerKnownGone ? '' : ' The terminated worker could not be reaped.';

                return [
                    'ok' => false,
                    'timedOut' => true,
                    'seconds' => pdf_corpus_elapsed_seconds($startedNanoseconds),
                    'error' => 'RuntimeException: PDF corpus ' . $mode
                        . ' exceeded the parent wall-clock timeout of '
                        . $timeoutSeconds
                        . ' seconds.'
                        . $reapFailure,
                    'watchdog' => array_replace($watchdog, $termination, ['timedOut' => true]),
                ];
            }
            usleep((int) min(25_000, max(1_000, intdiv($remainingNanoseconds, 1_000))));
        }
    } catch (Throwable $throwable) {
        $termination = $workerKnownGone
            ? ['termSent' => false, 'killSent' => false, 'reaped' => true]
            : pdf_corpus_terminate_watchdog_worker($workerPid);
        $workerKnownGone = $termination['reaped'];

        return [
            'ok' => false,
            'seconds' => pdf_corpus_elapsed_seconds($startedNanoseconds),
            'error' => $throwable::class . ': PDF corpus parent watchdog failed: ' . $throwable->getMessage(),
            'watchdog' => $watchdog + $termination,
        ];
    } finally {
        if ($workerKnownGone && is_file($resultPath)) {
            unlink($resultPath);
        }
    }
}

/**
 * @param array<string,mixed> $watchdog
 * @return array<string,mixed>
 */
function pdf_corpus_watchdog_worker_result(
    string $resultPath,
    int $status,
    array $watchdog,
    int $startedNanoseconds
): array {
    if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
        $statusDescription = pcntl_wifsignaled($status)
            ? 'signal ' . pcntl_wtermsig($status)
            : 'exit code ' . pcntl_wexitstatus($status);

        return [
            'ok' => false,
            'seconds' => pdf_corpus_elapsed_seconds($startedNanoseconds),
            'error' => 'RuntimeException: PDF corpus watchdog worker ended with ' . $statusDescription . '.',
            'watchdog' => $watchdog + ['reaped' => true],
        ];
    }

    $payload = file_get_contents($resultPath);
    if (!is_string($payload) || $payload === '') {
        return [
            'ok' => false,
            'seconds' => pdf_corpus_elapsed_seconds($startedNanoseconds),
            'error' => 'RuntimeException: PDF corpus watchdog worker produced no result payload.',
            'watchdog' => $watchdog + ['reaped' => true],
        ];
    }
    try {
        $envelope = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $throwable) {
        return [
            'ok' => false,
            'seconds' => pdf_corpus_elapsed_seconds($startedNanoseconds),
            'error' => $throwable::class . ': Invalid PDF corpus watchdog worker result: '
                . $throwable->getMessage(),
            'watchdog' => $watchdog + ['reaped' => true],
        ];
    }
    if (!is_array($envelope)
        || ($envelope['schemaVersion'] ?? null) !== 1
        || !is_array($envelope['result'] ?? null)) {
        return [
            'ok' => false,
            'seconds' => pdf_corpus_elapsed_seconds($startedNanoseconds),
            'error' => 'RuntimeException: PDF corpus watchdog worker result envelope was invalid.',
            'watchdog' => $watchdog + ['reaped' => true],
        ];
    }
    $result = $envelope['result'];
    $result['watchdog'] = $watchdog + ['reaped' => true];

    return $result;
}

/** @return array{termSent:bool,killSent:bool,reaped:bool} */
function pdf_corpus_terminate_watchdog_worker(int $workerPid): array
{
    $status = 0;
    $waitedPid = pdf_corpus_waitpid_nonblocking($workerPid, $status);
    if ($waitedPid === $workerPid) {
        return ['termSent' => false, 'killSent' => false, 'reaped' => true];
    }
    if ($waitedPid === -1) {
        if (pcntl_get_last_error() === PCNTL_ECHILD) {
            return ['termSent' => false, 'killSent' => false, 'reaped' => true];
        }
    }

    $termSent = posix_kill($workerPid, SIGTERM);
    if (pdf_corpus_reap_watchdog_worker_until($workerPid, hrtime(true) + 250_000_000)) {
        return ['termSent' => $termSent, 'killSent' => false, 'reaped' => true];
    }

    $killSent = posix_kill($workerPid, SIGKILL);
    $reaped = pdf_corpus_reap_watchdog_worker_until($workerPid, hrtime(true) + 1_000_000_000);

    return ['termSent' => $termSent, 'killSent' => $killSent, 'reaped' => $reaped];
}

function pdf_corpus_reap_watchdog_worker_until(int $workerPid, int $deadlineNanoseconds): bool
{
    $status = 0;
    while (true) {
        $waitedPid = pdf_corpus_waitpid_nonblocking($workerPid, $status);
        if ($waitedPid === $workerPid) {
            return true;
        }
        if ($waitedPid === -1) {
            return pcntl_get_last_error() === PCNTL_ECHILD;
        }
        if (hrtime(true) >= $deadlineNanoseconds) {
            return false;
        }
        usleep(10_000);
    }
}

function pdf_corpus_waitpid_nonblocking(int $workerPid, int &$status): int
{
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $waitedPid = pcntl_waitpid($workerPid, $status, WNOHANG);
        if ($waitedPid !== -1 || pcntl_get_last_error() !== PCNTL_EINTR) {
            return $waitedPid;
        }
    }

    return $waitedPid;
}

function pdf_corpus_wait_error_suffix(int $error): string
{
    return function_exists('pcntl_strerror') ? ' (' . pcntl_strerror($error) . ')' : '';
}

function pdf_corpus_elapsed_seconds(int $startedNanoseconds): float
{
    return round((hrtime(true) - $startedNanoseconds) / 1_000_000_000, 3);
}

/**
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $readerOptions
 * @return array<string, mixed>
 */
function convert_pdf_for_review(string $bytes, array $entry, string $outDir, string $mode, array $readerOptions): array
{
    $started = microtime(true);
    // This worker-local alarm is only defense-in-depth. The supervising parent owns the hard deadline.
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(pdf_corpus_timeout_seconds());
    }
    try {
        $document = PandocConverter::read($bytes, 'pdf', $readerOptions);
        $mediaResult = (new PandocMediaExtractor())->extract($document, $bytes, 'pdf', [
            'destination' => 'media',
            'imageMode' => 'important',
        ]);
        $document = $mediaResult['document'];
        $meta = $document->attr('meta', []);
        $sourceIntegrity = pdf_corpus_source_integrity_record(is_array($meta) ? $meta : []);
        $plainText = plain_text($document);
        $html = PandocConverter::write($document, 'html');
        $wordpress = PandocConverter::write($document, 'wordpress');
        $base = $outDir . '/' . $mode;
        write_pdf_corpus_file($base . '.plain.txt', $plainText);
        write_pdf_corpus_file($base . '.html', $html);
        write_pdf_corpus_file($base . '.wordpress.html', $wordpress);
        $nativePath = $base . '.native';
        $nativeError = null;
        $nativePreflight = pdf_corpus_native_metadata_preflight(is_array($meta) ? $meta : []);
        if (($nativePreflight['allowed'] ?? false) !== true) {
            $nativeError = 'bounded-native-metadata-preflight: '
                . (string) ($nativePreflight['reason'] ?? 'metadata-budget-exceeded')
                . '; values=' . (int) ($nativePreflight['valueCount'] ?? 0)
                . '; scalarBytes=' . (int) ($nativePreflight['scalarBytes'] ?? 0);
        } else {
            try {
                write_pdf_corpus_file($nativePath, PandocConverter::write($document, 'native'));
            } catch (Throwable $nativeThrowable) {
                $nativeError = $nativeThrowable::class . ': ' . $nativeThrowable->getMessage();
            }
        }
        if ($nativeError !== null) {
            pdf_corpus_remove_stale_optional_native($nativePath);
        }

        $outputs = [
            'plain' => relative_path($base . '.plain.txt'),
            'html' => relative_path($base . '.html'),
            'wordpress' => relative_path($base . '.wordpress.html'),
        ];
        if ($nativeError === null) {
            $outputs['native'] = relative_path($base . '.native');
        }
        $outputEvidence = [];
        foreach ($outputs as $outputKind => $outputPath) {
            $absoluteOutputPath = match ($outputKind) {
                'plain' => $base . '.plain.txt',
                'html' => $base . '.html',
                'wordpress' => $base . '.wordpress.html',
                'native' => $base . '.native',
                default => throw new RuntimeException('Unsupported PDF corpus output kind ' . $outputKind . '.'),
            };
            $outputEvidence[$outputKind] = immutable_pdf_corpus_file_identity($absoluteOutputPath, $outputPath);
        }

        $semanticSnapshot = semantic_snapshot($document);
        $semanticVerification = evaluate_semantic_expectations(
            is_array($entry['semanticExpectations'] ?? null) ? $entry['semanticExpectations'] : [],
            $semanticSnapshot,
            $mode
        );
        $mediaEntries = [];
        foreach (($mediaResult['entries'] ?? []) as $mediaEntry) {
            if (!is_array($mediaEntry)) {
                continue;
            }
            $mediaEntries[] = array_intersect_key($mediaEntry, array_flip([
                'path',
                'mediaPath',
                'mimeType',
                'byteLength',
                'sha1',
                'source',
                'canonicalSource',
            ]));
        }

        $record = [
            'ok' => true,
            'seconds' => round(microtime(true) - $started, 3),
            'outputs' => $outputs,
            'outputEvidence' => $outputEvidence,
            'nodeCounts' => node_counts($document),
            'tableCount' => count_nodes($document, 'table'),
            'listCount' => count_nodes($document, 'bullet_list') + count_nodes($document, 'ordered_list'),
            'paragraphCount' => count_nodes($document, 'paragraph'),
            'headingCount' => count_nodes($document, 'heading'),
            'codeBlockCount' => count_nodes($document, 'code_block'),
            'lineOrientedBlockCount' => count_nodes($document, 'line_block'),
            'dialogueParagraphCount' => count_dialogue_paragraphs($document),
            'singleGlyphParagraphCount' => count_single_glyph_paragraphs($document),
            'textBytes' => strlen($plainText),
            'htmlTableTags' => substr_count(strtolower($html), '<table'),
            'wordpressTableBlocks' => substr_count($wordpress, '<!-- wp:table'),
            'semanticSnapshot' => $semanticSnapshot,
            'semanticVerification' => $semanticVerification,
            'sourceIntegrity' => $sourceIntegrity,
            'mediaEntries' => $mediaEntries,
            'mediaDiagnostics' => array_values(array_filter(
                is_array($mediaResult['diagnostics'] ?? null) ? $mediaResult['diagnostics'] : [],
                static fn (mixed $diagnostic): bool => is_string($diagnostic)
                    && str_contains($diagnostic, 'pdf-')
            )),
            'metadata' => [
                'pdfDetectedTables' => $meta['pdfDetectedTables'] ?? null,
                'pdfGeometryTables' => $meta['pdfGeometryTables'] ?? null,
                'pdfGeometryTablesEnabled' => $meta['pdfGeometryTablesEnabled'] ?? null,
                'pdfTableReconstruction' => $meta['pdfTableReconstruction'] ?? null,
                'pdfTextRepair' => $meta['pdfTextRepair'] ?? null,
                'pdfTextRepairSource' => $meta['pdfTextRepairSource'] ?? null,
                'pdfDocumentComplete' => $meta['pdfDocumentComplete'] ?? null,
                'pdfRangeComplete' => $meta['pdfRangeComplete'] ?? null,
                'pdfSemanticTextComplete' => $meta['pdfSemanticTextComplete'] ?? null,
                'pdfPageCount' => $meta['pdfPageCount'] ?? null,
                'pdfProcessedPageNumbers' => $meta['pdfProcessedPageNumbers'] ?? [],
                'pdfUnresolvedSourceOccurrences' => $meta['pdfSourceDisposition']['unresolvedOccurrenceCount'] ?? null,
                'pdfOrderedSignificantCharactersPreserved' => $meta['pdfSourceDisposition']['orderedSignificantCharactersPreserved'] ?? null,
                'pdfMediaOccurrenceComplete' => $meta['pdfMediaOccurrenceComplete'] ?? null,
                'pdfMediaOccurrenceDispositions' => $meta['pdfMediaOccurrenceDispositions'] ?? [],
                'pdfLineOrientedRegions' => $meta['pdfLineOrientedRegions'] ?? null,
                'pdfInterGlyphSpacingRepairs' => $meta['pdfInterGlyphSpacingRepairs'] ?? null,
                'pdfInferredHeadingBoundaries' => $meta['pdfInferredHeadingBoundaries'] ?? null,
                'pdfMaxPages' => $meta['pdfMaxPages'] ?? null,
                'pdfTextLines' => $meta['pdfTextLines'] ?? null,
                'pdfPositionedTextRuns' => $meta['pdfPositionedTextRuns'] ?? null,
                'pdfWarnings' => $meta['pdfWarnings'] ?? [],
            ],
            'spacingReview' => spacing_review($plainText),
            'reviewStatus' => review_status($entry, $document, $plainText, $semanticVerification),
        ];
        if ($nativeError !== null) {
            $record['nativeDumpError'] = $nativeError;
        }

        return $record;
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'seconds' => round(microtime(true) - $started, 3),
            'error' => $e::class . ': ' . $e->getMessage(),
        ];
    } finally {
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(0);
        }
    }
}

function pdf_corpus_remove_stale_optional_native(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    if (!str_ends_with($path, '.native') || !unlink($path)) {
        throw new RuntimeException('Unable to remove stale optional native artifact.');
    }
}

/**
 * Keep the immutable execution receipt bound to the same source-integrity
 * decision that production publication enforces.
 *
 * @param array<string,mixed> $meta
 * @return array<string,bool|int|null>
 */
function pdf_corpus_source_integrity_record(array $meta): array
{
    $disposition = is_array($meta['pdfSourceDisposition'] ?? null)
        ? $meta['pdfSourceDisposition']
        : [];
    $documentComplete = ($meta['pdfDocumentComplete'] ?? null) === true;
    $semanticTextComplete = ($meta['pdfSemanticTextComplete'] ?? null) === true;
    $sourceBindingComplete = ($meta['pdfSourceBindingComplete'] ?? null) === true;
    $sourceEdgeMappingComplete = ($disposition['sourceEdgeMappingComplete'] ?? null) === true;
    $orderedCharactersPreserved = ($disposition['orderedSignificantCharactersPreserved'] ?? null) === true;
    $unresolved = is_int($disposition['unresolvedOccurrenceCount'] ?? null)
        ? $disposition['unresolvedOccurrenceCount']
        : null;

    return [
        'complete' => $documentComplete
            && $semanticTextComplete
            && $sourceBindingComplete
            && $sourceEdgeMappingComplete
            && $orderedCharactersPreserved
            && $unresolved === 0,
        'pdfDocumentComplete' => $documentComplete,
        'pdfSemanticTextComplete' => $semanticTextComplete,
        'pdfSourceBindingComplete' => $sourceBindingComplete,
        'pdfSourceEdgeMappingComplete' => $sourceEdgeMappingComplete,
        'pdfOrderedSignificantCharactersPreserved' => $orderedCharactersPreserved,
        'pdfUnresolvedSourceOccurrences' => $unresolved,
    ];
}

/**
 * The native dump is optional review convenience. NativeWriter builds its
 * complete metadata rendering in memory, so preflight the metadata graph
 * without materializing another large string. Exceeding either deterministic
 * budget omits only that optional artifact; HTML, WordPress, semantic, and
 * immutable execution evidence remain mandatory.
 *
 * @param array<string,mixed> $meta
 * @return array{allowed:bool,reason:?string,valueCount:int,scalarBytes:int,maxValues:int,maxScalarBytes:int}
 */
function pdf_corpus_native_metadata_preflight(
    array $meta,
    int $maxValues = 100000,
    int $maxScalarBytes = 8388608
): array {
    $maxValues = max(1, $maxValues);
    $maxScalarBytes = max(1, $maxScalarBytes);
    $valueCount = 0;
    $scalarBytes = 0;
    $reason = null;
    $visit = function (mixed $value) use (
        &$visit,
        &$valueCount,
        &$scalarBytes,
        &$reason,
        $maxValues,
        $maxScalarBytes
    ): void {
        if ($reason !== null) {
            return;
        }
        $valueCount++;
        if ($valueCount > $maxValues) {
            $reason = 'metadata-value-limit-exceeded';
            return;
        }
        if (is_string($value)) {
            $scalarBytes += strlen($value);
        } elseif (is_int($value) || is_float($value)) {
            $scalarBytes += 32;
        } elseif (is_bool($value) || $value === null) {
            $scalarBytes += 5;
        } elseif (is_array($value)) {
            foreach ($value as $key => $child) {
                if (is_string($key)) {
                    $scalarBytes += strlen($key);
                }
                if ($scalarBytes > $maxScalarBytes) {
                    $reason = 'metadata-scalar-byte-limit-exceeded';
                    return;
                }
                $visit($child);
                if ($reason !== null) {
                    return;
                }
            }
        } elseif ($value instanceof AstNode) {
            $scalarBytes += strlen($value->type);
            $visit($value->baseAttrs());
            $visit($value->children());
        } else {
            $reason = 'metadata-value-type-unsupported';
            return;
        }
        if ($scalarBytes > $maxScalarBytes) {
            $reason = 'metadata-scalar-byte-limit-exceeded';
        }
    };
    $visit($meta);

    return [
        'allowed' => $reason === null,
        'reason' => $reason,
        'valueCount' => $valueCount,
        'scalarBytes' => $scalarBytes,
        'maxValues' => $maxValues,
        'maxScalarBytes' => $maxScalarBytes,
    ];
}

function relative_path(string $path): string
{
    return str_replace(getcwd() . '/', '', $path);
}

function write_pdf_corpus_file(string $path, string $bytes): void
{
    $written = file_put_contents($path, $bytes);
    if ($written !== strlen($bytes)) {
        throw new RuntimeException('Unable to write complete PDF corpus evidence file ' . $path . '.');
    }
}

/** @return array{path:string,bytes:int,sha256:string} */
function immutable_pdf_corpus_file_identity(string $absolutePath, ?string $recordedPath = null): array
{
    $bytes = file_get_contents($absolutePath);
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to read PDF corpus evidence file ' . $absolutePath . '.');
    }

    return [
        'path' => $recordedPath ?? relative_path($absolutePath),
        'bytes' => strlen($bytes),
        'sha256' => hash('sha256', $bytes),
    ];
}

/**
 * The receipt intentionally excludes elapsed time. Its identity changes only
 * when the verified input, conversion result, or output bytes change.
 *
 * @param array<string,mixed> $artifact
 * @param array<string,array<string,mixed>> $modeRecords
 * @return array<string,mixed>
 */
function write_pdf_corpus_execution_report(
    string $id,
    array $artifact,
    array $modeRecords,
    string $executionState,
    string $entryOutDir
): array {
    $modes = [];
    foreach ($modeRecords as $mode => $modeRecord) {
        if (($modeRecord['ok'] ?? false) !== true) {
            $modes[$mode] = [
                'ok' => false,
                'error' => (string) ($modeRecord['error'] ?? 'unknown conversion failure'),
            ];
            continue;
        }
        $semantic = is_array($modeRecord['semanticVerification'] ?? null)
            ? $modeRecord['semanticVerification']
            : [];
        $modes[$mode] = [
            'ok' => true,
            'outputs' => $modeRecord['outputEvidence'] ?? [],
            'sourceIntegrity' => is_array($modeRecord['sourceIntegrity'] ?? null)
                ? $modeRecord['sourceIntegrity']
                : pdf_corpus_source_integrity_record([]),
            'metrics' => [
                'tableCount' => (int) ($modeRecord['tableCount'] ?? 0),
                'listCount' => (int) ($modeRecord['listCount'] ?? 0),
                'paragraphCount' => (int) ($modeRecord['paragraphCount'] ?? 0),
                'headingCount' => (int) ($modeRecord['headingCount'] ?? 0),
                'textBytes' => (int) ($modeRecord['textBytes'] ?? 0),
            ],
            'semanticVerification' => [
                'status' => (string) ($semantic['status'] ?? 'unavailable'),
                'passed' => $semantic['passed'] ?? null,
                'issues' => array_values(array_filter(
                    is_array($semantic['issues'] ?? null) ? $semantic['issues'] : [],
                    'is_string'
                )),
            ],
        ];
        if (is_string($modeRecord['nativeDumpError'] ?? null)
            && $modeRecord['nativeDumpError'] !== '') {
            $modes[$mode]['nativeDumpError'] = $modeRecord['nativeDumpError'];
        }
    }

    $receipt = [
        'schemaVersion' => 1,
        'corpusId' => $id,
        'artifact' => [
            'pinStatus' => (string) ($artifact['pinStatus'] ?? ''),
            'bytes' => (int) ($artifact['bytes'] ?? 0),
            'sha256' => (string) ($artifact['sha256'] ?? ''),
        ],
        'executionState' => $executionState,
        'modes' => $modes,
    ];
    $json = json_encode(
        $receipt,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    ) . "\n";
    $absolutePath = rtrim($entryOutDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'execution-report.json';
    write_pdf_corpus_file($absolutePath, $json);
    $identity = immutable_pdf_corpus_file_identity($absolutePath);

    return [
        'ok' => true,
        'schemaVersion' => 1,
        'path' => $identity['path'],
        'absolutePath' => $absolutePath,
        'bytes' => $identity['bytes'],
        'sha256' => $identity['sha256'],
    ];
}

/** @return array<string,mixed> */
function manual_review_not_applicable(string $reason): array
{
    return [
        'status' => 'not_applicable',
        'reviewed' => false,
        'passed' => null,
        'evidencePresent' => false,
        'issues' => [],
        'reason' => $reason,
    ];
}

/** @return list<string> */
function pdf_corpus_manual_review_requirements(): array
{
    return [
        'exact immutable PDF byte length and SHA-256',
        'exact immutable execution-report byte length and SHA-256',
        'desktop source screenshot byte length and SHA-256',
        'desktop output screenshot byte length and SHA-256',
        'mobile source screenshot byte length and SHA-256',
        'mobile output screenshot byte length and SHA-256',
        'named human reviewer, ISO-8601 timestamp, notes, and explicit pass/fail verdict',
    ];
}

/** @param array<string,mixed> $entry @return array<string,mixed> */
function manual_review_pending(array $entry, string $executionState): array
{
    $semanticStatus = (string) ($entry['semanticExpectations']['status'] ?? 'invalid');
    if ($semanticStatus !== 'pending_manual_review') {
        return manual_review_not_applicable($semanticStatus);
    }

    return [
        'status' => $executionState === 'remote_fetched_verified_executed'
            ? 'pending_manual_review'
            : 'pending_execution',
        'reviewed' => false,
        'passed' => null,
        'evidencePresent' => false,
        'issues' => ['complete-review-evidence-missing'],
        'reason' => $executionState,
        'requirements' => pdf_corpus_manual_review_requirements(),
    ];
}

/** @param list<string> $expected */
function pdf_corpus_has_exact_keys(mixed $value, array $expected): bool
{
    if (!is_array($value) || array_is_list($value)) {
        return false;
    }
    $actual = array_keys($value);
    sort($actual);
    sort($expected);

    return $actual === $expected;
}

function pdf_corpus_valid_sha256(mixed $value): bool
{
    return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
}

/** @param list<string> $issues */
function pdf_corpus_validate_identity_shape(mixed $identity, string $label, array &$issues): void
{
    if (!pdf_corpus_has_exact_keys($identity, ['path', 'bytes', 'sha256'])) {
        $issues[] = $label . '-identity-schema-invalid';
        return;
    }
    if (!is_string($identity['path']) || trim($identity['path']) === '') {
        $issues[] = $label . '-path-invalid';
    }
    if (!is_int($identity['bytes']) || $identity['bytes'] < 1) {
        $issues[] = $label . '-byte-length-invalid';
    }
    if (!pdf_corpus_valid_sha256($identity['sha256'])) {
        $issues[] = $label . '-sha256-invalid';
    }
}

function pdf_corpus_review_file_path(string $evidenceRoot, string $relativePath): ?string
{
    if ($relativePath === ''
        || str_starts_with($relativePath, '/')
        || str_contains($relativePath, "\0")) {
        return null;
    }
    $root = realpath($evidenceRoot);
    $path = realpath(rtrim($evidenceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath);
    if (!is_string($root)
        || !is_string($path)
        || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $path;
}

/** @param array<string,mixed> $identity @param list<string> $issues */
function pdf_corpus_validate_screenshot_identity(
    array $identity,
    string $label,
    string $evidenceRoot,
    array &$issues
): void {
    pdf_corpus_validate_identity_shape($identity, $label, $issues);
    if (!pdf_corpus_has_exact_keys($identity, ['path', 'bytes', 'sha256'])) {
        return;
    }
    $relativePath = (string) $identity['path'];
    if (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'png') {
        $issues[] = $label . '-must-be-png';
        return;
    }
    $path = pdf_corpus_review_file_path($evidenceRoot, $relativePath);
    if ($path === null) {
        $issues[] = $label . '-file-missing-or-unsafe';
        return;
    }
    $bytes = file_get_contents($path);
    if (!is_string($bytes)) {
        $issues[] = $label . '-file-unreadable';
        return;
    }
    if (!str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
        $issues[] = $label . '-not-a-png';
    }
    if (strlen($bytes) !== ($identity['bytes'] ?? null)) {
        $issues[] = $label . '-byte-length-mismatch';
    }
    if (!is_string($identity['sha256'] ?? null)
        || !hash_equals((string) $identity['sha256'], hash('sha256', $bytes))) {
        $issues[] = $label . '-sha256-mismatch';
    }
}

function pdf_corpus_valid_review_timestamp(mixed $value): bool
{
    if (!is_string($value)
        || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
        return false;
    }
    try {
        new DateTimeImmutable($value);
    } catch (Throwable) {
        return false;
    }

    return true;
}

/**
 * @param array<string,mixed> $expectedArtifact
 * @return list<string>
 */
function pdf_corpus_execution_report_source_integrity_issues(
    string $path,
    string $expectedCorpusId,
    array $expectedArtifact,
    string $expectedExecutionState
): array
{
    $bytes = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($bytes)) {
        return ['review-execution-report-source-integrity-unreadable'];
    }
    try {
        $receipt = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['review-execution-report-source-integrity-json-invalid'];
    }
    if (!pdf_corpus_has_exact_keys(
        $receipt,
        ['schemaVersion', 'corpusId', 'artifact', 'executionState', 'modes']
    ) || ($receipt['schemaVersion'] ?? null) !== 1) {
        return ['review-execution-report-source-integrity-schema-invalid'];
    }
    if (($receipt['corpusId'] ?? null) !== $expectedCorpusId) {
        return ['review-execution-report-source-integrity-corpus-id-mismatch'];
    }
    if (($receipt['executionState'] ?? null) !== $expectedExecutionState) {
        return ['review-execution-report-source-integrity-execution-state-mismatch'];
    }
    $receiptArtifact = $receipt['artifact'] ?? null;
    if (!pdf_corpus_has_exact_keys($receiptArtifact, ['pinStatus', 'bytes', 'sha256'])
        || ($receiptArtifact['pinStatus'] ?? null) !== ($expectedArtifact['pinStatus'] ?? null)
        || ($receiptArtifact['bytes'] ?? null) !== ($expectedArtifact['bytes'] ?? null)
        || !is_string($receiptArtifact['sha256'] ?? null)
        || !is_string($expectedArtifact['sha256'] ?? null)
        || !hash_equals($expectedArtifact['sha256'], $receiptArtifact['sha256'])) {
        return ['review-execution-report-source-integrity-artifact-mismatch'];
    }
    $modes = is_array($receipt['modes'] ?? null) ? $receipt['modes'] : [];
    $modeNames = array_keys($modes);
    sort($modeNames, SORT_STRING);
    if ($modeNames !== ['geometry-on', 'repair-only']) {
        return ['review-execution-report-source-integrity-modes-invalid'];
    }

    $issues = [];
    foreach ($modes as $mode => $modeRecord) {
        $label = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $mode)) ?? 'unknown';
        if (!is_array($modeRecord)) {
            $issues[] = 'review-execution-report-' . $label . '-record-invalid';
            continue;
        }
        $outputs = is_array($modeRecord['outputs'] ?? null) ? $modeRecord['outputs'] : [];
        $outputNames = array_keys($outputs);
        sort($outputNames, SORT_STRING);
        if ($outputNames !== ['html', 'plain', 'wordpress']
            && $outputNames !== ['html', 'native', 'plain', 'wordpress']) {
            $issues[] = 'review-execution-report-' . $label . '-outputs-invalid';
        } else {
            foreach ($outputs as $outputKind => $outputIdentity) {
                $expectedBasename = match ($outputKind) {
                    'plain' => (string) $mode . '.plain.txt',
                    'html' => (string) $mode . '.html',
                    'wordpress' => (string) $mode . '.wordpress.html',
                    'native' => (string) $mode . '.native',
                    default => '',
                };
                if (!pdf_corpus_has_exact_keys($outputIdentity, ['path', 'bytes', 'sha256'])
                    || !is_string($outputIdentity['path'] ?? null)
                    || $outputIdentity['path'] === ''
                    || basename(str_replace('\\', '/', $outputIdentity['path'])) !== $expectedBasename
                    || !is_int($outputIdentity['bytes'] ?? null)
                    || $outputIdentity['bytes'] < 0
                    || !is_string($outputIdentity['sha256'] ?? null)
                    || preg_match('/^[a-f0-9]{64}$/', $outputIdentity['sha256']) !== 1) {
                    $issues[] = 'review-execution-report-' . $label . '-output-identity-invalid';
                    break;
                }
                $actualOutputPath = dirname($path) . DIRECTORY_SEPARATOR . $expectedBasename;
                $actualOutputBytes = is_file($actualOutputPath)
                    ? file_get_contents($actualOutputPath)
                    : false;
                if (!is_string($actualOutputBytes)
                    || strlen($actualOutputBytes) !== $outputIdentity['bytes']
                    || !hash_equals($outputIdentity['sha256'], hash('sha256', $actualOutputBytes))) {
                    $issues[] = 'review-execution-report-' . $label . '-output-file-mismatch';
                    break;
                }
            }
        }
        $integrity = is_array($modeRecord['sourceIntegrity'] ?? null)
            ? $modeRecord['sourceIntegrity']
            : [];
        $unresolved = is_int($integrity['pdfUnresolvedSourceOccurrences'] ?? null)
            ? $integrity['pdfUnresolvedSourceOccurrences']
            : null;
        $complete = ($modeRecord['ok'] ?? null) === true
            && ($integrity['complete'] ?? null) === true
            && ($integrity['pdfDocumentComplete'] ?? null) === true
            && ($integrity['pdfSemanticTextComplete'] ?? null) === true
            && ($integrity['pdfSourceBindingComplete'] ?? null) === true
            && ($integrity['pdfSourceEdgeMappingComplete'] ?? null) === true
            && ($integrity['pdfOrderedSignificantCharactersPreserved'] ?? null) === true
            && $unresolved === 0;
        if (!$complete) {
            $issues[] = 'review-execution-report-' . $label . '-source-integrity-incomplete';
        }
    }

    return $issues;
}

/**
 * @param array<string,mixed> $entry
 * @param array<string,mixed> $artifact
 * @param array<string,mixed> $executionReport
 * @return array<string,mixed>
 */
function evaluate_pdf_corpus_manual_review(
    array $entry,
    array $artifact,
    array $executionReport,
    string $reviewEvidenceDir,
    string $executionState
): array {
    $semanticStatus = (string) ($entry['semanticExpectations']['status'] ?? 'invalid');
    if ($semanticStatus !== 'pending_manual_review') {
        return manual_review_not_applicable($semanticStatus);
    }
    if ($executionState !== 'remote_fetched_verified_executed') {
        return manual_review_pending($entry, $executionState);
    }

    $id = (string) ($entry['id'] ?? 'unknown');
    $evidencePath = rtrim($reviewEvidenceDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $id . '.json';
    if (!is_file($evidencePath)) {
        return manual_review_pending($entry, $executionState);
    }
    $evidenceBytes = file_get_contents($evidencePath);
    if (!is_string($evidenceBytes)) {
        $pending = manual_review_pending($entry, $executionState);
        $pending['status'] = 'invalid_review_evidence';
        $pending['evidencePresent'] = true;
        $pending['issues'] = ['review-evidence-unreadable'];
        return $pending;
    }
    try {
        $evidence = json_decode($evidenceBytes, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        $pending = manual_review_pending($entry, $executionState);
        $pending['status'] = 'invalid_review_evidence';
        $pending['evidencePresent'] = true;
        $pending['issues'] = ['review-evidence-json-invalid'];
        return $pending;
    }

    $issues = [];
    $automaticSourceIntegrityIssues = [];
    if (!pdf_corpus_has_exact_keys(
        $evidence,
        ['schemaVersion', 'corpusId', 'artifact', 'executionReport', 'screenshots', 'verdict']
    )) {
        $issues[] = 'review-evidence-schema-invalid';
    }
    if (($evidence['schemaVersion'] ?? null) !== 1) {
        $issues[] = 'review-evidence-version-invalid';
    }
    if (($evidence['corpusId'] ?? null) !== $id) {
        $issues[] = 'review-evidence-corpus-id-mismatch';
    }

    $artifactEvidence = $evidence['artifact'] ?? null;
    if (!pdf_corpus_has_exact_keys($artifactEvidence, ['bytes', 'sha256'])) {
        $issues[] = 'review-artifact-identity-schema-invalid';
    } else {
        if (($artifactEvidence['bytes'] ?? null) !== ($artifact['bytes'] ?? null)) {
            $issues[] = 'review-artifact-byte-length-mismatch';
        }
        if (!is_string($artifactEvidence['sha256'] ?? null)
            || !is_string($artifact['sha256'] ?? null)
            || !hash_equals($artifact['sha256'], $artifactEvidence['sha256'])) {
            $issues[] = 'review-artifact-sha256-mismatch';
        }
    }

    $reportEvidence = $evidence['executionReport'] ?? null;
    pdf_corpus_validate_identity_shape($reportEvidence, 'review-execution-report', $issues);
    if (pdf_corpus_has_exact_keys($reportEvidence, ['path', 'bytes', 'sha256'])) {
        foreach (['path', 'bytes', 'sha256'] as $key) {
            if (($reportEvidence[$key] ?? null) !== ($executionReport[$key] ?? null)) {
                $issues[] = 'review-execution-report-' . strtolower($key) . '-mismatch';
            }
        }
        $actualReportPath = (string) ($executionReport['absolutePath'] ?? '');
        if (!is_file($actualReportPath)) {
            $issues[] = 'review-execution-report-file-missing';
        } else {
            $actualReport = immutable_pdf_corpus_file_identity(
                $actualReportPath,
                (string) ($executionReport['path'] ?? '')
            );
            foreach (['path', 'bytes', 'sha256'] as $key) {
                if (($reportEvidence[$key] ?? null) !== ($actualReport[$key] ?? null)) {
                    $issues[] = 'review-execution-report-file-' . strtolower($key) . '-mismatch';
                }
            }
            $automaticSourceIntegrityIssues = pdf_corpus_execution_report_source_integrity_issues(
                $actualReportPath,
                $id,
                $artifact,
                $executionState
            );
        }
    }

    $screenshots = $evidence['screenshots'] ?? null;
    if (!pdf_corpus_has_exact_keys($screenshots, ['desktop', 'mobile'])) {
        $issues[] = 'review-screenshot-viewports-invalid';
    } else {
        foreach (['desktop', 'mobile'] as $viewport) {
            $pair = $screenshots[$viewport] ?? null;
            if (!pdf_corpus_has_exact_keys($pair, ['source', 'output'])) {
                $issues[] = 'review-' . $viewport . '-screenshot-pair-invalid';
                continue;
            }
            foreach (['source', 'output'] as $side) {
                $identity = $pair[$side] ?? null;
                if (!is_array($identity)) {
                    $issues[] = 'review-' . $viewport . '-' . $side . '-screenshot-identity-invalid';
                    continue;
                }
                pdf_corpus_validate_screenshot_identity(
                    $identity,
                    'review-' . $viewport . '-' . $side . '-screenshot',
                    $reviewEvidenceDir,
                    $issues
                );
            }
        }
    }

    $verdict = $evidence['verdict'] ?? null;
    if (!pdf_corpus_has_exact_keys($verdict, ['result', 'reviewer', 'reviewedAt', 'notes'])) {
        $issues[] = 'review-human-verdict-schema-invalid';
    } else {
        if (!in_array($verdict['result'] ?? null, ['pass', 'fail'], true)) {
            $issues[] = 'review-human-verdict-result-invalid';
        } elseif (($verdict['result'] ?? null) === 'pass') {
            $issues = array_merge($issues, $automaticSourceIntegrityIssues);
        }
        foreach (['reviewer', 'notes'] as $key) {
            if (!is_string($verdict[$key] ?? null) || trim((string) $verdict[$key]) === '') {
                $issues[] = 'review-human-verdict-' . strtolower($key) . '-missing';
            }
        }
        if (!pdf_corpus_valid_review_timestamp($verdict['reviewedAt'] ?? null)) {
            $issues[] = 'review-human-verdict-timestamp-invalid';
        }
    }

    $issues = array_values(array_unique($issues));
    if ($issues !== []) {
        return [
            'status' => 'invalid_review_evidence',
            'reviewed' => false,
            'passed' => null,
            'evidencePresent' => true,
            'evidenceIdentity' => [
                'path' => $evidencePath,
                'bytes' => strlen($evidenceBytes),
                'sha256' => hash('sha256', $evidenceBytes),
            ],
            'issues' => $issues,
            'reason' => 'Review evidence is incomplete, stale, malformed, or does not match exact files.',
            'requirements' => pdf_corpus_manual_review_requirements(),
        ];
    }

    $passed = ($verdict['result'] ?? null) === 'pass';
    return [
        'status' => $passed ? 'reviewed_pass' : 'reviewed_fail',
        'reviewed' => true,
        'passed' => $passed,
        'evidencePresent' => true,
        'evidenceIdentity' => [
            'path' => $evidencePath,
            'bytes' => strlen($evidenceBytes),
            'sha256' => hash('sha256', $evidenceBytes),
        ],
        'issues' => [],
        'reason' => null,
        'verdict' => $verdict,
    ];
}

/**
 * @return array<string, int>
 */
function node_counts(AstNode $node): array
{
    $counts = [];
    walk_node($node, static function (AstNode $node) use (&$counts): void {
        $counts[$node->type] = ($counts[$node->type] ?? 0) + 1;
    });
    ksort($counts);

    return $counts;
}

function count_nodes(AstNode $node, string $type): int
{
    $count = 0;
    walk_node($node, static function (AstNode $node) use ($type, &$count): void {
        if ($node->type === $type) {
            $count++;
        }
    });

    return $count;
}

function count_dialogue_paragraphs(AstNode $document): int
{
    $count = 0;
    walk_node($document, static function (AstNode $node) use (&$count): void {
        if ($node->type === 'paragraph' && $node->attr('sourceRole') === 'dialogue') {
            $count++;
        }
    });

    return $count;
}

function count_single_glyph_paragraphs(AstNode $document): int
{
    $count = 0;
    walk_node($document, static function (AstNode $node) use (&$count): void {
        if ($node->type !== 'paragraph') {
            return;
        }
        $text = preg_replace('/\s+/u', '', plain_text($node)) ?? '';
        if ($text !== '' && preg_match('/^[\p{L}\p{N}]$/u', $text) === 1) {
            $count++;
        }
    });

    return $count;
}

function walk_node(AstNode $node, callable $callback): void
{
    $callback($node);
    foreach ($node->children as $child) {
        walk_node($child, $callback);
    }
}

function plain_text(AstNode $node): string
{
    $parts = [];
    collect_plain_text($node, $parts);
    $text = preg_replace("/[ \t]+\n/", "\n", implode('', $parts)) ?? implode('', $parts);
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    return trim($text) . "\n";
}

function semantic_normalized_text(AstNode|string $value): string
{
    $text = $value instanceof AstNode ? plain_text($value) : $value;
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

    return $text;
}

function semantic_is_media_paragraph(AstNode $node): bool
{
    if ($node->type !== 'paragraph') {
        return false;
    }
    $classes = $node->attr('classes', []);

    return is_array($classes) && in_array('pandoc-pdf-image-block', $classes, true);
}

/** @return list<AstNode> */
function semantic_nodes_of_type(AstNode $document, string $type): array
{
    $nodes = [];
    walk_node($document, static function (AstNode $node) use ($type, &$nodes): void {
        if ($node->type === $type) {
            $nodes[] = $node;
        }
    });

    return $nodes;
}

/** @return list<array{tableIndex:int,cells:list<string>}> */
function semantic_table_headers(array $tables): array
{
    $headers = [];
    foreach ($tables as $tableIndex => $table) {
        foreach ($table->children as $section) {
            if (!in_array($section->type, ['table_head', 'table_body', 'table_foot'], true)) {
                continue;
            }
            foreach ($section->children as $row) {
                if ($row->type !== 'table_row') {
                    continue;
                }
                if ($section->type !== 'table_head' && $row->attr('header', false) !== true) {
                    continue;
                }
                $headers[] = [
                    'tableIndex' => $tableIndex,
                    'cells' => array_map(
                        static fn (AstNode $cell): string => semantic_normalized_text($cell),
                        array_values(array_filter(
                            $row->children,
                            static fn (AstNode $cell): bool => $cell->type === 'table_cell'
                        ))
                    ),
                ];
            }
        }
    }

    return $headers;
}

/** @return list<array{tableIndex:int,section:string,row:int,column:int,text:string,rowspan:int,colspan:int}> */
function semantic_table_cells(array $tables): array
{
    $cells = [];
    foreach ($tables as $tableIndex => $table) {
        $rowIndex = 0;
        foreach ($table->children as $section) {
            if (!in_array($section->type, ['table_head', 'table_body', 'table_foot'], true)) {
                continue;
            }
            foreach ($section->children as $row) {
                if ($row->type !== 'table_row') {
                    continue;
                }
                $columnIndex = 0;
                foreach ($row->children as $cell) {
                    if ($cell->type !== 'table_cell') {
                        continue;
                    }
                    $cells[] = [
                        'tableIndex' => $tableIndex,
                        'section' => $section->type,
                        'row' => $rowIndex,
                        'column' => $columnIndex,
                        'text' => semantic_normalized_text($cell),
                        'rowspan' => max(1, (int) ($cell->attr('rowspan', $cell->attr('rowSpan', 1)))),
                        'colspan' => max(1, (int) ($cell->attr('colspan', $cell->attr('colSpan', 1)))),
                    ];
                    $columnIndex++;
                }
                $rowIndex++;
            }
        }
    }

    return $cells;
}

function semantic_list_item_text(AstNode $item): string
{
    $parts = [];
    $visit = static function (AstNode $node) use (&$visit, &$parts): void {
        if (in_array($node->type, ['bullet_list', 'ordered_list'], true)) {
            return;
        }
        if (in_array($node->type, ['text', 'code', 'math', 'code_block'], true)) {
            $parts[] = (string) $node->attr('text', '');
            return;
        }
        if ($node->type === 'space' || $node->type === 'softbreak' || $node->type === 'linebreak') {
            $parts[] = ' ';
            return;
        }
        if ($node->children === [] && is_string($node->attr('text'))) {
            $parts[] = (string) $node->attr('text');
            return;
        }
        foreach ($node->children as $child) {
            $visit($child);
        }
    };
    foreach ($item->children as $child) {
        $visit($child);
    }

    return semantic_normalized_text(implode('', $parts));
}

function semantic_is_media_representation_link(AstNode $node): bool
{
    if ($node->type !== 'link') {
        return false;
    }
    $attributes = $node->attr('attributes', []);

    return is_array($attributes)
        && ($attributes['data-pandoc-pdf-image-original'] ?? null) === 'true';
}

/** @param list<array<string,mixed>> $order */
function collect_semantic_block_order(AstNode $node, array &$order, bool $insideList = false, bool $insideTable = false): void
{
    if ($node->type === 'table') {
        foreach (semantic_table_cells([$node]) as $cell) {
            $order[] = ['kind' => 'table_cell', 'text' => $cell['text']];
        }
        return;
    }
    if (in_array($node->type, ['bullet_list', 'ordered_list'], true)) {
        $first = null;
        foreach ($node->children as $child) {
            if ($child->type === 'list_item') {
                $first = $child;
                break;
            }
        }
        if ($first instanceof AstNode) {
            $order[] = ['kind' => 'list_start', 'text' => semantic_list_item_text($first)];
        }
        return;
    }
    if ($node->type === 'heading') {
        $order[] = ['kind' => 'heading', 'text' => semantic_normalized_text($node)];
    } elseif ($node->type === 'paragraph' && !$insideList && !$insideTable) {
        if (semantic_is_media_paragraph($node)) {
            foreach (semantic_nodes_of_type($node, 'image') as $image) {
                $attributes = $image->attr('attributes', []);
                $id = is_array($attributes) ? (string) ($attributes['data-pandoc-pdf-occurrence-id'] ?? '') : '';
                if ($id !== '') {
                    $order[] = ['kind' => 'media', 'text' => $id];
                }
            }
        } else {
            $order[] = ['kind' => 'paragraph', 'text' => semantic_normalized_text($node)];
        }
    } elseif ($node->type === 'link' && !semantic_is_media_representation_link($node)) {
        $order[] = ['kind' => 'link', 'text' => semantic_normalized_text($node)];
    }

    foreach ($node->children as $child) {
        collect_semantic_block_order(
            $child,
            $order,
            $insideList || $node->type === 'list_item',
            $insideTable || $node->type === 'table'
        );
    }
}

/** @return array<string,mixed> */
function semantic_snapshot(AstNode $document): array
{
    $meta = $document->attr('meta', []);
    $meta = is_array($meta) ? $meta : [];
    $headings = [];
    foreach (semantic_nodes_of_type($document, 'heading') as $heading) {
        $headings[] = [
            'text' => semantic_normalized_text($heading),
            'level' => max(1, min(6, (int) $heading->attr('level', 1))),
        ];
    }

    $paragraphs = [];
    $visitParagraphs = static function (AstNode $node, bool $insideList = false, bool $insideTable = false) use (&$visitParagraphs, &$paragraphs): void {
        if ($node->type === 'paragraph' && !$insideList && !$insideTable && !semantic_is_media_paragraph($node)) {
            $paragraphs[] = ['text' => semantic_normalized_text($node)];
        }
        foreach ($node->children as $child) {
            $visitParagraphs(
                $child,
                $insideList || in_array($node->type, ['bullet_list', 'ordered_list', 'list_item'], true),
                $insideTable || in_array($node->type, ['table', 'table_head', 'table_body', 'table_foot', 'table_row', 'table_cell'], true)
            );
        }
    };
    $visitParagraphs($document);

    $listStarts = [];
    foreach (array_merge(
        semantic_nodes_of_type($document, 'bullet_list'),
        semantic_nodes_of_type($document, 'ordered_list')
    ) as $list) {
        foreach ($list->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }
            $listStarts[] = [
                'text' => semantic_list_item_text($item),
                'ordered' => $list->type === 'ordered_list',
                // The first visible item is not enough to prove continuation:
                // a split ordered list whose source marker is 2 must retain
                // that ordinal instead of silently restarting at 1. Keep the
                // field on every list record so a baseline which requests it
                // fails closed when the AST loses or changes the start value.
                'start' => $list->type === 'ordered_list'
                    ? (int) $list->attr('start', 1)
                    : null,
            ];
            break;
        }
    }

    $tables = semantic_nodes_of_type($document, 'table');
    $tableCells = semantic_table_cells($tables);
    $links = [];
    foreach (semantic_nodes_of_type($document, 'link') as $link) {
        // The media extractor uses this marked link as a representation
        // fallback for an occurrence already gated by mediaOccurrences. It is
        // not a source-document hyperlink and must not inflate link semantics.
        if (semantic_is_media_representation_link($link)) {
            continue;
        }
        $links[] = [
            'text' => semantic_normalized_text($link),
            'url' => (string) $link->attr('url', ''),
        ];
    }
    $mediaOccurrences = array_values(array_filter(
        is_array($meta['pdfMediaOccurrenceDispositions'] ?? null) ? $meta['pdfMediaOccurrenceDispositions'] : [],
        static fn (mixed $item): bool => is_array($item)
    ));
    usort($mediaOccurrences, static function (array $left, array $right): int {
        return ((int) ($left['page'] ?? 0)) <=> ((int) ($right['page'] ?? 0))
            ?: ((int) ($left['paintOrder'] ?? 0)) <=> ((int) ($right['paintOrder'] ?? 0))
            ?: strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
    });

    $sourceDisposition = is_array($meta['pdfSourceDisposition'] ?? null) ? $meta['pdfSourceDisposition'] : [];
    $unresolved = [];
    foreach (($sourceDisposition['unresolvedOccurrenceSample'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $unresolved[] = [
            'domain' => 'source',
            'id' => (string) ($item['id'] ?? ''),
            'reason' => (string) ($item['reason'] ?? ''),
        ];
    }
    foreach ($mediaOccurrences as $item) {
        if (($item['disposition'] ?? '') !== 'unresolved') {
            continue;
        }
        $unresolved[] = [
            'domain' => 'media',
            'id' => (string) ($item['id'] ?? ''),
            'reason' => (string) ($item['reason'] ?? ''),
        ];
    }

    $order = [];
    collect_semantic_block_order($document, $order);

    return [
        'normalization' => 'trim-and-collapse-unicode-whitespace',
        'headings' => $headings,
        'paragraphs' => $paragraphs,
        'listStarts' => $listStarts,
        'tableHeaders' => semantic_table_headers($tables),
        'tableCells' => $tableCells,
        'spans' => array_map(
            static fn (array $cell): array => array_intersect_key($cell, array_flip(['tableIndex', 'text', 'rowspan', 'colspan'])),
            $tableCells
        ),
        'order' => $order,
        'links' => $links,
        'pageCoverage' => [
            'pageCount' => max(0, (int) ($meta['pdfPageCount'] ?? 0)),
            'processedPages' => array_values(array_map('intval', is_array($meta['pdfProcessedPageNumbers'] ?? null) ? $meta['pdfProcessedPageNumbers'] : [])),
        ],
        'mediaOccurrences' => $mediaOccurrences,
        'unresolvedDispositions' => $unresolved,
        'exactCounts' => [
            'headings' => count($headings),
            'paragraphs' => count($paragraphs),
            'listStarts' => count($listStarts),
            'tables' => count($tables),
            'links' => count($links),
            'mediaOccurrences' => count($mediaOccurrences),
            'unresolvedSourceDispositions' => max(0, (int) ($sourceDisposition['unresolvedOccurrenceCount'] ?? 0)),
            'unresolvedMediaDispositions' => count(array_filter(
                $mediaOccurrences,
                static fn (array $item): bool => ($item['disposition'] ?? '') === 'unresolved'
            )),
        ],
    ];
}

/** @param array<string,mixed> $expected @param array<string,mixed> $actual */
function semantic_record_matches(array $expected, array $actual, array $ignoredKeys = ['occurrences']): bool
{
    foreach ($expected as $key => $value) {
        if (in_array($key, $ignoredKeys, true)) {
            continue;
        }
        if (!array_key_exists($key, $actual) || $actual[$key] !== $value) {
            return false;
        }
    }

    return true;
}

/** @param list<array<string,mixed>> $records @param array<string,mixed> $pattern */
function semantic_match_count(array $records, array $pattern): int
{
    return count(array_filter(
        $records,
        static fn (array $record): bool => semantic_record_matches($pattern, $record)
    ));
}

/** @param list<array{kind:string,text:string}> $order @param list<array{kind:string,text:string}> $sequence */
function semantic_order_contains(array $order, array $sequence): bool
{
    if ($sequence === []) {
        return true;
    }
    $next = 0;
    foreach ($order as $anchor) {
        if (($anchor['kind'] ?? null) === ($sequence[$next]['kind'] ?? null)
            && ($anchor['text'] ?? null) === ($sequence[$next]['text'] ?? null)) {
            $next++;
            if ($next === count($sequence)) {
                return true;
            }
        }
    }

    return false;
}

function semantic_expectation_label(array $record): string
{
    if (isset($record['text'])) {
        return json_encode($record['text'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'text';
    }
    if (isset($record['id'])) {
        return (string) $record['id'];
    }

    return json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'record';
}

/**
 * @param array<string,mixed> $expectations
 * @param array<string,mixed> $snapshot
 * @return array<string,mixed>
 */
function evaluate_semantic_expectations(array $expectations, array $snapshot, string $mode = 'geometry-on'): array
{
    $status = (string) ($expectations['status'] ?? 'invalid');
    if ($mode !== 'geometry-on') {
        return [
            'status' => 'diagnostic_not_gated',
            'executed' => true,
            'passed' => null,
            'issues' => [],
            'checks' => [],
            'reason' => 'Exact semantic baselines apply to the production geometry-on mode; this mode is retained for diagnostic comparison.',
        ];
    }
    if (!in_array($status, ['verified_baseline', 'pending_manual_review', 'excluded_license_blocked'], true)) {
        return [
            'status' => 'invalid_expectation_schema',
            'executed' => true,
            'passed' => false,
            'issues' => ['invalid-semantic-expectation-schema'],
            'checks' => [],
            'reason' => 'The manifest entry did not provide a recognized semantic expectation state.',
        ];
    }
    if ($status === 'excluded_license_blocked') {
        return [
            'status' => 'invalid_excluded_execution',
            'executed' => true,
            'passed' => false,
            'issues' => ['license-blocked-entry-was-executed'],
            'checks' => [],
            'reason' => 'A license-blocked corpus entry must be excluded before conversion.',
        ];
    }
    if ($status !== 'verified_baseline') {
        return [
            'status' => $status,
            'executed' => true,
            'passed' => null,
            'issues' => [],
            'checks' => [],
            'reason' => $expectations['reason'] ?? 'No verified semantic baseline is available.',
        ];
    }

    $expected = is_array($expectations['expected'] ?? null) ? $expectations['expected'] : [];
    $forbidden = is_array($expectations['forbidden'] ?? null) ? $expectations['forbidden'] : [];
    $issues = [];
    $checks = [];
    $recordCategories = [
        'headings' => 'heading',
        'paragraphs' => 'paragraph',
        'listStarts' => 'list-start',
        'tableCells' => 'table-cell',
        'spans' => 'span',
        'links' => 'link',
        'mediaOccurrences' => 'media-occurrence',
        'unresolvedDispositions' => 'unresolved-disposition',
    ];
    foreach ($recordCategories as $category => $issueName) {
        $actualRecords = array_values(array_filter(
            is_array($snapshot[$category] ?? null) ? $snapshot[$category] : [],
            static fn (mixed $record): bool => is_array($record)
        ));
        foreach (($expected[$category] ?? []) as $index => $record) {
            if (!is_array($record)) {
                continue;
            }
            $expectedOccurrences = max(1, (int) ($record['occurrences'] ?? 1));
            $actualOccurrences = semantic_match_count($actualRecords, $record);
            $passed = $actualOccurrences === $expectedOccurrences;
            $checks["expected.{$category}.{$index}"] = [
                'passed' => $passed,
                'expectedOccurrences' => $expectedOccurrences,
                'actualOccurrences' => $actualOccurrences,
            ];
            if (!$passed) {
                $issues[] = 'expected-' . $issueName . '-missing-or-count-mismatch:' . semantic_expectation_label($record);
            }
        }
        foreach (($forbidden[$category] ?? []) as $index => $record) {
            if (!is_array($record)) {
                continue;
            }
            $actualOccurrences = semantic_match_count($actualRecords, $record);
            $passed = $actualOccurrences === 0;
            $checks["forbidden.{$category}.{$index}"] = [
                'passed' => $passed,
                'actualOccurrences' => $actualOccurrences,
            ];
            if (!$passed) {
                $issues[] = 'forbidden-' . $issueName . '-present:' . semantic_expectation_label($record);
            }
        }
    }

    foreach (($expected['tableHeaders'] ?? []) as $index => $record) {
        if (!is_array($record)) {
            continue;
        }
        $matches = semantic_match_count(
            is_array($snapshot['tableHeaders'] ?? null) ? $snapshot['tableHeaders'] : [],
            $record
        );
        $passed = $matches === 1;
        $checks["expected.tableHeaders.{$index}"] = ['passed' => $passed, 'actualOccurrences' => $matches];
        if (!$passed) {
            $issues[] = 'expected-table-header-missing-or-count-mismatch:' . semantic_expectation_label($record);
        }
    }
    foreach (($forbidden['tableHeaders'] ?? []) as $index => $record) {
        if (!is_array($record)) {
            continue;
        }
        $matches = semantic_match_count(
            is_array($snapshot['tableHeaders'] ?? null) ? $snapshot['tableHeaders'] : [],
            $record
        );
        $passed = $matches === 0;
        $checks["forbidden.tableHeaders.{$index}"] = ['passed' => $passed, 'actualOccurrences' => $matches];
        if (!$passed) {
            $issues[] = 'forbidden-table-header-present:' . semantic_expectation_label($record);
        }
    }

    foreach (($expected['order'] ?? []) as $index => $record) {
        $sequence = is_array($record['sequence'] ?? null) ? $record['sequence'] : [];
        $passed = semantic_order_contains(
            is_array($snapshot['order'] ?? null) ? $snapshot['order'] : [],
            $sequence
        );
        $checks["expected.order.{$index}"] = ['passed' => $passed];
        if (!$passed) {
            $issues[] = 'expected-order-missing:' . $index;
        }
    }
    foreach (($forbidden['order'] ?? []) as $index => $record) {
        $sequence = is_array($record['sequence'] ?? null) ? $record['sequence'] : [];
        $present = semantic_order_contains(
            is_array($snapshot['order'] ?? null) ? $snapshot['order'] : [],
            $sequence
        );
        $checks["forbidden.order.{$index}"] = ['passed' => !$present];
        if ($present) {
            $issues[] = 'forbidden-order-present:' . $index;
        }
    }

    $expectedCoverage = $expected['pageCoverage'] ?? null;
    if (is_array($expectedCoverage)) {
        $actualCoverage = is_array($snapshot['pageCoverage'] ?? null) ? $snapshot['pageCoverage'] : [];
        $passed = ($actualCoverage['pageCount'] ?? null) === ($expectedCoverage['pageCount'] ?? null)
            && ($actualCoverage['processedPages'] ?? null) === ($expectedCoverage['processedPages'] ?? null);
        $checks['expected.pageCoverage'] = ['passed' => $passed, 'actual' => $actualCoverage];
        if (!$passed) {
            $issues[] = 'expected-page-coverage-mismatch';
        }
    }
    $actualPages = is_array($snapshot['pageCoverage']['processedPages'] ?? null)
        ? $snapshot['pageCoverage']['processedPages']
        : [];
    foreach (($forbidden['pageCoverage'] ?? []) as $page) {
        $present = in_array($page, $actualPages, true);
        $checks['forbidden.pageCoverage.' . $page] = ['passed' => !$present];
        if ($present) {
            $issues[] = 'forbidden-page-present:' . $page;
        }
    }

    $actualCounts = is_array($snapshot['exactCounts'] ?? null) ? $snapshot['exactCounts'] : [];
    foreach (($expectations['exactCounts'] ?? []) as $key => $expectedCount) {
        $actualCount = $actualCounts[$key] ?? null;
        $passed = $actualCount === $expectedCount;
        $checks['exactCounts.' . $key] = [
            'passed' => $passed,
            'expected' => $expectedCount,
            'actual' => $actualCount,
        ];
        if (!$passed) {
            $issues[] = 'exact-count-mismatch:' . $key . ':expected-' . $expectedCount . ':actual-' . (string) $actualCount;
        }
    }

    return [
        'status' => $issues === [] ? 'verified_pass' : 'verified_fail',
        'executed' => true,
        'passed' => $issues === [],
        'issues' => array_values(array_unique($issues)),
        'checks' => $checks,
        'reason' => null,
    ];
}

/** @return array<string,mixed> */
function semantic_verification_not_executed(array $entry, string $executionState): array
{
    $expectations = is_array($entry['semanticExpectations'] ?? null) ? $entry['semanticExpectations'] : [];

    return [
        'status' => (string) ($expectations['status'] ?? 'invalid'),
        'executed' => false,
        'passed' => null,
        'issues' => [],
        'checks' => [],
        'reason' => $expectations['reason'] ?? $executionState,
        'executionState' => $executionState,
    ];
}

/**
 * @param list<string> $parts
 */
function collect_plain_text(AstNode $node, array &$parts): void
{
    if (in_array($node->type, ['text', 'code', 'math', 'code_block'], true)) {
        $parts[] = (string) $node->attr('text', '');
        return;
    }
    if (in_array($node->type, ['paragraph', 'heading', 'table_cell', 'list_item'], true) && $node->children === [] && $node->attr('text') !== null) {
        $parts[] = (string) $node->attr('text', '');
        $parts[] = "\n";
        return;
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        $parts[] = "\n";
        return;
    }
    if ($node->type === 'space') {
        $parts[] = ' ';
        return;
    }
    foreach ($node->children as $child) {
        collect_plain_text($child, $parts);
        if (in_array($child->type, ['paragraph', 'heading', 'code_block', 'table_row', 'list_item', 'line'], true)) {
            $parts[] = "\n";
        }
        if ($child->type === 'table_cell') {
            $parts[] = "\t";
        }
    }
    if (in_array($node->type, ['paragraph', 'heading', 'table_row', 'table', 'bullet_list', 'ordered_list', 'line_block'], true)) {
        $parts[] = "\n";
    }
}

/**
 * @return array<string, mixed>
 */
function spacing_review(string $text): array
{
    $examples = [
        'splitFragments' => regex_examples('/\b[A-Za-z]{2,}\s+[a-z]{1,3}(?=[a-z]?\b)/', $text),
        'longGluedWords' => regex_examples('/\b[A-Za-z]{24,}\b/', $text),
        'missingSpaceAfterPunctuation' => regex_examples('/[a-z][.!?][A-Z]/', $text),
        'missingSpaceAfterCommaOrSemicolon' => regex_examples('/[a-z][,;:][A-Z]/', $text),
        'braceArtifacts' => regex_examples('/\{\s*\}/', $text),
    ];
    $counts = [];
    foreach ($examples as $key => $values) {
        $counts[$key] = count($values);
    }

    return [
        'counts' => $counts,
        'examples' => $examples,
        'heuristicScore' => array_sum($counts),
    ];
}

/**
 * @return list<string>
 */
function regex_examples(string $pattern, string $text, int $limit = 12): array
{
    preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
    $examples = [];
    foreach ($matches[0] ?? [] as [$match, $offset]) {
        $start = max(0, (int) $offset - 45);
        $snippet = substr($text, $start, strlen((string) $match) + 90);
        $snippet = preg_replace('/\s+/', ' ', $snippet) ?? $snippet;
        $examples[] = trim($snippet);
        if (count($examples) >= $limit) {
            break;
        }
    }

    return array_values(array_unique($examples));
}

/**
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function review_status(array $entry, AstNode $document, string $plainText, array $semanticVerification = []): array
{
    $expectedTables = (int) ($entry['expectedTables'] ?? 0);
    $tableCount = count_nodes($document, 'table');
    $metadata = $document->attr('meta', []);
    $metadata = is_array($metadata) ? $metadata : [];
    $spacing = spacing_review($plainText);
    $issues = [];
    if ($expectedTables > 0 && $tableCount < 1) {
        $issues[] = 'expected-table-missing';
    }
    if (isset($entry['expectedPhysicalTables'])
        && $tableCount !== (int) $entry['expectedPhysicalTables']) {
        $issues[] = 'physical-table-count-mismatch';
    }
    if (isset($entry['expectedLogicalFamilies'])
        && (int) ($metadata['pdfLogicalTableFamilyCount'] ?? -1) !== (int) $entry['expectedLogicalFamilies']) {
        $issues[] = 'logical-table-family-count-mismatch';
    }
    if (isset($entry['expectedLogicalInstances'])
        && (int) ($metadata['pdfLogicalTableInstanceCount'] ?? -1) !== (int) $entry['expectedLogicalInstances']) {
        $issues[] = 'logical-table-instance-count-mismatch';
    }
    if (($spacing['counts']['braceArtifacts'] ?? 0) > 0) {
        $issues[] = 'brace-artifacts';
    }
    if (($spacing['counts']['longGluedWords'] ?? 0) > 0) {
        $issues[] = 'long-glued-words';
    }
    if (($spacing['counts']['missingSpaceAfterPunctuation'] ?? 0) > 0) {
        $issues[] = 'missing-space-after-punctuation';
    }
    if (($metadata['pdfSemanticTextComplete'] ?? false) !== true) {
        $issues[] = 'semantic-text-incomplete';
    }
    if (($metadata['pdfDocumentComplete'] ?? false) !== true) {
        $issues[] = 'document-range-incomplete';
    }
    if (($semanticVerification['passed'] ?? null) === false) {
        $issues[] = 'semantic-expectation-failed';
    }

    $criteria = is_array($entry['success'] ?? null) ? $entry['success'] : [];
    $metrics = [
        'textBytes' => strlen($plainText),
        'paragraphs' => count_nodes($document, 'paragraph'),
        'headings' => count_nodes($document, 'heading'),
        'tables' => $tableCount,
        'logicalTables' => (int) ($metadata['pdfLogicalTableCount'] ?? $tableCount),
        'logicalTableFamilies' => (int) ($metadata['pdfLogicalTableFamilyCount'] ?? 0),
        'logicalTableInstances' => (int) ($metadata['pdfLogicalTableInstanceCount'] ?? 0),
        'lists' => count_nodes($document, 'bullet_list') + count_nodes($document, 'ordered_list'),
        'codeBlocks' => count_nodes($document, 'code_block'),
        'lineOrientedBlocks' => count_nodes($document, 'line_block'),
        'dialogueParagraphs' => count_dialogue_paragraphs($document),
        'singleGlyphParagraphs' => count_single_glyph_paragraphs($document),
    ];
    $checks = [];
    $minimums = [
        'minTextBytes' => 'textBytes',
        'minParagraphs' => 'paragraphs',
        'minHeadings' => 'headings',
        'minTables' => 'tables',
        'minLists' => 'lists',
        'minCodeBlocks' => 'codeBlocks',
        'minLineOrientedBlocks' => 'lineOrientedBlocks',
        'minDialogueParagraphs' => 'dialogueParagraphs',
    ];
    foreach ($minimums as $criterion => $metric) {
        if (!array_key_exists($criterion, $criteria)) {
            continue;
        }
        $passed = $metrics[$metric] >= (int) $criteria[$criterion];
        $checks[$criterion] = $passed;
        if (!$passed) {
            $issues[] = 'criterion-' . $criterion;
        }
    }
    $maximums = [
        'maxTables' => 'tables',
        'maxCodeBlocks' => 'codeBlocks',
        'maxLineOrientedBlocks' => 'lineOrientedBlocks',
        'maxSingleGlyphParagraphs' => 'singleGlyphParagraphs',
    ];
    foreach ($maximums as $criterion => $metric) {
        if (!array_key_exists($criterion, $criteria)) {
            continue;
        }
        $passed = $metrics[$metric] <= (int) $criteria[$criterion];
        $checks[$criterion] = $passed;
        if (!$passed) {
            $issues[] = 'criterion-' . $criterion;
        }
    }

    $heuristicCriteriaSatisfied = $issues === [];
    $verifiedBaseline = ($entry['semanticExpectations']['status'] ?? null) === 'verified_baseline';

    return [
        // Compatibility field: a pending remote candidate is never described
        // as approved merely because generic numeric heuristics happened to
        // pass. Manual review has its own evidence-gated state.
        'approvedByHeuristic' => $verifiedBaseline && $heuristicCriteriaSatisfied,
        'heuristicCriteriaSatisfied' => $heuristicCriteriaSatisfied,
        'manualReviewRequired' => !$verifiedBaseline,
        'issues' => $issues,
        'criteria' => $criteria,
        'metrics' => $metrics,
        'checks' => $checks,
        'semanticExpectationStatus' => $semanticVerification['status'] ?? 'unavailable',
    ];
}

/**
 * @param list<array<string, mixed>> $records
 * @return array<string, mixed>
 */
function summarize_records(array $records): array
{
    $summary = [
        'pdfCount' => count($records),
        'pinnedArtifacts' => 0,
        'verifiedArtifacts' => 0,
        'executedDocuments' => 0,
        'checkedInVerifiedExecuted' => 0,
        'remotePinnedNotFetched' => 0,
        'remoteFetchedVerified' => 0,
        'remoteFetchedVerifiedExecuted' => 0,
        'executionFailedDocuments' => 0,
        'excludedLicenseBlocked' => 0,
        'verifiedSemanticBaselineDocuments' => 0,
        'pendingManualReviewDocuments' => 0,
        'manualReviewPendingDocuments' => 0,
        'humanReviewedPassDocuments' => 0,
        'humanReviewedFailDocuments' => 0,
        'invalidManualReviewEvidenceDocuments' => 0,
        'convertedModes' => 0,
        'failedArtifactVerification' => 0,
        'failedConversions' => 0,
        'geometryOnWithTables' => 0,
        'geometryOnHeuristicApproved' => 0,
        'geometryOnHeuristicCriteriaSatisfied' => 0,
        'semanticExpectationModePasses' => 0,
        'semanticExpectationFailures' => 0,
    ];
    foreach ($records as $record) {
        $executionState = (string) ($record['executionState'] ?? '');
        $artifact = is_array($record['artifact'] ?? null) ? $record['artifact'] : [];
        if (($artifact['pinned'] ?? false) === true || ($artifact['ok'] ?? false) === true) {
            $summary['pinnedArtifacts']++;
        }
        $expectationStatus = (string) ($record['semanticExpectations']['status'] ?? 'invalid');
        if ($expectationStatus === 'verified_baseline') {
            $summary['verifiedSemanticBaselineDocuments']++;
        } elseif ($expectationStatus === 'pending_manual_review') {
            $summary['pendingManualReviewDocuments']++;
        }
        if (($record['excluded'] ?? false) === true) {
            $summary['excludedLicenseBlocked']++;
        } elseif (($artifact['ok'] ?? false) === true) {
            $summary['verifiedArtifacts']++;
            if (($artifact['pinStatus'] ?? '') === 'remote-hash-pinned') {
                $summary['remoteFetchedVerified']++;
            }
            if (in_array($executionState, ['remote_fetched_verified_executed', 'checked_in_verified_executed'], true)
                || ($executionState === '' && ($record['modes'] ?? []) !== [])) {
                $summary['executedDocuments']++;
            }
            if ($executionState === 'remote_fetched_verified_executed') {
                $summary['remoteFetchedVerifiedExecuted']++;
            } elseif ($executionState === 'checked_in_verified_executed') {
                $summary['checkedInVerifiedExecuted']++;
            } elseif (str_ends_with($executionState, '_execution_failed')) {
                $summary['executionFailedDocuments']++;
            }
        } elseif ($executionState === 'remote_pinned_not_fetched') {
            $summary['remotePinnedNotFetched']++;
        } else {
            $summary['failedArtifactVerification']++;
        }
        $manualReviewStatus = (string) ($record['manualReview']['status'] ?? '');
        if (in_array($manualReviewStatus, ['pending_execution', 'pending_manual_review'], true)) {
            $summary['manualReviewPendingDocuments']++;
        } elseif ($manualReviewStatus === 'reviewed_pass') {
            $summary['humanReviewedPassDocuments']++;
        } elseif ($manualReviewStatus === 'reviewed_fail') {
            $summary['humanReviewedFailDocuments']++;
        } elseif ($manualReviewStatus === 'invalid_review_evidence') {
            $summary['invalidManualReviewEvidenceDocuments']++;
        }
        foreach (($record['modes'] ?? []) as $mode => $modeRecord) {
            if (($modeRecord['ok'] ?? false) !== true) {
                $summary['failedConversions']++;
                continue;
            }
            $summary['convertedModes']++;
            if (($modeRecord['semanticVerification']['passed'] ?? null) === true) {
                $summary['semanticExpectationModePasses']++;
            } elseif (($modeRecord['semanticVerification']['passed'] ?? null) === false) {
                $summary['semanticExpectationFailures']++;
            }
            if ($mode === 'geometry-on' && (int) ($modeRecord['tableCount'] ?? 0) > 0) {
                $summary['geometryOnWithTables']++;
            }
            if ($mode === 'geometry-on' && (($modeRecord['reviewStatus']['approvedByHeuristic'] ?? false) === true)) {
                $summary['geometryOnHeuristicApproved']++;
            }
            if ($mode === 'geometry-on' && (($modeRecord['reviewStatus']['heuristicCriteriaSatisfied'] ?? false) === true)) {
                $summary['geometryOnHeuristicCriteriaSatisfied']++;
            }
        }
    }

    return $summary;
}

/**
 * @param array<string, mixed> $report
 */
function pdf_corpus_report_href(string $targetPath, string $workDir): ?string
{
    $repositoryRoot = str_replace('\\', '/', dirname(__DIR__));
    $targetPath = str_replace('\\', '/', trim($targetPath));
    $workDir = str_replace('\\', '/', trim($workDir));
    if ($targetPath === '' || $workDir === '') {
        return null;
    }

    $targetAbsolute = str_starts_with($targetPath, '/')
        ? $targetPath
        : $repositoryRoot . '/' . ltrim($targetPath, '/');
    $workAbsolute = str_starts_with($workDir, '/')
        ? $workDir
        : $repositoryRoot . '/' . ltrim($workDir, '/');

    $normalize = static function (string $path): string {
        $prefix = str_starts_with($path, '/') ? '/' : '';
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return '';
                }
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return $prefix . implode('/', $segments);
    };
    $targetAbsolute = $normalize($targetAbsolute);
    $workAbsolute = $normalize($workAbsolute);
    if ($targetAbsolute === ''
        || $workAbsolute === ''
        || !str_starts_with($targetAbsolute, $repositoryRoot . '/')) {
        return null;
    }

    $targetSegments = explode('/', ltrim($targetAbsolute, '/'));
    $workSegments = explode('/', ltrim($workAbsolute, '/'));
    while ($targetSegments !== []
        && $workSegments !== []
        && $targetSegments[0] === $workSegments[0]) {
        array_shift($targetSegments);
        array_shift($workSegments);
    }
    $relativeSegments = array_merge(
        array_fill(0, count($workSegments), '..'),
        $targetSegments
    );
    if ($relativeSegments === []) {
        return '.';
    }

    return implode('/', array_map(
        static fn (string $segment): string => $segment === '..' ? '..' : rawurlencode($segment),
        $relativeSegments
    ));
}

function render_html_report(array $report): string
{
    $rows = '';
    $workDir = (string) ($report['workDir'] ?? '');
    foreach ($report['records'] as $record) {
        $cells = '';
        foreach (['geometry-on', 'repair-only'] as $mode) {
            if (($record['excluded'] ?? false) === true) {
                $cells .= '<td>excluded pending license review</td>';
                continue;
            }
            $modeRecord = $record['modes'][$mode] ?? null;
            if (!is_array($modeRecord) || ($modeRecord['ok'] ?? false) !== true) {
                $state = (string) ($record['executionState'] ?? 'not-executed');
                $cells .= '<td class="bad">' . htmlspecialchars($state, ENT_QUOTES) . '</td>';
                continue;
            }
            $issues = implode(', ', $modeRecord['reviewStatus']['issues'] ?? []);
            $issues = $issues === '' ? 'none' : $issues;
            $score = (int) ($modeRecord['spacingReview']['heuristicScore'] ?? 0);
            $semanticStatus = (string) ($modeRecord['semanticVerification']['status'] ?? 'unavailable');
            $semanticIssues = implode(', ', $modeRecord['semanticVerification']['issues'] ?? []);
            $semanticIssues = $semanticIssues === '' ? 'none' : $semanticIssues;
            $wordpressHref = pdf_corpus_report_href(
                (string) ($modeRecord['outputs']['wordpress'] ?? ''),
                $workDir
            );
            $wordpressLink = $wordpressHref === null
                ? 'WordPress output unavailable'
                : '<a href="' . htmlspecialchars($wordpressHref, ENT_QUOTES) . '">WordPress blocks</a>';
            $cells .= '<td>'
                . '<strong>tables:</strong> ' . (int) ($modeRecord['tableCount'] ?? 0)
                . '<br><strong>lists:</strong> ' . (int) ($modeRecord['listCount'] ?? 0)
                . '<br><strong>spacing score:</strong> ' . $score
                . '<br><strong>issues:</strong> ' . htmlspecialchars($issues, ENT_QUOTES)
                . '<br><strong>semantic expectations:</strong> ' . htmlspecialchars($semanticStatus, ENT_QUOTES)
                . '<br><strong>semantic issues:</strong> ' . htmlspecialchars($semanticIssues, ENT_QUOTES)
                . '<br>' . $wordpressLink
                . '</td>';
        }
        $artifactState = (string) ($record['artifact']['availabilityState'] ?? 'unknown');
        $artifactStatus = match ($artifactState) {
            'excluded_license_blocked' => 'license-blocked/excluded',
            'remote_pinned_not_fetched' => 'immutable identity pinned; artifact not fetched',
            'remote_fetched_verified' => 'remote artifact fetched and SHA-256 verified',
            'checked_in_verified' => 'checked-in artifact SHA-256 verified',
            default => 'artifact verification failed',
        };
        $executionState = (string) ($record['executionState'] ?? 'not-executed');
        $manualReviewState = (string) ($record['manualReview']['status'] ?? 'not-recorded');
        $artifactHref = pdf_corpus_report_href((string) ($record['artifact']['path'] ?? ''), $workDir);
        $artifactLink = $artifactHref === null
            ? ''
            : '<br><a href="' . htmlspecialchars($artifactHref, ENT_QUOTES) . '">Source PDF</a>';
        $executionHref = pdf_corpus_report_href(
            (string) ($record['executionReport']['absolutePath'] ?? ''),
            $workDir
        );
        $executionLink = $executionHref === null
            ? ''
            : '<br><a href="' . htmlspecialchars($executionHref, ENT_QUOTES) . '">Execution report</a>';
        $rows .= '<tr><th>' . htmlspecialchars((string) $record['id'], ENT_QUOTES) . '</th>'
            . '<td>' . htmlspecialchars((string) ($record['kind'] ?? ''), ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($artifactStatus, ENT_QUOTES) . $artifactLink . '</td>'
            . '<td>' . htmlspecialchars($executionState, ENT_QUOTES) . $executionLink . '</td>'
            . '<td>' . htmlspecialchars($manualReviewState, ENT_QUOTES) . '</td>'
            . $cells
            . '</tr>';
    }

    return '<!doctype html><meta charset="utf-8"><title>PDF corpus report</title>'
        . '<style>body{font:14px system-ui,sans-serif;margin:24px;color:#17202a}table{border-collapse:collapse;width:100%}th,td{border:1px solid #d8dee4;padding:8px;text-align:left;vertical-align:top}th{background:#f6f8fa}.bad{color:#9b1c1c;background:#fff5f5}code{white-space:pre-wrap}</style>'
        . '<h1>PDF corpus report</h1>'
        . '<p>Generated ' . htmlspecialchars((string) $report['generatedAt'], ENT_QUOTES) . '. Verified baselines are strict semantic gates. A remote pin is not an execution. A remote manual pass is reported only when exact artifact and execution-report identities, desktop/mobile source/output screenshot hashes, and an explicit human verdict all validate. License-blocked records remain excluded.</p>'
        . '<pre><code>' . htmlspecialchars((string) json_encode($report['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES) . '</code></pre>'
        . '<table><thead><tr><th>PDF</th><th>Kind</th><th>Artifact</th><th>Execution</th><th>Manual review</th><th>Geometry on</th><th>Repair only</th></tr></thead><tbody>'
        . $rows
        . '</tbody></table>';
}
