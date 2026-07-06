<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$mode = 'canary';
$outputDir = $repoRoot . '/.upstream-cache/pandoc-reader-external-corpus';
$manifestPath = null;
$runReport = false;
$pandocBin = getenv('PANDOC_BIN') ?: 'pandoc';
$reportHtml = $repoRoot . '/lanes/pandoc/reports/haskell-pandoc-external-corpus.html';
$reportJson = $repoRoot . '/lanes/pandoc/reports/haskell-pandoc-external-corpus.json';
$limit = 0;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-reader-external-corpus.php [--mode=canary|representative|stress] [--output-dir=PATH] [--manifest=PATH] [--limit=N] [--run-report] [--pandoc-bin=PATH] [--output-html=PATH] [--output-json=PATH]

Downloads a curated external document corpus into an ignored cache directory and
writes a manifest compatible with tools/pandoc-reader-executable-sample-report.php.
The manifest records source URLs, feature tags, byte sizes, and sha256 pins.

TXT);
        exit(0);
    }

    if ($argument === '--run-report') {
        $runReport = true;
        continue;
    }
    if (str_starts_with($argument, '--mode=')) {
        $mode = substr($argument, strlen('--mode='));
        continue;
    }
    if (str_starts_with($argument, '--output-dir=')) {
        $outputDir = normalizePath($repoRoot, substr($argument, strlen('--output-dir=')));
        continue;
    }
    if (str_starts_with($argument, '--manifest=')) {
        $manifestPath = normalizePath($repoRoot, substr($argument, strlen('--manifest=')));
        continue;
    }
    if (str_starts_with($argument, '--limit=')) {
        $rawLimit = substr($argument, strlen('--limit='));
        if (!ctype_digit($rawLimit)) {
            fwrite(STDERR, "--limit must be a non-negative integer\n");
            exit(2);
        }
        $limit = (int) $rawLimit;
        continue;
    }
    if (str_starts_with($argument, '--pandoc-bin=')) {
        $pandocBin = substr($argument, strlen('--pandoc-bin='));
        continue;
    }
    if (str_starts_with($argument, '--output-html=')) {
        $reportHtml = normalizePath($repoRoot, substr($argument, strlen('--output-html=')));
        continue;
    }
    if (str_starts_with($argument, '--output-json=')) {
        $reportJson = normalizePath($repoRoot, substr($argument, strlen('--output-json=')));
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

if (!in_array($mode, ['canary', 'representative', 'stress'], true)) {
    fwrite(STDERR, "--mode must be canary, representative, or stress\n");
    exit(2);
}

$manifestPath ??= rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'manifest.json';
$inputDir = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'inputs';
ensureDirectory($inputDir);

$sources = externalCorpusSources($mode);
if ($limit > 0) {
    $sources = array_slice($sources, 0, $limit);
}

$entries = [];
$fetchFailures = [];
foreach ($sources as $source) {
    try {
        $entry = fetchExternalCorpusSource($repoRoot, $inputDir, $source);
        $entries[] = $entry;
        fwrite(STDOUT, 'fetched ' . $entry['id'] . ' ' . $entry['bytes'] . " bytes\n");
    } catch (Throwable $exception) {
        $fetchFailures[] = [
            'id' => (string) ($source['id'] ?? 'unknown'),
            'url' => (string) ($source['url'] ?? ''),
            'error' => $exception::class . ': ' . $exception->getMessage(),
        ];
        fwrite(STDERR, 'failed ' . (string) ($source['id'] ?? 'unknown') . ': ' . $exception->getMessage() . "\n");
    }
}

$manifest = [
    'title' => 'External Pandoc Reader Corpus',
    'description' => 'Fetched public documents selected to assess local reader behavior beyond checked-in upstream fixtures.',
    'schemaVersion' => 1,
    'mode' => $mode,
    'generatedAt' => gmdate('c'),
    'sourceCount' => count($sources),
    'entryCount' => count($entries),
    'fetchFailureCount' => count($fetchFailures),
    'inputDirectory' => relativePath($repoRoot, $inputDir),
    'entries' => array_map(static function (array $entry): array {
        $manifestEntry = $entry;
        unset($manifestEntry['bytes']);

        return $manifestEntry;
    }, $entries),
    'fetchFailures' => $fetchFailures,
];

writeJson($manifestPath, $manifest);
fwrite(STDOUT, "Wrote {$manifestPath}\n");
fwrite(STDOUT, sprintf("External corpus: mode=%s entries=%d fetchFailures=%d\n", $mode, count($entries), count($fetchFailures)));

if ($runReport) {
    $reportTool = $repoRoot . '/tools/pandoc-reader-executable-sample-report.php';
    $command = [
        PHP_BINARY,
        '-d',
        'memory_limit=' . (getenv('PANDOC_SAMPLE_REPORT_MEMORY_LIMIT') ?: '1024M'),
        $reportTool,
        '--manifest=' . $manifestPath,
        '--pandoc-bin=' . $pandocBin,
        '--output-html=' . $reportHtml,
        '--output-json=' . $reportJson,
    ];
    $result = runCommand($command);
    fwrite(STDOUT, $result['stdout']);
    fwrite(STDERR, $result['stderr']);
    if ($result['exitCode'] !== 0) {
        exit($result['exitCode']);
    }
}

/**
 * @return list<array<string, mixed>>
 */
function externalCorpusSources(string $mode): array
{
    $canary = [
        source('wordpress-gutenberg-readme', 'markdown', 'md', 'https://raw.githubusercontent.com/WordPress/gutenberg/trunk/README.md', 'gfm', 'wordpress-project-docs', 'WordPress Gutenberg repository README.', ['wordpress', 'gfm', 'readme', 'badges', 'images', 'links'], ['format' => 'gfm']),
        source('kubernetes-concepts-overview', 'markdown', 'md', 'https://raw.githubusercontent.com/kubernetes/website/main/content/en/docs/concepts/overview/_index.md', 'gfm', 'technical-docs', 'Kubernetes concepts overview page.', ['gfm', 'front-matter', 'technical-docs', 'links'], ['format' => 'gfm']),
        source('mdbook-markdown-format', 'markdown', 'md', 'https://raw.githubusercontent.com/rust-lang/mdBook/master/guide/src/format/markdown.md', 'gfm', 'book-docs', 'mdBook Markdown format guide.', ['gfm', 'book-docs', 'code-blocks', 'tables'], ['format' => 'gfm']),
        source('w3c-mathml-core', 'html', 'html', 'https://www.w3.org/TR/mathml-core/', 'html', 'standards-html', 'W3C MathML Core recommendation page.', ['html', 'mathml', 'standards', 'tables', 'links']),
        source('whatwg-html-introduction', 'html', 'html', 'https://html.spec.whatwg.org/multipage/introduction.html', 'html', 'standards-html', 'WHATWG HTML introduction page.', ['html', 'spec', 'sections', 'links']),
        source('gutenberg-pride-and-prejudice', 'epub', 'epub', 'https://www.gutenberg.org/ebooks/1342.epub.noimages', 'epub', 'public-domain-ebook', 'Project Gutenberg EPUB without images.', ['epub', 'gutenberg', 'metadata', 'spine', 'chapters']),
        source('rse-slideshow-sample', 'pptx', 'pptx', 'https://raw.githubusercontent.com/rse/slideshow/master/sample.pptx', 'pptx', 'public-pptx-sample', 'Small public PPTX slideshow sample.', ['pptx', 'slides', 'text', 'openxml']),
        source('frictionless-sample-xlsx', 'xlsx', 'xlsx', 'https://raw.githubusercontent.com/frictionlessdata/datasets/main/files/excel/sample-1-sheet.xlsx', 'xlsx', 'public-xlsx-sample', 'Small Frictionless Data XLSX sample workbook.', ['xlsx', 'workbook', 'sheet', 'table']),
        source('plotly-usa-states-csv', 'csv', 'csv', 'https://raw.githubusercontent.com/plotly/datasets/master/2014_usa_states.csv', 'csv', 'public-csv-dataset', 'Plotly state-level CSV dataset.', ['csv', 'header-row', 'numeric-cells']),
        source('plotly-usa-states-tsv', 'tsv', 'tsv', 'https://raw.githubusercontent.com/plotly/datasets/master/2014_usa_states.csv', 'tsv', 'generated-from-public-csv', 'TSV derivative of the Plotly state-level CSV dataset.', ['tsv', 'header-row', 'numeric-cells'], [], 'csv-to-tsv'),
        source('linux-bootparam-manpage', 'man', '7', 'https://raw.githubusercontent.com/mkerrisk/man-pages/master/man7/bootparam.7', 'man', 'linux-man-pages', 'Linux man-pages bootparam(7).', ['man', 'roff', 'sections', 'option-lists']),
    ];

    $representative = [
        source('node-fs-api', 'markdown', 'md', 'https://raw.githubusercontent.com/nodejs/node/main/doc/api/fs.md', 'gfm', 'api-docs', 'Node.js File System API documentation.', ['gfm', 'api-docs', 'code-blocks', 'tables', 'anchors'], ['format' => 'gfm']),
        source('laravel-installation', 'markdown', 'md', 'https://raw.githubusercontent.com/laravel/docs/12.x/installation.md', 'gfm', 'framework-docs', 'Laravel installation documentation.', ['gfm', 'front-matter', 'code-blocks', 'callouts'], ['format' => 'gfm']),
        source('vue-introduction', 'markdown', 'md', 'https://raw.githubusercontent.com/vuejs/docs/main/src/guide/introduction.md', 'gfm', 'framework-docs', 'Vue introduction guide.', ['gfm', 'front-matter', 'html-islands', 'code-blocks'], ['format' => 'gfm']),
        source('w3c-html-aria', 'html', 'html', 'https://www.w3.org/TR/html-aria/', 'html', 'standards-html', 'ARIA in HTML recommendation page.', ['html', 'standards', 'tables', 'definition-lists']),
        source('gutenberg-ulysses', 'epub', 'epub', 'https://www.gutenberg.org/ebooks/4300.epub.noimages', 'epub', 'public-domain-ebook', 'Project Gutenberg Ulysses EPUB without images.', ['epub', 'gutenberg', 'large-document', 'chapters']),
        source('linux-proc-manpage', 'man', '5', 'https://raw.githubusercontent.com/mkerrisk/man-pages/master/man5/proc.5', 'man', 'linux-man-pages', 'Linux man-pages proc(5).', ['man', 'roff', 'large-document', 'tables']),
    ];

    $stress = [
        source('covid-countries-aggregated-csv', 'csv', 'csv', 'https://raw.githubusercontent.com/datasets/covid-19/main/data/countries-aggregated.csv', 'csv', 'public-csv-dataset', 'Large COVID-19 country aggregate CSV dataset.', ['csv', 'large-document', 'dates', 'numeric-cells']),
    ];

    return match ($mode) {
        'canary' => $canary,
        'representative' => array_merge($canary, $representative),
        'stress' => array_merge($canary, $representative, $stress),
    };
}

/**
 * @param list<string> $features
 * @param array<string, mixed> $readerOptions
 * @return array<string, mixed>
 */
function source(
    string $id,
    string $format,
    string $extension,
    string $url,
    string $pandocFormat,
    string $sourceKind,
    string $description,
    array $features,
    array $readerOptions = [],
    ?string $transform = null
): array {
    return [
        'id' => $id,
        'format' => $format,
        'extension' => $extension,
        'url' => $url,
        'pandocFormat' => $pandocFormat,
        'sourceKind' => $sourceKind,
        'description' => $description,
        'features' => $features,
        'readerOptions' => $readerOptions,
        'transform' => $transform,
    ];
}

/**
 * @param array<string, mixed> $source
 * @return array<string, mixed>
 */
function fetchExternalCorpusSource(string $repoRoot, string $inputDir, array $source): array
{
    $id = (string) $source['id'];
    $extension = (string) $source['extension'];
    $path = $inputDir . DIRECTORY_SEPARATOR . safeFileName($id) . '.' . $extension;
    $fetch = fetchUrl((string) $source['url']);
    $bytes = applyTransform($fetch['bytes'], is_string($source['transform'] ?? null) ? $source['transform'] : null);
    writeBytes($path, $bytes);

    $entry = [
        'id' => $id,
        'format' => (string) $source['format'],
        'path' => relativePath($repoRoot, $path),
        'pandocFormat' => (string) $source['pandocFormat'],
        'sourceKind' => (string) $source['sourceKind'],
        'sourceUrl' => (string) $source['url'],
        'description' => (string) $source['description'],
        'features' => array_values(array_map('strval', is_array($source['features'] ?? null) ? $source['features'] : [])),
        'sha256' => hash('sha256', $bytes),
        'bytes' => strlen($bytes),
        'etag' => $fetch['etag'],
        'lastModified' => $fetch['lastModified'],
    ];
    if (is_array($source['readerOptions'] ?? null) && $source['readerOptions'] !== []) {
        $entry['readerOptions'] = $source['readerOptions'];
    }
    if (is_string($source['transform'] ?? null)) {
        $entry['transform'] = $source['transform'];
    }

    return $entry;
}

/**
 * @return array{bytes:string, etag:?string, lastModified:?string}
 */
function fetchUrl(string $url): array
{
    $headersPath = tempnam(sys_get_temp_dir(), 'pandoc-external-corpus-headers-');
    if (!is_string($headersPath)) {
        throw new RuntimeException('Unable to allocate header temp file');
    }

    $result = runCommand([
        'curl',
        '--location',
        '--fail',
        '--silent',
        '--show-error',
        '--max-time',
        '60',
        '--dump-header',
        $headersPath,
        '--user-agent',
        'port-libs-pandoc-external-corpus',
        $url,
    ]);
    $headers = is_file($headersPath) ? (string) file_get_contents($headersPath) : '';
    @unlink($headersPath);

    if ($result['exitCode'] !== 0) {
        $stderr = trim($result['stderr']);
        throw new RuntimeException('Unable to fetch ' . $url . ($stderr === '' ? '' : ': ' . $stderr));
    }

    return [
        'bytes' => $result['stdout'],
        'etag' => responseHeader($headers, 'etag'),
        'lastModified' => responseHeader($headers, 'last-modified'),
    ];
}

function applyTransform(string $bytes, ?string $transform): string
{
    if ($transform === null) {
        return $bytes;
    }
    if ($transform === 'csv-to-tsv') {
        $rows = [];
        $handle = fopen('php://temp', 'r+');
        if (!is_resource($handle)) {
            throw new RuntimeException('Unable to allocate CSV transform buffer');
        }
        fwrite($handle, $bytes);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rows[] = implode("\t", array_map(static fn (?string $cell): string => str_replace(["\t", "\r", "\n"], ' ', (string) $cell), $row));
        }
        fclose($handle);

        return implode("\n", $rows) . "\n";
    }

    throw new RuntimeException("Unsupported transform: {$transform}");
}

