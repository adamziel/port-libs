<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$cliOptions = getopt('', ['target-dir:', 'plugin-source:']);
foreach (['target-dir', 'plugin-source'] as $optionName) {
    $wasRequested = false;
    foreach ($argv as $argument) {
        if ($argument === '--' . $optionName || str_starts_with($argument, '--' . $optionName . '=')) {
            $wasRequested = true;
            break;
        }
    }
    if ($wasRequested
        && (!array_key_exists($optionName, $cliOptions)
            || !is_string($cliOptions[$optionName])
            || trim($cliOptions[$optionName]) === '')) {
        fwrite(STDERR, "--{$optionName} requires a non-empty value.\n");
        exit(1);
    }
}
$sourcePlugin = isset($cliOptions['plugin-source']) && is_string($cliOptions['plugin-source'])
    ? $cliOptions['plugin-source']
    : $root . '/tools/playground-converter-plugin/port-libs-playground-converter.php';
$sourceAssets = $root . '/tools/playground-converter-plugin/assets';
$sourcePdfJsRasterizer = $root . '/pandoc-showcase/pdfjs-form-rasterizer.mjs';
$sourcePdfJsFactsProvider = $root . '/pandoc-showcase/pdfjs-facts-provider.mjs';
$sourcePdfJs = $root . '/pandoc-showcase/vendor/pdfjs';
$sourcePdfJpxRasterizer = $root . '/pandoc-showcase/pdf-jpx-rasterizer.mjs';
$sourcePdfJbig2Rasterizer = $root . '/pandoc-showcase/pdf-jbig2-rasterizer.mjs';
$sourcePdfOpenJpeg = $root . '/pandoc-showcase/vendor/pdfjs-openjpeg';
$sourcePdfJbig2 = $root . '/pandoc-showcase/vendor/pdfjs-jbig2';
$targetDir = isset($cliOptions['target-dir']) && is_string($cliOptions['target-dir'])
    ? rtrim($cliOptions['target-dir'], DIRECTORY_SEPARATOR)
    : $root . '/pandoc-showcase/playground';
if ($targetDir === '') {
    fwrite(STDERR, "The plugin distribution target directory cannot be empty.\n");
    exit(1);
}
$targetZip = $targetDir . '/port-libs-playground-converter.zip';
$targetManifest = $targetDir . '/port-libs-playground-converter.manifest.json';
// Many shared hosts configure PHP uploads at 8 MiB. Keep a little multipart
// overhead below that ceiling; hosts still using PHP's 2 MiB default need a
// normal server-level upload-limit increase for any feature-complete PDF.js
// importer package.
const PLPC_COMMON_PHP_UPLOAD_LIMIT = (8 * 1024 * 1024) - (64 * 1024);
// ZIP metadata must not make two builds from the same source differ. 1980-01-01
// is the earliest timestamp representable by the classic ZIP format.
const PLPC_DISTRIBUTION_MTIME = 315532800;

if (!is_file($sourcePlugin)) {
    fwrite(STDERR, "Missing plugin source: {$sourcePlugin}\n");
    exit(1);
}
if (!is_dir($sourceAssets) || !is_file($sourcePdfJsRasterizer) || !is_file($sourcePdfJsFactsProvider) || !is_dir($sourcePdfJs)
    || !is_file($sourcePdfJpxRasterizer) || !is_file($sourcePdfJbig2Rasterizer)
    || !is_dir($sourcePdfOpenJpeg) || !is_dir($sourcePdfJbig2)) {
    fwrite(STDERR, "Missing browser importer assets. Restore the PDF.js, OpenJPEG, and JBIG2 vendor assets before building the plugin.\n");
    exit(1);
}

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$temporaryZip = $targetZip . '.tmp-' . getmypid() . '-' . bin2hex(random_bytes(4));
@unlink($temporaryZip);

