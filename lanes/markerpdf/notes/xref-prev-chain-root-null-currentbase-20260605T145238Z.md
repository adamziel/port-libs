# markerPDF xref Prev-chain Root null current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T145238Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260605T145238Z`
Base accepted HEAD: `44170a629757d61b851ec8fee38b7d6611784378`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF parsing to pdftext/PDFium before model/OCR/layout stages. Under the current no-GPU scope, this lane owns the native PHP parser boundary for current xref sections, trailer dictionaries, `/Prev` recursion, metadata, and WordPress import safety.

Incremental PDF updates can carry older trailer dictionaries through `/Prev`. A sparse latest trailer that omits `/Root` may rely on an earlier root, but a latest trailer that explicitly sets `/Root null` clears the catalog root and must not inherit stale previous page trees or catalog metadata.

## Behavior

`PdfTextExtractor` now treats `/Root null` in the current xref table or xref-stream trailer as an explicit stop while resolving trailer Root through the `/Prev` chain. Omitted `/Root` still follows the already-covered sparse latest trailer path.

The focused fixture builds a previous xref table with stale catalog, page text, XMP metadata, Info, and EmbeddedFiles. It then appends a latest xref stream with `/Root null /Info 6 0 R /Prev ...` and current Info metadata. WordPress import now selects the current Info dictionary while suppressing stale previous catalog metadata, attachments, and page text.

## Red-First Evidence

Before implementation, after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php
FAIL stops previous catalog inheritance when latest xref-stream trailer sets Root null
Expected: []
Actual: ['Stale Root null Prev page']
1 test files, 13 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php
1 test files, 13 assertions, 0 failures
```

Adjacent xref/metadata/security check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
5 test files, 1366 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-root-null-currentbase.php
```

The smoke exits 0 and reports `root_null_latest_xref_stream_stops_prev_catalog=true`, `current_info_selected=true`, `stale_prev_text_excluded=true`, `stale_prev_metadata_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-root-null-currentbase.php
git diff --check -- lanes/markerpdf
```

All changed PHP files reported no syntax errors and diff check reported no whitespace errors.

Root harness status: not run - isolated micro-slice.

## Broad-Test Exclusion

An attempted broader extractor run including `lanes/markerpdf/tests/PdfTextExtractorTest.php` was not used as an acceptance gate because it exposed an unrelated Type0 CMap code-space variable issue already outside this xref slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
6 test files, 1983 assertions, 2 failures
```

The failures were `uses Type0 Encoding CMap CIDs before raw source-code width fallbacks` and `uses Type0 Encoding CMap code-space boundaries for fallback text widths`, with undefined Type0 CMap code-space variables in `PdfTextExtractor::parseCidCMap()`. This patch does not change Type0 CMap parsing behavior.

## Non-Overlap

This does not repeat sparse latest trailers that omit `/Root`, explicit `/Info null`, damaged same-generation xref row repair, stale explicit offset repair, wrong current-offset repair, indirect/compressed `/Prev` helpers, damaged middle `/Prev` repair, hybrid companion xref merging, xref-stream object-stream metadata selection, trailer `/Encrypt null` clearing, trailer `/ID` precedence, latest free-row catalog suppression, or object-stream/free-entry handling.

The bounded behavior here is only explicit `/Root null` in the latest xref section stopping stale previous trailer Root inheritance while preserving current Info metadata from the latest section.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/xref-stream `/Prev` walker, dictionary parser, metadata extractor, text extractor, and WordPress smoke path. OCR, Surya/Texify/Torch, PDFium execution, model downloads, raster rendering, and external PDF tools remain intentionally outside this no-GPU markerPDF slice.