function responseHeader(string $headers, string $name): ?string
{
    $name = strtolower($name);
    $value = null;
    foreach (preg_split('/\r\n|\r|\n/', $headers) ?: [] as $line) {
        [$headerName, $headerValue] = array_pad(explode(':', $line, 2), 2, '');
        if (strtolower(trim($headerName)) === $name) {
            $value = trim($headerValue);
        }
    }

    return $value === '' ? null : $value;
}

function safeFileName(string $name): string
{
    $safe = preg_replace('/[^a-z0-9_.-]+/i', '-', strtolower($name));

    return is_string($safe) && $safe !== '' ? trim($safe, '-') : 'source';
}

function normalizePath(string $repoRoot, string $path): string
{
    if ($path === '') {
        throw new InvalidArgumentException('Path must not be empty');
    }

    return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $repoRoot . DIRECTORY_SEPARATOR . $path;
}

function relativePath(string $base, string $path): string
{
    $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create directory '{$directory}'.");
    }
}

function writeBytes(string $path, string $bytes): void
{
    ensureDirectory(dirname($path));
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException("Unable to write '{$path}'.");
    }
}

/**
 * @param array<string, mixed> $value
 */
function writeJson(string $path, array $value): void
{
    writeBytes($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
}

/**
 * @param list<string> $command
 * @return array{stdout:string, stderr:string, exitCode:int}
 */
function runCommand(array $command): array
{
    $commandLine = implode(' ', array_map('escapeshellarg', $command));
    $pipes = [];
    $process = proc_open(
        $commandLine,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    if (!is_resource($process)) {
        return ['stdout' => '', 'stderr' => 'proc_open failed', 'exitCode' => 127];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'exitCode' => $exitCode,
    ];
}
