# markerPDF xref hybrid object-stream free owner rebase

Micro-slice: `xref46-rebase-prev-hybrid-text-extractor-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` uses pypdfium page text extraction. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

This native PHP lane owns the parser boundary before WordPress paragraphs are emitted. PDFium treats hybrid xref table rows as authoritative over companion xref-stream rows for the same object number, so same-revision `/XRefStm` type-2 rows suppressed by a current table entry should remain review-only parser metadata rather than visible text owners. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_parser.cpp>

## Behavior

`PdfTextExtractor::extractXrefObjectStreamIndexReview()` now exposes suppressed companion hybrid type-2 rows separately from selected compressed entries. When the current xref table marks object `4` as free and the companion `/XRefStm` advertises object `4` as a compressed member in object stream `6`, the current table free row remains authoritative and the review payload records:

- `suppressed_hybrid_type2_entry_count=1`
- `hybrid_table_free_owner_count=1`
- `owner_policy=hybrid_table_free_entry_preserved`

The visible WordPress import remains current-only: stale compressed page text and suppressed member dictionary text do not leak into Gutenberg paragraphs.

## Evidence

Focused xref46 rebase:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS exposes current hybrid table free owners before suppressed companion object-stream rows

1 test files, 23 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php
10 test files, 173 assertions, 0 failures
```

Central text-extractor gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php
2 test files, 620 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-objectstream-free-owner-currentbase.php
uses_current_hybrid_free_owner_page=true
suppresses_companion_type2_row=true
reports_hybrid_free_owner=true
excluded_stale_object_stream_page=true
excluded_suppressed_member_metadata=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-objectstream-free-owner-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-objectstream-free-owner-currentbase.php
php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
lane-status.json valid
git diff --check -- lanes/markerpdf
passed with no output
```

## Non-Overlap

This rebase does not repeat accepted visible-text hybrid free-entry precedence, generation-one repair over companion object-stream rows, `/Prev` object-stream carrier ownership, object-stream free-entry `/Prev` suppression, duplicate zero-width member rejection, direct xref stream owner-cycle preservation, current-stream dictionary ownership, or parser xref offset-owner boundaries.

The bounded behavior is specifically review metadata for current hybrid xref table free rows that suppress same-revision companion `/XRefStm` type-2 object-stream members.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/stream parser, hybrid `/XRefStm` merger, object-stream decoder, page-tree walker, content-token extractor, xref review payload, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
