<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$archivePath = $argv[1] ?? $root . '/pandoc-showcase/playground/port-libs-playground-converter.zip';
$manifestPath = $argv[2] ?? $root . '/pandoc-showcase/playground/port-libs-playground-converter.manifest.json';
$comparisonArchivePath = $argv[3] ?? null;
$comparisonManifestPath = $argv[4] ?? null;
const PLPC_VERIFICATION_UPLOAD_LIMIT = (8 * 1024 * 1024) - (64 * 1024);

if (!is_file($archivePath) || !is_file($manifestPath)) {
    fwrite(STDERR, "The production plugin ZIP and its manifest must both exist.\n");
    exit(1);
}

try {
    $sourcePluginPath = $root . '/tools/playground-converter-plugin/port-libs-playground-converter.php';
    $sourcePlugin = file_get_contents($sourcePluginPath);
    if (!is_string($sourcePlugin)) {
        throw new RuntimeException('The production plugin source cannot be read for header verification.');
    }
    $expectedPluginHeader = distribution_wordpress_plugin_header_comment($sourcePlugin, $sourcePluginPath);
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || ($manifest['schemaVersion'] ?? null) !== 1 || !is_array($manifest['entries'] ?? null)) {
        throw new RuntimeException('The production plugin manifest has an unsupported schema.');
    }
    if (($manifest['archive'] ?? null) !== basename($archivePath)) {
        throw new RuntimeException('The manifest names a different production archive.');
    }
    $archiveBytes = filesize($archivePath);
    if (!is_int($archiveBytes) || $archiveBytes !== ($manifest['archiveBytes'] ?? null)) {
        throw new RuntimeException('The production archive size does not match its manifest.');
    }
    if ($archiveBytes > PLPC_VERIFICATION_UPLOAD_LIMIT) {
        throw new RuntimeException(sprintf(
            'The production archive exceeds the existing upload limit: %d > %d bytes.',
            $archiveBytes,
            PLPC_VERIFICATION_UPLOAD_LIMIT
        ));
    }
    $archiveHash = hash_file('sha256', $archivePath);
    if (!is_string($archiveHash)
        || !is_string($manifest['archiveSha256'] ?? null)
        || !hash_equals($manifest['archiveSha256'], $archiveHash)) {
        throw new RuntimeException('The production archive hash does not match its manifest.');
    }

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException('The production plugin ZIP cannot be opened.');
    }
    $actualEntries = [];
    $pluginContents = null;
    try {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $contents = $zip->getFromIndex($index);
            if (!is_string($name) || $name === '' || !is_string($contents)) {
                throw new RuntimeException("The production ZIP entry at index {$index} cannot be read.");
            }
            $actualEntries[$name] = [
                'bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ];
            if (preg_match('#/(?:tests?)(?:/|$)#i', $name) === 1
                || preg_match('/(?:^|\/)\w+Test\.php$/', $name) === 1) {
                throw new RuntimeException("The production plugin ZIP contains a test path: {$name}.");
            }
            if (str_ends_with($name, '.mjs')) {
                throw new RuntimeException("The production plugin ZIP contains an unrevised module path: {$name}.");
            }
            if (str_ends_with($name, '.php')) {
                $comments = distribution_php_comment_tokens($contents);
                if ($name === 'port-libs-playground-converter/port-libs-playground-converter.php') {
                    $pluginContents = $contents;
                    if ($comments !== [$expectedPluginHeader]) {
                        throw new RuntimeException('The minified main plugin did not preserve exactly its discoverable header comment.');
                    }
                } elseif ($comments !== []) {
                    throw new RuntimeException("The production PHP entry still contains comments: {$name}.");
                }
            }
        }
    } finally {
        $zip->close();
    }
    ksort($actualEntries, SORT_STRING);
    $expectedEntries = $manifest['entries'];
    ksort($expectedEntries, SORT_STRING);
    if ($actualEntries !== $expectedEntries) {
        throw new RuntimeException('The production ZIP content inventory does not match its manifest.');
    }

    $required = [
        'port-libs-playground-converter/port-libs-playground-converter.php',
        'port-libs-playground-converter/assets/admin-importer.js',
        'port-libs-playground-converter/assets/pdfjs-form-rasterizer.js',
        'port-libs-playground-converter/assets/pdfjs-facts-provider.js',
        'port-libs-playground-converter/assets/vendor/pdfjs/pdf.min.js',
        'port-libs-playground-converter/lanes/pandoc/src/PdfReader.php',
        'port-libs-playground-converter/lanes/markerpdf/src/NativePdfFactsProvider.php',
    ];
    foreach ($required as $name) {
        if (!isset($actualEntries[$name])) {
            throw new RuntimeException("The production plugin ZIP is missing {$name}.");
        }
    }
    if (!is_string($pluginContents)) {
        throw new RuntimeException('The production plugin PHP could not be inspected.');
    }
    if (str_contains($pluginContents, '.mjs')) {
        throw new RuntimeException('The minified main plugin still contains .mjs runtime references.');
    }
    if (distribution_wordpress_plugin_header_comment(
        $pluginContents,
        'the archived main plugin'
    ) !== $expectedPluginHeader) {
        throw new RuntimeException('The archived WordPress plugin header differs from its source header.');
    }
    lint_distribution_php($pluginContents);

    if ($comparisonArchivePath !== null) {
        if (!is_file($comparisonArchivePath)) {
            throw new RuntimeException('The comparison production archive does not exist.');
        }
        $comparisonHash = hash_file('sha256', $comparisonArchivePath);
        if (!is_string($comparisonHash) || !hash_equals($archiveHash, $comparisonHash)) {
            throw new RuntimeException('Two production builds from the same source were not byte-identical.');
        }
        if ($comparisonManifestPath !== null) {
            if (!is_file($comparisonManifestPath)
                || file_get_contents($manifestPath) !== file_get_contents($comparisonManifestPath)) {
                throw new RuntimeException('Two production distribution manifests were not byte-identical.');
            }
        }
    }

    echo sprintf(
        "Verified %s (%d bytes, %d entries, sha256 %s)\n",
        basename($archivePath),
        $archiveBytes,
        count($actualEntries),
        $archiveHash
    );
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

