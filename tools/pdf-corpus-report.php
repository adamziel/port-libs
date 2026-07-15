<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;

require __DIR__ . '/bootstrap.php';

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
$manifest = read_manifest($manifestPath);

ensure_dir($workDir);
ensure_dir($workDir . '/pdfs');
ensure_dir($workDir . '/outputs');

$modes = [
    'geometry-on' => [
        'pdfFastTextOnly' => false,
        'pdfGeometryTables' => true,
        'pdfRepairProseText' => true,
        'maxTextBytes' => 90000,
        'pdfMaxPages' => 8,
    ],
    'repair-only' => [
        'pdfFastTextOnly' => false,
        'pdfGeometryTables' => false,
        'pdfRepairProseText' => true,
        'maxTextBytes' => 90000,
        'pdfMaxPages' => 8,
    ],
];

$records = [];
foreach ($manifest as $entry) {
    $id = (string) $entry['id'];
    $pdfPath = $workDir . '/pdfs/' . $id . '.pdf';
    $entryOutDir = $workDir . '/outputs/' . $id;
    ensure_dir($entryOutDir);

    fwrite(STDERR, "== {$id}\n");
    $download = is_file($pdfPath) ? ['ok' => true, 'cached' => true, 'bytes' => filesize($pdfPath)] : download_pdf((string) $entry['url'], $pdfPath);
    if (!$download['ok']) {
        $records[] = [
            'id' => $id,
            'label' => $entry['label'] ?? $id,
            'kind' => $entry['kind'] ?? '',
            'url' => $entry['url'],
            'download' => $download,
            'modes' => [],
        ];
        continue;
    }

    $bytes = file_get_contents($pdfPath);
    if (!is_string($bytes)) {
        $records[] = [
            'id' => $id,
            'label' => $entry['label'] ?? $id,
            'kind' => $entry['kind'] ?? '',
            'url' => $entry['url'],
            'download' => ['ok' => false, 'error' => 'Unable to read cached PDF.'],
            'modes' => [],
        ];
        continue;
    }

    $modeRecords = [];
    foreach ($modes as $mode => $readerOptions) {
        fwrite(STDERR, "   {$mode}\n");
        $modeRecords[$mode] = convert_pdf_for_review($bytes, $entry, $entryOutDir, $mode, $readerOptions);
    }

    $records[] = [
        'id' => $id,
        'label' => $entry['label'] ?? $id,
        'kind' => $entry['kind'] ?? '',
        'url' => $entry['url'],
        'expectedTables' => $entry['expectedTables'] ?? null,
        'notes' => $entry['notes'] ?? '',
        'download' => $download,
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

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create {$dir}.");
    }
}

/**
 * @return array<string, mixed>
 */
function download_pdf(string $url, string $path): array
{
    $tmp = $path . '.tmp';
    $cmd = [
        'curl',
        '-L',
        '--http1.1',
        '--fail',
        '--silent',
        '--show-error',
        '--max-time',
        '90',
        '--retry',
        '2',
        '--header',
        'Accept: application/pdf,*/*',
        '--user-agent',
        'port-libs-pdf-corpus/1.0 (+https://github.com/adamziel/port-libs)',
        '--output',
        $tmp,
        $url,
    ];
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['ok' => false, 'error' => 'Unable to start curl.'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        @unlink($tmp);
        return ['ok' => false, 'exit' => $exit, 'stdout' => trim((string) $stdout), 'stderr' => trim((string) $stderr)];
    }
    $bytes = is_file($tmp) ? (int) filesize($tmp) : 0;
    $header = $bytes > 4 ? file_get_contents($tmp, false, null, 0, 5) : '';
    if ($bytes < 5 || $header !== '%PDF-') {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Downloaded file is not a PDF.', 'bytes' => $bytes];
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Unable to move downloaded PDF into cache.'];
    }

    return ['ok' => true, 'cached' => false, 'bytes' => $bytes];
}

/**
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $readerOptions
 * @return array<string, mixed>
 */
