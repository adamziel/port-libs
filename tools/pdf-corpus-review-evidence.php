#!/usr/bin/env php
<?php

declare(strict_types=1);

/** @return array<string,string|bool> */
function pdf_corpus_review_parse_options(array $arguments): array
{
    $options = [];
    for ($index = 1, $count = count($arguments); $index < $count; $index++) {
        $argument = (string) $arguments[$index];
        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }
        if ($argument === '--replace') {
            $options['replace'] = true;
            continue;
        }
        if (!str_starts_with($argument, '--')) {
            throw new InvalidArgumentException('Unexpected positional argument: ' . $argument);
        }
        $name = substr($argument, 2);
        if ($name === '' || $index + 1 >= $count) {
            throw new InvalidArgumentException('Missing value for ' . $argument . '.');
        }
        $options[$name] = (string) $arguments[++$index];
    }

    return $options;
}

function pdf_corpus_review_usage(): string
{
    return <<<'TEXT'
Usage:
  php tools/pdf-corpus-review-evidence.php \
    --report .port-libs/pdf-corpus/report.json \
    --id CORPUS_ID \
    --desktop-source desktop-source.png \
    --desktop-output desktop-output.png \
    --mobile-source mobile-source.png \
    --mobile-output mobile-output.png \
    --verdict pass|fail --reviewer "Human name" \
    --reviewed-at 2026-07-17T12:00:00Z --notes "Review notes" \
    [--output-dir .port-libs/pdf-corpus/review-evidence] [--replace]

The command never chooses a verdict. It binds an explicit human verdict to the
exact PDF, execution report, and four PNG screenshots. --replace is required
when the corpus already has a different receipt; the old receipt is archived.
TEXT;
}

function pdf_corpus_review_absolute_path(string $path, string $base): string
{
    if ($path === '') {
        throw new InvalidArgumentException('A required path is empty.');
    }
    if ($path[0] === DIRECTORY_SEPARATOR) {
        return $path;
    }

    return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
}

/** @return array{path:string,bytes:int,sha256:string} */
function pdf_corpus_review_file_identity(string $path, string $reportedPath): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Required evidence file is missing or unreadable: ' . $path);
    }
    $bytes = file_get_contents($path);
    if (!is_string($bytes)) {
        throw new RuntimeException('Could not read evidence file: ' . $path);
    }

    return [
        'path' => $reportedPath,
        'bytes' => strlen($bytes),
        'sha256' => hash('sha256', $bytes),
    ];
}

/** @param array<string,mixed> $expected */
function pdf_corpus_review_assert_identity(array $expected, array $actual, string $label): void
{
    foreach (['bytes', 'sha256'] as $key) {
        if (($expected[$key] ?? null) !== ($actual[$key] ?? null)) {
            throw new RuntimeException($label . ' ' . $key . ' does not match the report.');
        }
    }
}

function pdf_corpus_review_ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Could not create review evidence directory: ' . $path);
    }
}

function pdf_corpus_review_atomic_write(string $path, string $bytes): void
{
    $directory = dirname($path);
    pdf_corpus_review_ensure_directory($directory);
    $temporary = tempnam($directory, '.pdf-review-');
    if (!is_string($temporary)) {
        throw new RuntimeException('Could not allocate a temporary review evidence file.');
    }
    try {
        if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('Could not write complete review evidence: ' . $path);
        }
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Could not atomically publish review evidence: ' . $path);
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

/** @return array{path:string,bytes:int,sha256:string} */
function pdf_corpus_review_import_png(
    string $sourcePath,
    string $outputRoot,
    string $corpusId,
    string $label
): array {
    if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) !== 'png') {
        throw new InvalidArgumentException($label . ' must have a .png extension.');
    }
    if (!is_file($sourcePath) || !is_readable($sourcePath)) {
        throw new RuntimeException($label . ' is missing or unreadable: ' . $sourcePath);
    }
    $bytes = file_get_contents($sourcePath);
    if (!is_string($bytes) || !str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
        throw new RuntimeException($label . ' is not a PNG file.');
    }
    $sha256 = hash('sha256', $bytes);
    $relativePath = 'assets/' . $corpusId . '/' . $label . '-' . substr($sha256, 0, 16) . '.png';
    $destination = $outputRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($destination)) {
        $existing = file_get_contents($destination);
        if (!is_string($existing) || !hash_equals($sha256, hash('sha256', $existing))) {
            throw new RuntimeException('Content-addressed screenshot collision: ' . $destination);
        }
    } else {
        pdf_corpus_review_atomic_write($destination, $bytes);
    }

    return [
        'path' => $relativePath,
        'bytes' => strlen($bytes),
        'sha256' => $sha256,
    ];
}