$zip = new ZipArchive();
if ($zip->open($temporaryZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create {$temporaryZip}\n");
    exit(1);
}
$zipClosed = false;

try {
    add_wordpress_plugin_php_to_zip(
        $zip,
        $sourcePlugin,
        'port-libs-playground-converter/port-libs-playground-converter.php'
    );
    add_tree_to_zip(
        $zip,
        $root . '/lanes/pandoc/src',
        'port-libs-playground-converter/lanes/pandoc/src',
        static fn (SplFileInfo $file, string $relative): bool => !is_distribution_only_audit_source($relative)
    );
    add_tree_to_zip($zip, $root . '/lanes/markerpdf/src', 'port-libs-playground-converter/lanes/markerpdf/src');
    add_tree_to_zip($zip, $sourceAssets, 'port-libs-playground-converter/assets');
    add_file_to_zip($zip, $sourcePdfJsRasterizer, 'port-libs-playground-converter/assets/pdfjs-form-rasterizer.mjs');
    add_file_to_zip($zip, $sourcePdfJsFactsProvider, 'port-libs-playground-converter/assets/pdfjs-facts-provider.mjs');
    add_tree_to_zip(
        $zip,
        $sourcePdfJs,
        'port-libs-playground-converter/assets/vendor/pdfjs',
        static fn (SplFileInfo $file, string $relative): bool => !in_array(str_replace(DIRECTORY_SEPARATOR, '/', $relative), [
            // Source maps do not execute in the plugin.
            'image_decoders/pdf.image_decoders.mjs.map',
            // PDF.js ships equivalent minified and development decoder
            // bundles. The importer loads production assets only; retaining
            // both needlessly consumes the shared-host upload margin.
            'image_decoders/pdf.image_decoders.mjs',
            // Static document import has no PDF JavaScript/XFA requirement.
            // If a browser lacks WebAssembly, the client already returns a
            // per-figure placeholder instead of abandoning the import.
            'wasm/quickjs-eval.wasm',
            'wasm/quickjs-eval.js',
            'wasm/openjpeg_nowasm_fallback.js',
            'wasm/jbig2_nowasm_fallback.js',
        ], true)
    );
    add_file_to_zip($zip, $sourcePdfJpxRasterizer, 'port-libs-playground-converter/assets/pdf-jpx-rasterizer.mjs');
    add_file_to_zip($zip, $sourcePdfJbig2Rasterizer, 'port-libs-playground-converter/assets/pdf-jbig2-rasterizer.mjs');
    // Both standalone decoders use the same WASM binaries as PDF.js. The
    // rasterizers explicitly locate those shared binaries, so do not carry
    // duplicate copies in the plugin ZIP.
    add_tree_to_zip(
        $zip,
        $sourcePdfOpenJpeg,
        'port-libs-playground-converter/assets/vendor/pdfjs-openjpeg',
        static fn (SplFileInfo $file): bool => $file->getFilename() !== 'openjpeg.wasm'
    );
    add_tree_to_zip(
        $zip,
        $sourcePdfJbig2,
        'port-libs-playground-converter/assets/vendor/pdfjs-jbig2',
        static fn (SplFileInfo $file): bool => $file->getFilename() !== 'jbig2.wasm'
    );
    if (!$zip->close()) {
        throw new RuntimeException('ZipArchive could not finish the plugin archive.');
    }
    $zipClosed = true;
    clearstatcache(true, $temporaryZip);
    $archiveBytes = filesize($temporaryZip);
    if (!is_int($archiveBytes) || $archiveBytes > PLPC_COMMON_PHP_UPLOAD_LIMIT) {
        throw new RuntimeException(sprintf(
            'Plugin archive is %d bytes; it must stay below the common 8 MiB PHP upload limit (%d bytes).',
            (int) $archiveBytes,
            PLPC_COMMON_PHP_UPLOAD_LIMIT
        ));
    }
    if (!rename($temporaryZip, $targetZip)) {
        throw new RuntimeException("Unable to publish {$targetZip}.");
    }
    write_distribution_manifest($targetZip, $targetManifest);
} catch (Throwable $error) {
    if (!$zipClosed) {
        try {
            $zip->close();
        } catch (Throwable) {
            // Preserve the original build error when libzip already made the
            // archive handle unusable while failing to close it.
        }
    }
    @unlink($temporaryZip);
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
echo $targetZip . "\n";
echo $targetManifest . "\n";

/**
 * Preserve the exact WordPress discovery header while stripping comments and
 * formatting from executable PHP. The header and executable are handled
 * separately so module-extension rewriting cannot mutate plugin metadata.
 */
function add_wordpress_plugin_php_to_zip(ZipArchive $zip, string $source, string $local): void
{
    $sourceContents = file_get_contents($source);
    if (!is_string($sourceContents)) {
        throw new RuntimeException("Unable to read {$source} for the plugin archive.");
    }
    $header = wordpress_plugin_header_comment($sourceContents, $source);
    $stripped = php_strip_whitespace($source);
    if (!is_string($stripped) || $stripped === '') {
        throw new RuntimeException("Unable to minify {$source} for the plugin archive.");
    }
    if (preg_match('/\A<\?php(?:\s|$)/', $stripped, $openingMatch) !== 1) {
        throw new RuntimeException("The WordPress plugin source {$source} does not begin with an ordinary PHP open tag.");
    }

    $openingTag = $openingMatch[0];
    $openingTag = rtrim($openingTag);
    $executable = substr($stripped, strlen($openingMatch[0]));
    if (!is_string($executable)) {
        throw new RuntimeException("Unable to isolate executable PHP in {$source}.");
    }
    // Native WordPress/Playground servers commonly serve .mjs with the wrong
    // MIME type. Keep the established production rewrite in minified PHP.
    $executable = str_replace('.mjs', '.js', $executable);
    $contents = $openingTag . "\n" . $header . "\n" . ltrim($executable, "\r\n");
    $local = plugin_distribution_path($local);
    if (!$zip->addFromString($local, $contents)) {
        throw new RuntimeException("Unable to add {$source} to the plugin archive.");
    }
    set_distribution_entry_metadata($zip, $local);
}

/**
 * Return the one exact header comment WordPress can discover in the first
 * 8 KiB of the main plugin file. Missing or ambiguous headers fail closed.
 */
function wordpress_plugin_header_comment(string $source, string $label): string
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

function add_file_to_zip(
    ZipArchive $zip,
    string $source,
    string $local,
    bool $minifyPhp = true,
    bool $rewriteModuleExtensions = false
): void
{
    $local = plugin_distribution_path($local);
    $extension = pathinfo($source, PATHINFO_EXTENSION);
    // The plugin ships a sizeable pure-PHP parser. Distribution packages do
    // not need comments or formatting, and stripping them keeps the ZIP below
    // the common 8 MiB PHP upload limit without removing any runtime formats.
    if ($minifyPhp && $extension === 'php') {
        $contents = php_strip_whitespace($source);
        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException("Unable to minify {$source} for the plugin archive.");
        }
    } elseif ($extension === 'mjs' || $rewriteModuleExtensions) {
        $contents = file_get_contents($source);
        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read {$source} for the plugin archive.");
        }
        $rewriteModuleExtensions = true;
    } else {
        if (!$zip->addFile($source, $local)) {
            throw new RuntimeException("Unable to add {$source} to the plugin archive.");
        }
        set_distribution_entry_metadata($zip, $local);

        return;
    }

    if ($rewriteModuleExtensions || $extension === 'mjs') {
        // Native WordPress/Playground servers can serve .mjs as
        // application/octet-stream. Modules are strict about MIME types,
        // whereas the same assets under .js receive text/javascript. Rewrite
        // both archive names and relative module references at build time.
        $contents = str_replace('.mjs', '.js', $contents);
    }
    if (!$zip->addFromString($local, $contents)) {
        throw new RuntimeException("Unable to add {$source} to the plugin archive.");
    }
    set_distribution_entry_metadata($zip, $local);
}