function convert_pdf_for_review(string $bytes, array $entry, string $outDir, string $mode, array $readerOptions): array
{
    $started = microtime(true);
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(25);
    }
    try {
        $document = PandocConverter::read($bytes, 'pdf', $readerOptions);
        $meta = $document->attr('meta', []);
        $plainText = plain_text($document);
        $html = PandocConverter::write($document, 'html');
        $wordpress = PandocConverter::write($document, 'wordpress');
        $base = $outDir . '/' . $mode;
        file_put_contents($base . '.plain.txt', $plainText);
        file_put_contents($base . '.html', $html);
        file_put_contents($base . '.wordpress.html', $wordpress);
        $nativeError = null;
        try {
            file_put_contents($base . '.native', PandocConverter::write($document, 'native'));
        } catch (Throwable $nativeThrowable) {
            $nativeError = $nativeThrowable::class . ': ' . $nativeThrowable->getMessage();
        }

        $outputs = [
            'plain' => relative_path($base . '.plain.txt'),
            'html' => relative_path($base . '.html'),
            'wordpress' => relative_path($base . '.wordpress.html'),
        ];
        if ($nativeError === null) {
            $outputs['native'] = relative_path($base . '.native');
        }

        $record = [
            'ok' => true,
            'seconds' => round(microtime(true) - $started, 3),
            'outputs' => $outputs,
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
            'metadata' => [
                'pdfDetectedTables' => $meta['pdfDetectedTables'] ?? null,
                'pdfGeometryTables' => $meta['pdfGeometryTables'] ?? null,
                'pdfGeometryTablesEnabled' => $meta['pdfGeometryTablesEnabled'] ?? null,
                'pdfTableReconstruction' => $meta['pdfTableReconstruction'] ?? null,
                'pdfTextRepair' => $meta['pdfTextRepair'] ?? null,
                'pdfTextRepairSource' => $meta['pdfTextRepairSource'] ?? null,
                'pdfLineOrientedRegions' => $meta['pdfLineOrientedRegions'] ?? null,
                'pdfInterGlyphSpacingRepairs' => $meta['pdfInterGlyphSpacingRepairs'] ?? null,
                'pdfInferredHeadingBoundaries' => $meta['pdfInferredHeadingBoundaries'] ?? null,
                'pdfMaxPages' => $meta['pdfMaxPages'] ?? null,
                'pdfTextLines' => $meta['pdfTextLines'] ?? null,
                'pdfPositionedTextRuns' => $meta['pdfPositionedTextRuns'] ?? null,
                'pdfWarnings' => $meta['pdfWarnings'] ?? [],
            ],
            'spacingReview' => spacing_review($plainText),
            'reviewStatus' => review_status($entry, $document, $plainText),
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

function relative_path(string $path): string
{
    return str_replace(getcwd() . '/', '', $path);
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
function review_status(array $entry, AstNode $document, string $plainText): array
{
    $expectedTables = (int) ($entry['expectedTables'] ?? 0);
    $tableCount = count_nodes($document, 'table');
    $spacing = spacing_review($plainText);
    $issues = [];
    if ($expectedTables > 0 && $tableCount < 1) {
        $issues[] = 'expected-table-missing';
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

    $criteria = is_array($entry['success'] ?? null) ? $entry['success'] : [];
    $metrics = [
        'textBytes' => strlen($plainText),
        'paragraphs' => count_nodes($document, 'paragraph'),
        'headings' => count_nodes($document, 'heading'),
        'tables' => $tableCount,
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

    return [
        'approvedByHeuristic' => $issues === [],
        'issues' => $issues,
        'criteria' => $criteria,
        'metrics' => $metrics,
        'checks' => $checks,
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
        'downloaded' => 0,
        'convertedModes' => 0,
        'failedDownloads' => 0,
        'failedConversions' => 0,
        'geometryOnWithTables' => 0,
        'geometryOnHeuristicApproved' => 0,
    ];
    foreach ($records as $record) {
        if (($record['download']['ok'] ?? false) === true) {
            $summary['downloaded']++;
        } else {
            $summary['failedDownloads']++;
        }
        foreach (($record['modes'] ?? []) as $mode => $modeRecord) {
            if (($modeRecord['ok'] ?? false) !== true) {
                $summary['failedConversions']++;
                continue;
            }
            $summary['convertedModes']++;
            if ($mode === 'geometry-on' && (int) ($modeRecord['tableCount'] ?? 0) > 0) {
                $summary['geometryOnWithTables']++;
            }
            if ($mode === 'geometry-on' && (($modeRecord['reviewStatus']['approvedByHeuristic'] ?? false) === true)) {
                $summary['geometryOnHeuristicApproved']++;
            }
        }
    }

    return $summary;
}

/**
 * @param array<string, mixed> $report
 */
function render_html_report(array $report): string
{
    $rows = '';
    foreach ($report['records'] as $record) {
        $cells = '';
        foreach (['geometry-on', 'repair-only'] as $mode) {
            $modeRecord = $record['modes'][$mode] ?? null;
            if (!is_array($modeRecord) || ($modeRecord['ok'] ?? false) !== true) {
                $cells .= '<td class="bad">failed</td>';
                continue;
            }
            $issues = implode(', ', $modeRecord['reviewStatus']['issues'] ?? []);
            $issues = $issues === '' ? 'none' : $issues;
            $score = (int) ($modeRecord['spacingReview']['heuristicScore'] ?? 0);
            $wordpress = htmlspecialchars((string) ($modeRecord['outputs']['wordpress'] ?? ''), ENT_QUOTES);
            $cells .= '<td>'
                . '<strong>tables:</strong> ' . (int) ($modeRecord['tableCount'] ?? 0)
                . '<br><strong>lists:</strong> ' . (int) ($modeRecord['listCount'] ?? 0)
                . '<br><strong>spacing score:</strong> ' . $score
                . '<br><strong>issues:</strong> ' . htmlspecialchars($issues, ENT_QUOTES)
                . '<br><a href="' . $wordpress . '">WordPress blocks</a>'
                . '</td>';
        }
        $download = (($record['download']['ok'] ?? false) === true) ? 'ok' : 'failed';
        $rows .= '<tr><th>' . htmlspecialchars((string) $record['id'], ENT_QUOTES) . '</th>'
            . '<td>' . htmlspecialchars((string) ($record['kind'] ?? ''), ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($download, ENT_QUOTES) . '</td>'
            . $cells
            . '</tr>';
    }

    return '<!doctype html><meta charset="utf-8"><title>PDF corpus report</title>'
        . '<style>body{font:14px system-ui,sans-serif;margin:24px;color:#17202a}table{border-collapse:collapse;width:100%}th,td{border:1px solid #d8dee4;padding:8px;text-align:left;vertical-align:top}th{background:#f6f8fa}.bad{color:#9b1c1c;background:#fff5f5}code{white-space:pre-wrap}</style>'
        . '<h1>PDF corpus report</h1>'
        . '<p>Generated ' . htmlspecialchars((string) $report['generatedAt'], ENT_QUOTES) . '. This is a triage report: the spacing score highlights suspicious text extraction artifacts for human review, not a proof of correctness.</p>'
        . '<pre><code>' . htmlspecialchars((string) json_encode($report['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES) . '</code></pre>'
        . '<table><thead><tr><th>PDF</th><th>Kind</th><th>Download</th><th>Geometry on</th><th>Repair only</th></tr></thead><tbody>'
        . $rows
        . '</tbody></table>';
}
