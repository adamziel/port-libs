# markerPDF xref Prev-chain Root-null attachments current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T152558Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260605T152558Z`
Base accepted HEAD: `1bad6e3dbf9e8a6855582232164ffeb31f9e1f02`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF structure extraction to pdftext/PDFium before model/OCR stages. Under the current no-GPU markerPDF scope, this lane owns the native PHP parser boundary for searchable-PDF xref sections, trailer dictionaries, `/Prev` recursion, attachments, and WordPress import safety.

Incremental updates can keep an older catalog reachable through a previous xref section. A latest trailer that explicitly carries `/Root null` is a current-section catalog clearing boundary, so attachment preflight must not scan stale previous catalog `EmbeddedFiles`, catalog `/AF`, page `/AF`, or file-attachment annotations.

## Behavior

`PdfEmbeddedFileExtractor` now treats any explicit latest trailer `/Root` value that is not an indirect reference as a stop condition instead of falling back to scanning every stale catalog object.

`PdfAttachmentExtractor` now distinguishes an omitted latest trailer `/Root` from a present but non-reference `/Root` value. Omitted roots keep the existing damaged-trailer fallback, while `/Root null` and malformed explicit root values produce an empty catalog selection.

The focused fixture keeps a stale previous catalog with page text, XMP, Info, and an EmbeddedFiles name tree, then appends a latest xref stream with `/Root null /Info 6 0 R /Prev ...`. Current Info metadata is still selected, but stale previous attachments are excluded from both low-level embedded-file extraction and WordPress attachment summaries.

## Red-First Evidence

Before source changes, after adding attachment assertions to the existing root-null test, the failing output included:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL stops previous catalog inheritance when latest xref-stream trailer sets Root null
Expected: array (
)
Actual: array (
  0 =>
  array (
    'source' => 'catalog_names_embedded_files',
    'name' => 'stale-root-null.xml',
...
1 test files, 3 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS stops previous catalog inheritance when latest xref-stream trailer sets Root null

1 test files, 23 assertions, 0 failures
```

Adjacent xref/attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfXrefPrevChainAttachmentNearMissPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
4 test files, 947 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-root-null-currentbase.php
```

The smoke exits 0 and reports `root_null_latest_xref_stream_stops_prev_catalog=true`, `current_info_selected=true`, `stale_prev_text_excluded=true`, `stale_prev_metadata_excluded=true`, `stale_prev_embedded_files_excluded=true`, `attachment_summary_empty=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-root-null-currentbase.php
```

All changed PHP files reported no syntax errors.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat sparse latest trailers that omit `/Root`, current Info selection, text-side `/Root null` page suppression, latest free-row catalog suppression, direct `/Prev` helper repair, damaged middle `/Prev` repair, generation-exact attachment import, near-miss previous xref repair, xref-stream `/W` and `/Index` helper decoding, object-stream carrier repair, hybrid companion xref merging, or encrypted/crypt-filter attachment preflight.

The bounded behavior here is only attachment and embedded-file catalog selection when the latest xref section explicitly clears `/Root`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/xref-stream `/Prev` walker, dictionary parser, embedded-file extractor, attachment preflight summarizer, metadata extractor, and WordPress smoke path. OCR, Surya/Texify/Torch, PDFium execution, model downloads, raster rendering, and external PDF tools remain intentionally outside this no-GPU markerPDF slice.