/**
 * @param null|callable(SplFileInfo,string):bool $include
 */
function add_tree_to_zip(ZipArchive $zip, string $source, string $localRoot, ?callable $include = null): void
{
    $entries = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
            continue;
        }
        $relative = substr($file->getPathname(), strlen(rtrim($source, DIRECTORY_SEPARATOR)) + 1);
        if ($include !== null && !$include($file, $relative)) {
            continue;
        }
        $entries[str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = $file->getPathname();
    }
    ksort($entries, SORT_STRING);
    foreach ($entries as $relative => $path) {
        add_file_to_zip($zip, $path, rtrim($localRoot, '/') . '/' . $relative);
    }
}

function set_distribution_entry_metadata(ZipArchive $zip, string $local): void
{
    // The runtime-complete PDF import package sits close to common shared-host
    // upload ceilings. Use libzip's strongest deterministic DEFLATE level
    // before considering removal of any executable format support.
    if (method_exists($zip, 'setCompressionName')
        && !$zip->setCompressionName($local, ZipArchive::CM_DEFLATE, 9)) {
        throw new RuntimeException("Unable to set ZIP compression for {$local}.");
    }
    if (method_exists($zip, 'setMtimeName') && !$zip->setMtimeName($local, PLPC_DISTRIBUTION_MTIME)) {
        throw new RuntimeException("Unable to normalize ZIP timestamp for {$local}.");
    }
    // Regular, world-readable file permissions. This avoids host umasks
    // leaking into an otherwise identical production package.
    if (method_exists($zip, 'setExternalAttributesName')
        && !$zip->setExternalAttributesName($local, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
        throw new RuntimeException("Unable to normalize ZIP permissions for {$local}.");
    }
}

function write_distribution_manifest(string $archivePath, string $manifestPath): void
{
    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException("Unable to reopen {$archivePath} for manifest generation.");
    }

    $entries = [];
    try {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name) || $name === '') {
                throw new RuntimeException("Unable to read ZIP entry {$index}.");
            }
            $contents = $zip->getFromIndex($index);
            if (!is_string($contents)) {
                throw new RuntimeException("Unable to hash ZIP entry {$name}.");
            }
            $entries[$name] = [
                'bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ];
        }
    } finally {
        $zip->close();
    }
    ksort($entries, SORT_STRING);

    $manifest = [
        'schemaVersion' => 1,
        'archive' => basename($archivePath),
        'archiveBytes' => filesize($archivePath),
        'archiveSha256' => hash_file('sha256', $archivePath),
        'entries' => $entries,
    ];
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $temporaryManifest = $manifestPath . '.tmp-' . getmypid();
    if (file_put_contents($temporaryManifest, $json, LOCK_EX) !== strlen($json)
        || !rename($temporaryManifest, $manifestPath)) {
        @unlink($temporaryManifest);
        throw new RuntimeException("Unable to publish {$manifestPath}.");
    }
}

