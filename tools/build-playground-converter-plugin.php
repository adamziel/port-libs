<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourcePlugin = $root . '/tools/playground-converter-plugin/port-libs-playground-converter.php';
$sourceAssets = $root . '/tools/playground-converter-plugin/assets';
$sourcePdfJsRasterizer = $root . '/pandoc-showcase/pdfjs-form-rasterizer.mjs';
$sourcePdfJs = $root . '/pandoc-showcase/vendor/pdfjs';
$sourcePdfJpxRasterizer = $root . '/pandoc-showcase/pdf-jpx-rasterizer.mjs';
$sourcePdfJbig2Rasterizer = $root . '/pandoc-showcase/pdf-jbig2-rasterizer.mjs';
$sourcePdfOpenJpeg = $root . '/pandoc-showcase/vendor/pdfjs-openjpeg';
$sourcePdfJbig2 = $root . '/pandoc-showcase/vendor/pdfjs-jbig2';
$targetDir = $root . '/pandoc-showcase/playground';
$targetZip = $targetDir . '/port-libs-playground-converter.zip';
// Many shared hosts configure PHP uploads at 8 MiB. Keep a little multipart
// overhead below that ceiling; hosts still using PHP's 2 MiB default need a
// normal server-level upload-limit increase for any feature-complete PDF.js
// importer package.
const PLPC_COMMON_PHP_UPLOAD_LIMIT = (8 * 1024 * 1024) - (64 * 1024);

if (!is_file($sourcePlugin)) {
    fwrite(STDERR, "Missing plugin source: {$sourcePlugin}\n");
    exit(1);
}
if (!is_dir($sourceAssets) || !is_file($sourcePdfJsRasterizer) || !is_dir($sourcePdfJs)
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

try {
    // Keep the WordPress plugin-header comment intact; WordPress discovers a
    // plugin from that header before it ever executes the file.
    add_file_to_zip($zip, $sourcePlugin, 'port-libs-playground-converter/port-libs-playground-converter.php', false, true);
    add_tree_to_zip(
        $zip,
        $root . '/lanes/pandoc/src',
        'port-libs-playground-converter/lanes/pandoc/src',
        static fn (SplFileInfo $file, string $relative): bool => !is_distribution_only_audit_source($relative)
    );
    add_tree_to_zip($zip, $root . '/lanes/markerpdf/src', 'port-libs-playground-converter/lanes/markerpdf/src');
    add_tree_to_zip($zip, $sourceAssets, 'port-libs-playground-converter/assets');
    add_file_to_zip($zip, $sourcePdfJsRasterizer, 'port-libs-playground-converter/assets/pdfjs-form-rasterizer.mjs');
    add_tree_to_zip(
        $zip,
        $sourcePdfJs,
        'port-libs-playground-converter/assets/vendor/pdfjs',
        static fn (SplFileInfo $file, string $relative): bool => !in_array(str_replace(DIRECTORY_SEPARATOR, '/', $relative), [
            // Source maps do not execute in the plugin.
            'image_decoders/pdf.image_decoders.mjs.map',
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
} catch (Throwable $error) {
    $zip->close();
    @unlink($temporaryZip);
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
echo $targetZip . "\n";

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
}

/**
 * @param null|callable(SplFileInfo,string):bool $include
 */
function add_tree_to_zip(ZipArchive $zip, string $source, string $localRoot, ?callable $include = null): void
{
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
        add_file_to_zip($zip, $file->getPathname(), rtrim($localRoot, '/') . '/' . $relative);
    }
}

function plugin_distribution_path(string $path): string
{
    $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

    return str_ends_with($path, '.mjs') ? substr($path, 0, -4) . '.js' : $path;
}

/**
 * These are upstream-parity and audit reports, not readers/writers. Retaining
 * them in source control is useful, but loading them in a production import
 * plugin only makes the install ZIP exceed PHP's usual upload limit.
 */
function is_distribution_only_audit_source(string $relative): bool
{
    static $files = [
        'UpstreamRunnerDependencyAudit.php',
        'DelimitedTextUpstreamReaderEvidence.php',
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
