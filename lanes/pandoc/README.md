# Pandoc conversion package

This package is a native PHP document-conversion kernel. It reads supported
document formats into a shared semantic AST and writes that AST to supported
targets, including Gutenberg block markup. The primary public facade is
[`PandocConverter`](src/PandocConverter.php).

Start with:

- [Architecture](ARCHITECTURE.md) for the pipeline, component boundaries,
  resource model, and current limitations.
- [Design decisions](DESIGN_DECISIONS.md) for the durable invariants that
  changes to readers, writers, media handling, and tests must preserve.
- [`PandocFormatRegistry`](src/PandocFormatRegistry.php), together with the
  facade's local aliases and WordPress target, for executable format support
  and dispatch.

## Scope

The package owns document interpretation and conversion:

- format recognition and dispatch;
- reader implementations and the shared [`AstNode`](src/AstNode.php) model;
- deterministic, provenance-carrying repairs;
- optional media extraction and reference rewriting;
- target writers and caller-owned output sinks;
- conversion diagnostics and bounded PDF source-integrity summaries.

It does **not** own uploads, authentication, authorization, job persistence,
WordPress post creation, publication policy, or HTML/SVG sanitization. The
Playground application is a demonstration and integration harness, not part of
the conversion kernel. A WordPress importer should call this package and keep
those application responsibilities in its own boundary.

## Runtime

The repository requires PHP 8.2 or newer and autoloads `PortLibs\Pandoc`
through the root Composer configuration. Format-specific paths may need PHP
extensions such as DOM, Intl, Mbstring, Zip, or GD. Focused CI currently
exercises PHP 8.4 with DOM, Intl, Mbstring, and Zip enabled.

Pandoc, browser PDF renderers, and other upstream tools are test or evidence
oracles. They are not runtime dependencies of the native conversion path.

## Basic conversion

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use PortLibs\Pandoc\PandocConverter;

$blocks = PandocConverter::convert(
    $sourceBytes,
    from: 'docx',
    to: 'wordpress',
    options: [
        'readerOptions' => [],
        'writerOptions' => [],
    ],
);
```

Use `read()` and `write()` when an integration needs to inspect or transform
the AST between those stages. File variants avoid an extra source-string copy
where a reader supports a file-backed path:

```php
$blocks = PandocConverter::convertFile(
    '/srv/imports/document.epub',
    from: 'epub',
    to: 'wordpress',
);
```

For a caller-owned output callback, use `convertToSink()` or
`convertFileToSink()`:

```php
PandocConverter::convertFileToSink(
    '/srv/imports/document.epub',
    from: 'epub',
    to: 'wordpress',
    sink: static function (string $chunk): void {
        // Send the chunk to application-owned storage.
    },
);
```

Sink output is required to be byte-equivalent to eager output. The WordPress
writer, including selected EPUB-to-WordPress paths, can emit incrementally and
avoid retaining the final string. Other target writers may materialize their
complete output before invoking the sink, and not every reader is a fully
streaming parser.

## Media extraction

Media extraction is explicit. It rewrites document references and returns
public media metadata plus diagnostics:

```php
$result = PandocConverter::convertFileWithMedia(
    '/srv/imports/document.epub',
    from: 'epub',
    to: 'wordpress',
    options: [
        'extractMedia' => [
            'destination' => 'media',
            'outputDirectory' => '/srv/import-work/42',
        ],
    ],
);

$blocks = $result['output'];
$media = $result['media'];
$diagnostics = $result['diagnostics'];
```

The facade's returned media entries deliberately omit file bodies. It writes
those bodies only when the caller explicitly supplies `outputDirectory`.
Lower-level [`PandocMediaExtractor`](src/PandocMediaExtractor.php) result
entries include a `contents` body outside the AST for integrations that manage
storage themselves. The application owns directory lifecycle, quotas, MIME
acceptance, and publication.

For PDF conversions, `convertWithMedia()` may also return `sourceIntegrity`.
Treat `sourceIntegrity.complete !== true` as an explicit review or fallback
condition, not as a successful fidelity claim.

## Format support

Do not infer support from a filename, a class, or Pandoc's upstream format
list. Ask the facade or registry:

```php
if (!PandocConverter::canRead($inputFormat)) {
    throw new RuntimeException('Unsupported input format');
}

if (!PandocConverter::canWrite('wordpress')) {
    throw new RuntimeException('Unsupported output format');
}
```

Registry states are evidence-scoped. `reader-equivalent`, `partial`, and
`unsupported` are materially different claims. See
[`lane-status.json`](lane-status.json) and
[`UPSTREAM_TEST_MANIFEST.json`](UPSTREAM_TEST_MANIFEST.json) for the current
status and pinned evidence denominator.

## Untrusted inputs

The conversion package contains many structural checks and bounded operations,
but it does not impose one universal source-size, decompression, time, or memory
quota. Importers must set workload limits before accepting untrusted documents.

For ZIP-based inputs, [`ZipPackage`](src/ZipPackage.php) exposes strict raw
preflight and an optional early entry-count gate. Example policy values, not
library defaults:

```php
use PortLibs\Pandoc\ZipPackage;

$preflight = ZipPackage::rawStrictImportPreflight(
    $sourceBytes,
    maxTotalUncompressedBytes: 256 * 1024 * 1024,
    maxExpansionRatio: 100.0,
    maxEntryUncompressedBytes: 32 * 1024 * 1024,
    maxEntryCount: 4096,
);

if (($preflight['isValid'] ?? false) !== true) {
    throw new RuntimeException('Archive rejected by importer policy');
}
```

These limits are caller-selected and are not yet applied automatically by every
package reader. See [Current boundaries](ARCHITECTURE.md#current-boundaries)
before exposing a converter to untrusted workloads.

## WordPress output

`wordpress` (aliases: `wp`, `blocks`) is a facade-owned output format. The
writer emits native core blocks where possible and uses Custom HTML for literal
raw and known structures without a native block mapping. Unknown node types are
not promised a fallback. Literal raw HTML is preserved for conversion fidelity.
Active raw inline markup is isolated in `core/html`, but that isolation is
**not sanitization**. A publishing integration must apply its own HTML, SVG,
URL, capability, and MIME policy.

The compatibility contract is the pinned official WordPress core block parser
and serializer, including exact code-listing payloads. See
[`WordPressBlockWriterCoreRoundTripTest`](tests/WordPressBlockWriterCoreRoundTripTest.php).

## Tests and evidence

Run focused tests from the repository root:

```sh
php tools/run-tests.php lanes/pandoc/tests/PandocDocumentationContractTest.php
php tools/run-tests.php lanes/pandoc/tests/WordPressBlockWriterCoreRoundTripTest.php
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
```

The full package has layered evidence rather than one all-purpose test:
synthetic contracts, pinned upstream fixtures, native AST comparisons,
WordPress consumer round trips, the showcase corpus, PDF fidelity ledgers, and
memory-budget processes. [Architecture](ARCHITECTURE.md#test-and-evidence-architecture)
describes how those layers fit together.

The files under [`notes/`](notes/) are dated implementation and audit records.
They are useful historical evidence, but this README, `ARCHITECTURE.md`, and
`DESIGN_DECISIONS.md` are the canonical maintainers' documentation and must be
updated when a package boundary changes.
