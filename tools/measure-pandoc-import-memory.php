<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;

/**
 * Measure one isolated import path without conflating it with the test runner.
 *
 * Example:
 *   php -d memory_limit=512M tools/measure-pandoc-import-memory.php \
 *     --input=/tmp/example.csv --from=csv --to=wordpress --compact-ast=on
 */
$options = getopt('', [
    'input:',
    'from:',
    'to::',
    'mode::',
    'compact-ast::',
    'reader-options::',
    'writer-options::',
]);

$input = isset($options['input']) ? (string) $options['input'] : '';
$from = isset($options['from']) ? (string) $options['from'] : '';
$to = isset($options['to']) ? (string) $options['to'] : 'wordpress';
$mode = isset($options['mode']) ? (string) $options['mode'] : 'file';
if ($input === '' || $from === '' || !is_file($input) || !in_array($mode, ['bytes', 'file'], true)) {
    fwrite(STDERR, "Usage: php tools/measure-pandoc-import-memory.php --input=PATH --from=FORMAT [--to=wordpress] [--mode=bytes|file] [--compact-ast=auto|on|off] [--reader-options=JSON] [--writer-options=JSON]\n");
    exit(2);
}

$decodeOptions = static function (string $name) use ($options): array {
    $encoded = $options[$name] ?? '';
    if ($encoded === '' || $encoded === false) {
        return [];
    }
    try {
        $decoded = json_decode((string) $encoded, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "Invalid --{$name} JSON: {$exception->getMessage()}\n");
        exit(2);
    }
    if (!is_array($decoded)) {
        fwrite(STDERR, "--{$name} JSON must decode to an object.\n");
        exit(2);
    }

    return $decoded;
};

$readerOptions = $decodeOptions('reader-options');
$writerOptions = $decodeOptions('writer-options');
$compactAst = isset($options['compact-ast']) ? strtolower(trim((string) $options['compact-ast'])) : 'auto';
if (!in_array($compactAst, ['auto', 'on', 'off'], true)) {
    fwrite(STDERR, "--compact-ast must be auto, on, or off.\n");
    exit(2);
}
if ($compactAst === 'on') {
    $readerOptions['compactAst'] = true;
} elseif ($compactAst === 'off') {
    $readerOptions['compactAst'] = false;
}

if (function_exists('memory_reset_peak_usage')) {
    memory_reset_peak_usage();
}

$baselineUsed = memory_get_usage();
$baseline = memory_get_usage(true);
$bytes = null;
if ($mode === 'bytes') {
    $bytes = file_get_contents($input);
    if (!is_string($bytes)) {
        fwrite(STDERR, "Unable to read {$input}.\n");
        exit(1);
    }
    $afterInputUsed = memory_get_usage();
    $afterInput = memory_get_usage(true);
    $document = PandocConverter::read($bytes, $from, $readerOptions);
} else {
    $afterInputUsed = memory_get_usage();
    $afterInput = memory_get_usage(true);
    $document = PandocConverter::readFile($input, $from, $readerOptions);
}

$afterReadUsed = memory_get_usage();
$afterRead = memory_get_usage(true);
$output = PandocConverter::write($document, $to, $writerOptions);
$afterWriteUsed = memory_get_usage();
$afterWrite = memory_get_usage(true);

$nodeCount = 0;
$walk = static function (AstNode $node) use (&$walk, &$nodeCount): void {
    $nodeCount++;
    foreach ($node->children as $child) {
        $walk($child);
    }
};
$walk($document);

echo json_encode([
    'input' => $input,
    'from' => $from,
    'to' => $to,
    'mode' => $mode,
    'compactAst' => $compactAst,
    'sourceBytes' => filesize($input),
    'baselineUsedBytes' => $baselineUsed,
    'baselineBytes' => $baseline,
    'afterInputUsedBytes' => $afterInputUsed,
    'afterInputBytes' => $afterInput,
    'afterReadUsedBytes' => $afterReadUsed,
    'afterReadBytes' => $afterRead,
    'afterWriteUsedBytes' => $afterWriteUsed,
    'afterWriteBytes' => $afterWrite,
    'afterReadUsedDeltaBytes' => $afterReadUsed - $baselineUsed,
    'afterReadDeltaBytes' => $afterRead - $baseline,
    'afterWriteUsedDeltaBytes' => $afterWriteUsed - $baselineUsed,
    'afterWriteDeltaBytes' => $afterWrite - $baseline,
    'peakUsedBytes' => memory_get_peak_usage(),
    'peakBytes' => memory_get_peak_usage(true),
    'nodeCount' => $nodeCount,
    'outputBytes' => strlen($output),
    'outputSha256' => hash('sha256', $output),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