function pdf_corpus_review_valid_timestamp(string $value): bool
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
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
function pdf_corpus_review_execution_source_integrity_issues(
    string $path,
    string $expectedCorpusId,
    array $expectedArtifact,
    string $expectedExecutionState
): array
{
    $bytes = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($bytes)) {
        return ['execution-report-unreadable'];
    }
    try {
        $receipt = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['execution-report-json-invalid'];
    }
    $topLevelKeys = is_array($receipt) ? array_keys($receipt) : [];
    sort($topLevelKeys, SORT_STRING);
    if ($topLevelKeys !== ['artifact', 'corpusId', 'executionState', 'modes', 'schemaVersion']
        || ($receipt['schemaVersion'] ?? null) !== 1) {
        return ['execution-report-schema-invalid'];
    }
    if (($receipt['corpusId'] ?? null) !== $expectedCorpusId) {
        return ['execution-report-corpus-id-mismatch'];
    }
    if (($receipt['executionState'] ?? null) !== $expectedExecutionState) {
        return ['execution-report-execution-state-mismatch'];
    }
    $receiptArtifact = is_array($receipt['artifact'] ?? null) ? $receipt['artifact'] : [];
    $artifactKeys = array_keys($receiptArtifact);
    sort($artifactKeys, SORT_STRING);
    if ($artifactKeys !== ['bytes', 'pinStatus', 'sha256']
        || ($receiptArtifact['pinStatus'] ?? null) !== ($expectedArtifact['pinStatus'] ?? null)
        || ($receiptArtifact['bytes'] ?? null) !== ($expectedArtifact['bytes'] ?? null)
        || !is_string($receiptArtifact['sha256'] ?? null)
        || !is_string($expectedArtifact['sha256'] ?? null)
        || !hash_equals($expectedArtifact['sha256'], $receiptArtifact['sha256'])) {
        return ['execution-report-artifact-mismatch'];
    }
    $modes = is_array($receipt['modes'] ?? null) ? $receipt['modes'] : [];
    $modeNames = array_keys($modes);
    sort($modeNames, SORT_STRING);
    if ($modeNames !== ['geometry-on', 'repair-only']) {
        return ['execution-report-modes-invalid'];
    }

    $issues = [];
    foreach ($modes as $mode => $modeRecord) {
        if (!is_array($modeRecord)) {
            $issues[] = (string) $mode . '-record-invalid';
            continue;
        }
        $outputs = is_array($modeRecord['outputs'] ?? null) ? $modeRecord['outputs'] : [];
        $outputNames = array_keys($outputs);
        sort($outputNames, SORT_STRING);
        if ($outputNames !== ['html', 'plain', 'wordpress']
            && $outputNames !== ['html', 'native', 'plain', 'wordpress']) {
            $issues[] = (string) $mode . '-outputs-invalid';
        } else {
            foreach ($outputs as $outputKind => $outputIdentity) {
                $identityKeys = is_array($outputIdentity) ? array_keys($outputIdentity) : [];
                sort($identityKeys, SORT_STRING);
                $expectedBasename = match ($outputKind) {
                    'plain' => (string) $mode . '.plain.txt',
                    'html' => (string) $mode . '.html',
                    'wordpress' => (string) $mode . '.wordpress.html',
                    'native' => (string) $mode . '.native',
                    default => '',
                };
                if ($identityKeys !== ['bytes', 'path', 'sha256']
                    || !is_string($outputIdentity['path'] ?? null)
                    || $outputIdentity['path'] === ''
                    || basename(str_replace('\\', '/', $outputIdentity['path'])) !== $expectedBasename
                    || !is_int($outputIdentity['bytes'] ?? null)
                    || $outputIdentity['bytes'] < 0
                    || !is_string($outputIdentity['sha256'] ?? null)
                    || preg_match('/^[a-f0-9]{64}$/', $outputIdentity['sha256']) !== 1) {
                    $issues[] = (string) $mode . '-output-identity-invalid';
                    break;
                }
                $actualOutputPath = dirname($path) . DIRECTORY_SEPARATOR . $expectedBasename;
                $actualOutputBytes = is_file($actualOutputPath)
                    ? file_get_contents($actualOutputPath)
                    : false;
                if (!is_string($actualOutputBytes)
                    || strlen($actualOutputBytes) !== $outputIdentity['bytes']
                    || !hash_equals($outputIdentity['sha256'], hash('sha256', $actualOutputBytes))) {
                    $issues[] = (string) $mode . '-output-file-mismatch';
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
        if (($modeRecord['ok'] ?? null) !== true
            || ($integrity['complete'] ?? null) !== true
            || ($integrity['pdfDocumentComplete'] ?? null) !== true
            || ($integrity['pdfSemanticTextComplete'] ?? null) !== true
            || ($integrity['pdfSourceBindingComplete'] ?? null) !== true
            || ($integrity['pdfSourceEdgeMappingComplete'] ?? null) !== true
            || ($integrity['pdfOrderedSignificantCharactersPreserved'] ?? null) !== true
            || $unresolved !== 0) {
            $issues[] = (string) $mode . '-source-integrity-incomplete';
        }
    }

    return $issues;
}

/**
 * @param array<string,string|bool> $options
 * @return array{path:string,bytes:int,sha256:string,evidence:array<string,mixed>,archivedPath:?string}
 */
function build_pdf_corpus_review_evidence(array $options): array
{
    $required = [
        'report', 'id', 'desktop-source', 'desktop-output', 'mobile-source',
        'mobile-output', 'verdict', 'reviewer', 'reviewed-at', 'notes',
    ];
    foreach ($required as $key) {
        if (!is_string($options[$key] ?? null) || trim((string) $options[$key]) === '') {
            throw new InvalidArgumentException('Missing required --' . $key . ' value.');
        }
    }

    $repositoryRoot = dirname(__DIR__);
    $reportPath = pdf_corpus_review_absolute_path((string) $options['report'], $repositoryRoot);
    $reportBytes = is_file($reportPath) ? file_get_contents($reportPath) : false;
    if (!is_string($reportBytes)) {
        throw new RuntimeException('Corpus report is missing or unreadable: ' . $reportPath);
    }
    try {
        $report = json_decode($reportBytes, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        throw new RuntimeException('Corpus report is not valid JSON.', 0, $error);
    }
    if (!is_array($report) || !is_array($report['records'] ?? null)) {
        throw new RuntimeException('Corpus report does not contain records.');
    }

    $corpusId = trim((string) $options['id']);
    if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $corpusId) !== 1) {
        throw new InvalidArgumentException('Corpus ID must be a lowercase slug.');
    }
    $record = null;
    foreach ($report['records'] as $candidate) {
        if (is_array($candidate) && ($candidate['id'] ?? null) === $corpusId) {
            $record = $candidate;
            break;
        }
    }
    if (!is_array($record)) {
        throw new RuntimeException('Corpus report has no record for ' . $corpusId . '.');
    }
    if (($record['executionState'] ?? null) !== 'remote_fetched_verified_executed') {
        throw new RuntimeException('Manual review evidence requires a fully executed remote artifact.');
    }
    if (($record['semanticExpectations']['status'] ?? null) !== 'pending_manual_review') {
        throw new RuntimeException('The selected record is not pending manual review.');
    }

    $artifact = is_array($record['artifact'] ?? null) ? $record['artifact'] : [];
    $artifactPath = pdf_corpus_review_absolute_path((string) ($artifact['path'] ?? ''), $repositoryRoot);
    $actualArtifact = pdf_corpus_review_file_identity($artifactPath, '');
    pdf_corpus_review_assert_identity($artifact, $actualArtifact, 'PDF artifact');

    $executionReport = is_array($record['executionReport'] ?? null) ? $record['executionReport'] : [];
    $executionPath = (string) ($executionReport['absolutePath'] ?? $executionReport['path'] ?? '');
    $executionPath = pdf_corpus_review_absolute_path($executionPath, $repositoryRoot);
    $actualExecution = pdf_corpus_review_file_identity(
        $executionPath,
        (string) ($executionReport['path'] ?? '')
    );
    pdf_corpus_review_assert_identity($executionReport, $actualExecution, 'Execution report');

    $verdict = (string) $options['verdict'];
    if (!in_array($verdict, ['pass', 'fail'], true)) {
        throw new InvalidArgumentException('--verdict must be pass or fail.');
    }
    if ($verdict === 'pass') {
        $sourceIntegrityIssues = pdf_corpus_review_execution_source_integrity_issues(
            $executionPath,
            $corpusId,
            $artifact,
            (string) ($record['executionState'] ?? '')
        );
        if ($sourceIntegrityIssues !== []) {
            throw new RuntimeException(
                'A pass verdict requires mechanically complete PDF source integrity: '
                . implode(', ', $sourceIntegrityIssues)
            );
        }
    }
    $reviewedAt = (string) $options['reviewed-at'];
    if (!pdf_corpus_review_valid_timestamp($reviewedAt)) {
        throw new InvalidArgumentException('--reviewed-at must be an ISO-8601 timestamp with an explicit zone.');
    }

    $defaultOutputRoot = dirname($reportPath) . DIRECTORY_SEPARATOR . 'review-evidence';
    $outputRoot = isset($options['output-dir'])
        ? pdf_corpus_review_absolute_path((string) $options['output-dir'], $repositoryRoot)
        : $defaultOutputRoot;
    pdf_corpus_review_ensure_directory($outputRoot);

    $screenshots = [];
    foreach (['desktop', 'mobile'] as $viewport) {
        foreach (['source', 'output'] as $side) {
            $key = $viewport . '-' . $side;
            $screenshots[$viewport][$side] = pdf_corpus_review_import_png(
                pdf_corpus_review_absolute_path((string) $options[$key], getcwd() ?: $repositoryRoot),
                $outputRoot,
                $corpusId,
                $key
            );
        }
    }

    $evidence = [
        'schemaVersion' => 1,
        'corpusId' => $corpusId,
        'artifact' => [
            'bytes' => $actualArtifact['bytes'],
            'sha256' => $actualArtifact['sha256'],
        ],
        'executionReport' => $actualExecution,
        'screenshots' => $screenshots,
        'verdict' => [
            'result' => $verdict,
            'reviewer' => trim((string) $options['reviewer']),
            'reviewedAt' => $reviewedAt,
            'notes' => trim((string) $options['notes']),
        ],
    ];
    $encoded = json_encode(
        $evidence,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
    $destination = $outputRoot . DIRECTORY_SEPARATOR . $corpusId . '.json';
    $archivedPath = null;
    if (is_file($destination)) {
        $existing = file_get_contents($destination);
        if (!is_string($existing)) {
            throw new RuntimeException('Existing review receipt is unreadable: ' . $destination);
        }
        if (hash_equals(hash('sha256', $existing), hash('sha256', $encoded))) {
            return [
                'path' => $destination,
                'bytes' => strlen($encoded),
                'sha256' => hash('sha256', $encoded),
                'evidence' => $evidence,
                'archivedPath' => null,
            ];
        }
        if (($options['replace'] ?? false) !== true) {
            throw new RuntimeException('A different receipt already exists; rerun with --replace to archive it.');
        }
        $archiveHash = substr(hash('sha256', $existing), 0, 16);
        $archivedPath = $outputRoot . DIRECTORY_SEPARATOR . $corpusId . '.previous-' . $archiveHash . '.json';
        if (!is_file($archivedPath)) {
            pdf_corpus_review_atomic_write($archivedPath, $existing);
        }
    }
    pdf_corpus_review_atomic_write($destination, $encoded);

    return [
        'path' => $destination,
        'bytes' => strlen($encoded),
        'sha256' => hash('sha256', $encoded),
        'evidence' => $evidence,
        'archivedPath' => $archivedPath,
    ];
}

if (!defined('PDF_CORPUS_REVIEW_EVIDENCE_LIBRARY_ONLY')) {
    try {
        $options = pdf_corpus_review_parse_options($argv);
        if (($options['help'] ?? false) === true) {
            fwrite(STDOUT, pdf_corpus_review_usage() . "\n");
            exit(0);
        }
        $result = build_pdf_corpus_review_evidence($options);
        fwrite(
            STDOUT,
            'Wrote ' . $result['path'] . ' (' . $result['bytes'] . ' bytes, SHA-256 '
            . $result['sha256'] . ").\n"
        );
        if (is_string($result['archivedPath'])) {
            fwrite(STDOUT, 'Archived the previous receipt at ' . $result['archivedPath'] . ".\n");
        }
    } catch (Throwable $error) {
        fwrite(STDERR, 'PDF corpus review evidence error: ' . $error->getMessage() . "\n");
        fwrite(STDERR, pdf_corpus_review_usage() . "\n");
        exit(1);
    }
}
