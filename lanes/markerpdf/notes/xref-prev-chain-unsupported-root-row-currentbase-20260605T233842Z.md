# markerPDF xref Prev-chain unsupported Root row current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T233842Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260605T233842Z`
Base accepted HEAD: `b65bbeadd52942a905ef176ab2bea038137f6aca`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF parsing to PDF text extraction before OCR/model stages. Under the current no-GPU scope, this lane owns the native PHP xref-stream, `/Prev`, metadata, attachment, and WordPress import-safety boundaries.

PDF xref-stream entry types `0`, `1`, and `2` are the supported free, direct, and compressed storage owners. A higher current entry type for the trailer `/Root` object is not a usable catalog owner, but it is still a current-section ownership row. The native parser must fail closed at that current row instead of ignoring it and replaying stale previous catalog, XMP, page text, or EmbeddedFiles through `/Prev`.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now keep unsupported current xref-stream rows as current null/free owners for merge purposes. Trailer-root resolution treats a current-section non-live row for the referenced root as a present-but-cleared catalog boundary.

The fixture builds a previous xref table with stale catalog text, catalog XMP, Info, language, and an EmbeddedFiles name tree, then appends a latest xref stream with `/Root 1 0 R /Info 6 0 R /Prev ...`, a type-`9` row for object `1`, and a valid current Info row for object `6`. WordPress import now keeps current Info metadata and excludes stale previous catalog text, XMP, language, and attachments.

## Red-First Evidence

Before the parser edit, after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainUnsupportedRootRowCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats unsupported current xref-stream Root row as catalog-clearing owner before Prev replay (lanes/markerpdf/tests/PdfXrefPrevChainUnsupportedRootRowCurrentBaseTest.php)
Values are not identical
Expected: array (
)
Actual: array (
  0 => 'Stale unsupported Root Prev page',
)

1 test files, 1 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainUnsupportedRootRowCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats unsupported current xref-stream Root row as catalog-clearing owner before Prev replay

1 test files, 24 assertions, 0 failures
```

Adjacent xref ownership family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainUnsupportedRootRowCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamUnsupportedTypeObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainMalformedRootCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 609 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-unsupported-root-row-currentbase.php
```

The smoke exits 0 and emits `unsupported_current_root_row_stops_prev_catalog=true`, `current_info_selected=true`, `stale_prev_text_excluded=true`, `stale_prev_metadata_excluded=true`, `stale_prev_embedded_files_excluded=true`, `attachment_summary_empty=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and metadata checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainUnsupportedRootRowCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-unsupported-root-row-currentbase.php
php -r '$files=["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { $json = json_decode(file_get_contents($file), true); if (!is_array($json)) { fwrite(STDERR, $file . ": " . json_last_error_msg() . "\n"); exit(1); } echo $file . " ok\n"; }'
git diff --check -- lanes/markerpdf
```

All changed PHP files reported no syntax errors. The JSON check reported both lane metadata files `ok`. Diff check reported no whitespace errors.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat xref-stream `/Root null`, malformed `/Root`, sparse omitted `/Root` inheritance, latest free-row catalog suppression, object-stream free-entry suppression, unsupported current object-stream member row text-only suppression, indirect/compressed `/Prev` helper repair, damaged middle `/Prev` repair, hybrid companion xref merging, trailer `/Encrypt null`, trailer `/ID` precedence, or Image XObject q/Q current-path behavior.

The bounded behavior here is only unsupported current xref-stream row types for the trailer Root object suppressing stale previous catalog replay while preserving current Info metadata.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP xref-stream decoder, `/Prev` merge logic, direct-object selection, text extraction, metadata extraction, embedded-file extraction, and WordPress smoke path. OCR, Surya/Texify/Torch, PDFium execution, model downloads, raster rendering, and external PDF tools remain intentionally outside this no-GPU markerPDF slice.