/** @return list<string> */
function distribution_php_comment_tokens(string $php): array
{
    $comments = [];
    foreach (token_get_all($php) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $comments[] = $token[1];
        }
    }

    return $comments;
}

function distribution_wordpress_plugin_header_comment(string $source, string $label): string
{
    $headers = [];
    $offset = 0;
    foreach (token_get_all($source) as $token) {
        $text = is_array($token) ? $token[1] : $token;
        if (is_array($token)
            && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
            && preg_match('/^[ \t\/*#@]*Plugin Name\s*:\s*\S.*$/mi', $text) === 1
            && $offset + strlen($text) <= 8192) {
            $headers[] = $text;
        }
        $offset += strlen($text);
    }
    if (count($headers) !== 1) {
        throw new RuntimeException(sprintf(
            'Expected exactly one discoverable WordPress Plugin Name header comment in %s; found %d.',
            $label,
            count($headers)
        ));
    }

    return $headers[0];
}

function lint_distribution_php(string $php): void
{
    $temporary = tempnam(sys_get_temp_dir(), 'plpc-distribution-php-');
    if ($temporary === false || file_put_contents($temporary, $php, LOCK_EX) !== strlen($php)) {
        if (is_string($temporary)) {
            @unlink($temporary);
        }
        throw new RuntimeException('Unable to create a temporary file for production plugin PHP linting.');
    }
    try {
        $process = proc_open(
            [PHP_BINARY, '-l', $temporary],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start PHP lint for the production plugin.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException('The minified production plugin PHP does not lint: ' . trim($stdout . "\n" . $stderr));
        }
    } finally {
        @unlink($temporary);
    }
}
