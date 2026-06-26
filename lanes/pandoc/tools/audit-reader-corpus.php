<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = dirname(__DIR__, 3);
$paths = array_slice($argv, 1);
if ($paths === []) {
    $paths = [
        $root . '/lanes/pandoc/fixtures',
        $root . '/lanes/difftastic/fixtures',
    ];
}

$extensionFormats = [
    'bib' => 'bibtex',
    'biblatex' => 'biblatex',
    'csv' => 'csv',
    'latex' => 'latex',
    'mediawiki' => 'mediawiki',
    'pptx' => 'pptx',
    'ris' => 'ris',
    'rst' => 'rst',
    'tex' => 'latex',
    'tsv' => 'tsv',
    'wiki' => 'mediawiki',
    'xlsx' => 'xlsx',
    'xml' => 'xml',
];

$files = [];
foreach ($paths as $path) {
    $absolute = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $root . DIRECTORY_SEPARATOR . $path;
    if (is_file($absolute)) {
        $files[$absolute] = true;
        continue;
    }
    if (!is_dir($absolute)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile()) {
            $files[$fileInfo->getPathname()] = true;
        }
    }
}

$rows = [];
foreach (array_keys($files) as $file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!isset($extensionFormats[$extension])) {
        continue;
    }

    $format = $extensionFormats[$extension];
    if (!PandocConverter::canRead($format)) {
        continue;
    }

    $bytes = file_get_contents($file);
    if (!is_string($bytes)) {
        $rows[] = [
            'path' => relativePath($file, $root),
            'format' => $format,
            'status' => 'error',
            'error' => 'file-read-failed',
        ];
        continue;
    }

    try {
        $document = PandocConverter::read($bytes, $format);
        $blocks = PandocConverter::write($document, 'blocks');
        $counts = nodeCounts($document);
        $rows[] = [
            'path' => relativePath($file, $root),
            'format' => $format,
            'status' => 'ok',
            'bytes' => strlen($bytes),
            'blockBytes' => strlen($blocks),
            'topLevelBlocks' => count($document->children),
            'rawNodes' => rawNodeCount($document),
            'reviewSignals' => reviewSignals($blocks),
            'nodeTypes' => $counts,
        ];
    } catch (Throwable $exception) {
        $rows[] = [
            'path' => relativePath($file, $root),
            'format' => $format,
            'status' => 'error',
            'error' => $exception::class . ': ' . $exception->getMessage(),
        ];
    }
}

usort($rows, static fn (array $a, array $b): int => [$a['format'], $a['path']] <=> [$b['format'], $b['path']]);

$summary = [
    'paths' => array_map(static fn (string $path): string => relativePath(str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $root . DIRECTORY_SEPARATOR . $path, $root), $paths),
    'supportedFiles' => count($rows),
    'ok' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'ok')),
    'errors' => count(array_filter($rows, static fn (array $row): bool => $row['status'] !== 'ok')),
    'rawNodes' => array_sum(array_map(static fn (array $row): int => (int) ($row['rawNodes'] ?? 0), $rows)),
];

echo json_encode(['summary' => $summary, 'files' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function relativePath(string $path, string $root): string
{
    $realRoot = realpath($root) ?: $root;
    $realPath = realpath($path) ?: $path;
    if ($realPath === $realRoot) {
        return '.';
    }
    if (str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)) {
        return substr($realPath, strlen($realRoot) + 1);
    }

    return $path;
}

/**
 * @return array<string, int>
 */
function nodeCounts(AstNode $node): array
{
    $counts = [$node->type => 1];
    foreach ($node->children as $child) {
        foreach (nodeCounts($child) as $type => $count) {
            $counts[$type] = ($counts[$type] ?? 0) + $count;
        }
    }
    ksort($counts);

    return $counts;
}

function rawNodeCount(AstNode $node): int
{
    $count = str_starts_with($node->type, 'raw_') ? 1 : 0;
    foreach ($node->children as $child) {
        $count += rawNodeCount($child);
    }

    return $count;
}

/**
 * @return array<string, int>
 */
function reviewSignals(string $blocks): array
{
    $signals = [
        'rawBlocks' => substr_count($blocks, 'pandoc-raw-'),
        'sourceFormatAttrs' => substr_count($blocks, 'data-pandoc-source-format='),
        'xmlElements' => substr_count($blocks, 'data-xml-element='),
        'genericXmlContainers' => substr_count($blocks, 'class="xml-element'),
        'htmlBlocks' => substr_count($blocks, '<!-- wp:html -->'),
        'codeBlocks' => substr_count($blocks, '<!-- wp:code -->'),
        'tables' => substr_count($blocks, '<!-- wp:table -->'),
    ];

    return array_filter($signals, static fn (int $count): bool => $count > 0);
}