function plugin_distribution_path(string $path): string
{
    $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

    return str_ends_with($path, '.mjs') ? substr($path, 0, -4) . '.js' : $path;
}

/**
 * These are upstream-parity/audit reports or external-engine planning code,
 * not import readers/writers. Retaining them in source control is useful, but
 * loading them in a production import plugin only makes the install ZIP exceed
 * PHP's usual upload limit.
 */
function is_distribution_only_audit_source(string $relative): bool
{
    static $files = [
        // This handoff plans pdflatex/Typst/etc. processes for server-side PDF
        // generation. The WordPress plugin imports documents and has no
        // external-engine execution path or runtime reference to this class.
        'PdfEngineHandoff.php',
        'UpstreamRunnerDependencyAudit.php',
        'EpubNativeAstPackageComparisonHarness.php',
        'PptxUpstreamReaderEvidence.php',
        'MarkdownUpstreamReaderEvidence.php',
        'DocxWriterGoldenManifest.php',
        'HtmlUpstreamReaderEvidence.php',
        'EpubUpstreamReaderEvidence.php',
        'DocxUpstreamRunnerPlan.php',
        'DocxParityCorpusAudit.php',
        'DocxUpstreamFocusedReaderEvidence.php',
        'EpubMediaBagComparisonHarness.php',
        'MarkdownNativeAstComparisonHarness.php',
        'HtmlNativeAstComparisonHarness.php',
        'DocxNativeAstComparisonHarness.php',
        'ManCorpusAudit.php',
        'EpubExecutableNativeAstComparisonHarness.php',
        'PptxNativeAstComparisonHarness.php',
        'ManUpstreamReaderEvidence.php',
        'XlsxNativeAstComparisonHarness.php',
        'PptxExecutableNativeAstComparisonHarness.php',
        'XlsxExecutableNativeAstComparisonHarness.php',
        'DocxNativeComparisonSmokeHarness.php',
        'XlsxUpstreamReaderEvidence.php',
        'DocxUpstreamCacheManifest.php',
        'PandocUpstreamRunnerDependencyAudit.php',
        'ShowcaseHaskellReferenceTimeout.php',
    ];

    return in_array(basename($relative), $files, true);
}
