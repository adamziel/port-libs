# markerPDF xref Prev object-stream generation review current-base

Micro-slice: `xref-prev-object-stream-generation-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native text extraction through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates low-level parser selection to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates page text extraction to pypdfium. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

PDFium object-stream parsing treats type-2 xref rows as object-stream carrier plus member index and parses members from that selected carrier stream. That makes `/Prev` type-2 row ownership and carrier generation/storage selection a native parser boundary before WordPress-visible text import. Source: https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp

## Behavior

`PdfTextExtractor::extractXrefPrevObjectStreamGenerationReview()` now exposes review-only metadata for inherited `/Prev` type-2 object-stream rows. The review reports whether each previous compressed member is preserved because the latest xref section repeats the same direct object-stream carrier storage, or skipped because the previous carrier was absent, compressed rather than direct, or replaced by current storage.

The focused test keeps the accepted skip fixture and adds the complementary preservation case:

- previous xref stream maps page object `4` as type-2 member `0` of carrier `6 0`;
- latest xref stream repeats carrier `6 0` with the same offset using zero-width generation rows;
- latest page tree references a current direct page plus previous compressed page `4 0`;
- WordPress paragraph extraction emits the current page followed by the preserved previous compressed page, while the review records `preserved_same_carrier_storage`.

The existing compressed-carrier-decoy fixture now records `skipped_prev_carrier_not_direct` for page member `4`, plus the preserved carrier-decoy member row, without leaking stale or replacement object-stream text.

## Evidence

Red-first focused run before source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips Prev type-2 rows when the object-stream carrier is only a compressed previous-generation decoy (lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php)
Call to undefined method PortLibs\MarkerPDF\PdfTextExtractor::extractXrefPrevObjectStreamGenerationReview()
FAIL preserves Prev type-2 rows when the latest section repeats the same object-stream carrier storage (lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php)
Call to undefined method PortLibs\MarkerPDF\PdfTextExtractor::extractXrefPrevObjectStreamGenerationReview()

1 test files, 0 assertions, 2 failures
```

Focused green after source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips Prev type-2 rows when the object-stream carrier is only a compressed previous-generation decoy
PASS preserves Prev type-2 rows when the latest section repeats the same object-stream carrier storage

1 test files, 48 assertions, 0 failures
```

Adjacent xref/object-stream generation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 162 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-object-stream-generation-currentbase.php
uses_current_object_stream_generation_guard=true
skips_compressed_prev_carrier_decoy=true
excludes_previous_compressed_generation_page=true
excludes_replacement_generation_object_stream=true
prev_type2_review_entries=2
skipped_unselected_prev_carrier_count=1
preserved_prev_type2_count=1
page_member_owner_policy=skipped_prev_carrier_not_direct
carrier_member_owner_policy=preserved_prev_carrier_storage
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Changed PHP lint and JSON check:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-object-stream-generation-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-prev-object-stream-generation-currentbase.php
jq empty lanes/markerpdf/lane-status.json
passed
git diff --check -- lanes/markerpdf
passed
```

## Non-Overlap

This does not repeat accepted xref-stream `/Prev` exact-offset generation repair, current hybrid table direct-generation precedence over companion `/XRefStm`, current free-entry suppression of stale `/Prev` compressed members, previous type-2 rows whose carriers were absent, current carrier replacement by generation `6 1`, object-stream omitted member-index repair, duplicate zero-width header rejection, xref-stream owner cycles, or stream-owned xref/object boundary rejection.

The bounded addition is review metadata plus a preservation fixture for the other side of the same `/Prev` generation rule: previous type-2 rows remain valid only when their direct object-stream carrier storage is still selected by the current xref chain.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/stream parser, `/Prev` chain merger, object-stream decoder, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full markerPDF parity remains dependency-gated by `pdftext`, pypdfium/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
