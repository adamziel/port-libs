# markerPDF xref hybrid free-entry owner current-base

Micro-slice: `xref-hybrid-free-entry-owner-currentbase`
Session: `port-dev-markerpdf-xref76-20260602T224641Z`
Base accepted HEAD: `46dcbc383630b2d55e601d02ab9f1a9bd647b8e2`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`. `get_text_blocks()` delegates low-level PDF object/xref/text parsing to `pdftext.extraction.dictionary_output`, while `naive_get_text()` delegates page text extraction to pypdfium/PDFium.

Source checked locally with:

```text
curl -L --max-time 10 -s https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
```

PDF parser behavior for this native PHP slice: in a PDF 1.5 hybrid-reference section, the compatibility xref table and the `/XRefStm` stream are both current xref data for the latest `startxref`. A current xref-stream type-0 free row for an object number must suppress a stale compatibility-table direct row for that same object before page-tree text extraction.

## Behavior

The focused fixture builds:

- a compatibility xref table with object `4` pointing at a stale direct page;
- a current hybrid `/XRefStm` stream that marks object `4` free with generation `2`;
- a current page `8` in the same page tree.

Before this parser path, the hybrid stream row was ignored whenever the table already had an entry for that object number, so stale table-selected page text could leak into WordPress paragraphs. The parser now lets hybrid stream type-0 free rows own the object number over stale table direct rows. `extractXrefObjectStreamIndexReview()` also reports the hybrid free-row owner decision as review metadata:

- `owner_policy=hybrid_xref_stream_free_entry_suppressed_table_direct_object`
- `table_entry_suppressed=true`
- `direct_object_suppressed=true`

Visible WordPress text now contains only:

- `Current hybrid free owner page`
- `Hybrid xref stream free row wins`

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridFreeEntryOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS lets current hybrid xref-stream free rows own stale table direct objects before WordPress text extraction

1 test files, 27 assertions, 0 failures
```

Adjacent xref/free-entry gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridFreeEntryOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeGenerationBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalFreeEntryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php
Focused test run: 9 selected test files (root lock skipped)
PASS honors current hybrid xref free entry before conflicting object stream member
PASS lets current hybrid xref-stream free rows own stale table direct objects before WordPress text extraction
PASS exposes current hybrid table free owners before suppressed companion object-stream rows
PASS keeps referenced generation one direct page before stale hybrid object-stream generation zero
PASS keeps current incremental xref-stream free generation rows before stale Prev objects
PASS keeps current xref-stream free row before stale Prev object-stream member
PASS keeps a current object-stream base when Prev marks that carrier free
PASS reviews current free generation rows that suppress stale Prev object-stream members while rebuilding the current carrier
PASS keeps current xref-stream free entries authoritative over stale direct and previous object-stream owners

9 test files, 149 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-free-entry-owner-currentbase.php
<!-- markerpdf-xref-hybrid-free-entry-owner-currentbase-smoke {"executes_python_or_models":false,"executes_external_pdf_tools":false,"native_boundary":"current hybrid xref-stream type-0 free rows own object numbers before compatibility xref table direct rows","uses_current_hybrid_free_owner_page":true,"reports_hybrid_xref_stream_free_owner":true,"suppresses_table_direct_owner":true,"excluded_stale_table_direct_page":true,"excluded_stale_table_note":true,"page_count":1} -->
<!-- wp:paragraph -->
<p>Current hybrid free owner page</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Hybrid xref stream free row wins</p>
<!-- /wp:paragraph -->
```

Syntax and lane checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefHybridFreeEntryOwnerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-free-entry-owner-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

Status delta: behavior tests move `930 -> 931` pass / `0` fail; mapped semantics move `654 -> 655 / 78`.

## Non-Overlap

This does not repeat accepted hybrid table free-entry precedence over companion `/XRefStm` type-2 rows, pure xref-stream free rows suppressing previous direct/compressed owners, current object-stream carrier preservation when `/Prev` marks the carrier free, current free-generation rows suppressing stale `/Prev` object-stream members, type-2 member-index repair, object-stream carrier generation ownership, xref-stream owner boundary rejection, or encrypted/security xref preflight.

The new behavior is specifically a current hybrid `/XRefStm` type-0 free row owning an object number before a stale compatibility xref-table direct row for that same object.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, startxref xref table parser, hybrid `/XRefStm` decoder, free-entry owner review path, page-tree walker, text-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, pypdfium/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, OCR/rendering helpers, and external Python/model execution.
