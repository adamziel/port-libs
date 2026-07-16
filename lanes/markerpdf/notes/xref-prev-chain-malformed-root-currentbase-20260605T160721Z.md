# markerPDF xref Prev-chain malformed Root current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T160721Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260605T160721Z`
Base accepted HEAD: `08542b78fdb2262014f0d9fbe01790f70b8e93d1`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF parsing to pdftext/PDFium before model/OCR/layout stages. Under the current no-GPU markerPDF scope, this lane owns the native PHP parser boundary for xref sections, trailer dictionaries, `/Prev` recursion, metadata, attachments, and WordPress import safety.

Incremental updates can keep an older catalog reachable through `/Prev`. A latest trailer with no `/Root` may intentionally inherit the previous root, but a latest trailer with an explicit malformed `/Root` value is still a current-section catalog decision. Since trailer `/Root` must be an indirect catalog reference to be usable, the native parser must fail closed instead of inheriting stale previous page trees, catalog metadata, or attachments.

## Behavior

`PdfTextExtractor` now treats any explicit latest xref table or xref-stream trailer `/Root` value that is not an indirect reference as a stop while resolving trailer Root through the `/Prev` chain. Omitted `/Root` still follows the already-covered sparse latest trailer path, and valid indirect `/Root N G R` references still select the named current catalog.

The focused fixture builds a previous xref table with stale catalog, page text, XMP metadata, Info, and EmbeddedFiles. It then appends a latest xref stream with `/Root /CurrentRootCleared /Info 6 0 R /Prev ...` and current Info metadata. WordPress import now selects the current Info dictionary while suppressing stale previous catalog metadata, attachments, and page text.

## Red-First Evidence

Before source changes, after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainMalformedRootCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL stops previous catalog inheritance when latest xref-stream trailer has malformed Root (lanes/markerpdf/tests/PdfXrefPrevChainMalformedRootCurrentBaseTest.php)
Values are not identical
Expected: array (
)
Actual: array (
  0 => 'Stale malformed Root Prev page',
)

1 test files, 1 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainMalformedRootCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS stops previous catalog inheritance when latest xref-stream trailer has malformed Root

1 test files, 23 assertions, 0 failures
```

Adjacent xref root/current-base check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainMalformedRootCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainRootNullCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
PASS repairs current metadata generation objects through damaged xref Prev chain offsets
PASS does not resolve generation-zero catalog Metadata to a generation-one current xref object
PASS repairs trailer Root generation before embedded-file name-tree attachment import
PASS repairs malformed current xref-stream Index rows by direct offsets before same-generation metadata and attachments
PASS repairs same-generation current update objects when xref-stream Prev rows have damaged explicit offsets
PASS repairs same-generation current update objects when xref-stream Prev rows point at stale explicit offsets
PASS repairs same-generation xref-stream rows whose damaged offsets point at a different current object
PASS repairs same-generation current update objects when classic xref Prev rows have damaged explicit offsets
PASS repairs latest classic xref-table stale rows after damaged Prev pointer recovery
PASS repairs classic xref-table current update rows when Prev is an indirect numeric helper
PASS repairs classic xref-table direct Prev helper before post-table same-number decoys
PASS repairs current metadata and attachments when xref-stream Prev is a compressed object-stream numeric helper
PASS repairs current xref-stream rows when direct Prev helper is shadowed after startxref target
PASS resolves xref-stream W and Index helpers before stale post-xref direct objects
PASS repairs attachment preflight rows when xref-stream W and Index operands are indirect
PASS repairs damaged middle Prev pointers to the earlier base xref before post-xref decoys
PASS resolves current Info from previous xref section when latest xref stream omits Info
PASS stops previous Info inheritance when latest xref-stream trailer sets Info null
PASS stops previous Info inheritance in lightweight outline metadata when latest xref-stream trailer sets Info null
PASS suppresses previous metadata text and attachments when latest xref-stream rows free those objects
PASS suppresses previous metadata and attachments when latest xref-stream rows point at stale wrong owners
PASS blocks previous trailer root fallback when latest xref-stream frees inherited catalog
PASS blocks previous trailer root fallback when latest xref-stream frees inherited catalog through damaged Prev repair
PASS blocks previous trailer root fallback when latest classic table frees inherited catalog through direct Prev helper
PASS stops previous catalog inheritance when latest xref-stream trailer has malformed Root
PASS stops previous catalog inheritance when latest xref-stream trailer sets Root null

3 test files, 481 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-malformed-root-currentbase.php
```

The smoke exits 0 and reports `malformed_root_latest_xref_stream_stops_prev_catalog=true`, `current_info_selected=true`, `stale_prev_text_excluded=true`, `stale_prev_metadata_excluded=true`, `stale_prev_embedded_files_excluded=true`, `attachment_summary_empty=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainMalformedRootCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-malformed-root-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat sparse latest trailers that omit `/Root`, explicit `/Root null`, attachment-only malformed-root fallback, current Info selection, latest free-row catalog suppression, direct `/Prev` helper repair, damaged middle `/Prev` repair, generation-exact attachment import, near-miss previous xref repair, xref-stream `/W` and `/Index` helper decoding, object-stream carrier repair, hybrid companion xref merging, encrypted/crypt-filter preflight, or page-tree `/Kids` token-boundary behavior.

The bounded behavior here is only text/catalog root selection when the latest xref section explicitly provides a malformed non-reference `/Root` value.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/xref-stream `/Prev` walker, dictionary parser, metadata extractor, text extractor, embedded-file extractor, attachment preflight summarizer, and WordPress smoke path. OCR, Surya/Texify/Torch, PDFium execution, model downloads, raster rendering, and external PDF tools remain intentionally outside this no-GPU markerPDF slice.
