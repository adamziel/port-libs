# markerPDF xref Prev-chain Info null current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T043836Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260605T043836Z`
Base accepted HEAD: `6aaf0f620e0a4ee5fbfffd3a2afb15e30bb56a45`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF parsing to pdftext/PDFium before model/OCR/layout stages. Under the no-GPU scope, this lane owns the native PHP parser boundary for current xref sections, trailer dictionaries, `/Prev` recursion, metadata, and WordPress import safety.

Incremental PDF updates can carry older trailer dictionaries through `/Prev`. A sparse latest trailer that omits `/Info` may intentionally rely on an earlier current section, but a latest trailer that explicitly sets `/Info null` clears the Info dictionary and must not inherit stale previous document metadata.

## Behavior

`PdfMetadataExtractor` now treats `/Info null` in the current xref table or xref-stream trailer as an explicit stop while resolving trailer Info through the `/Prev` chain. Omitted `/Info` still follows the already-covered sparse latest trailer path.

The focused fixture builds a previous xref table with stale Info title/author/producer and stale page text, then appends current catalog/page objects plus a latest xref stream with `/Root 1 0 R /Info null /Prev ...`. WordPress metadata now contains current catalog language and viewer preferences only, current page text is selected, and stale previous Info strings are excluded.

## Red-First Evidence

Before implementation, after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
FAIL stops previous Info inheritance when latest xref-stream trailer sets Info null
Expected: ['catalog']
Actual: ['info', 'catalog']
1 test files, 220 assertions, 1 failures
```

## Focused Verification

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
1 test files, 231 assertions, 0 failures
```

Adjacent metadata/xref/security check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php
4 test files, 313 assertions, 0 failures
```

Broader xref/parser family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfXref*Test.php' -o -name 'PdfParserXref*Test.php' -o -name 'PdfParserTrailer*CurrentBaseTest.php' \) | sort)
63 test files, 1415 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
```

The smoke exits 0 and reports `info_null_latest_xref_stream_stops_prev_info=true`, `info_null_latest_xref_stream_current_catalog_selected=true`, `info_null_latest_xref_stream_current_text_selected=true`, `info_null_latest_xref_stream_stale_prev_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Diff hygiene:

```text
git diff --check -- lanes/markerpdf
```

No whitespace errors reported.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat sparse latest trailers that omit `/Info`, damaged same-generation xref row repair, stale explicit offset repair, wrong current-offset repair, indirect/compressed `/Prev` helpers, damaged middle `/Prev` repair, hybrid companion xref merging, xref-stream object-stream metadata selection, trailer `/Encrypt null` clearing, trailer `/ID` precedence, or object-stream/free-entry handling.

The bounded behavior here is only explicit `/Info null` in the latest xref section stopping stale previous trailer Info inheritance while preserving current catalog/page selection.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/xref-stream `/Prev` walker, dictionary parser, catalog metadata extractor, text extractor, and WordPress smoke path. OCR, Surya/Texify/Torch, PDFium execution, model downloads, raster rendering, and external PDF tools remain intentionally outside this no-GPU markerPDF slice.
