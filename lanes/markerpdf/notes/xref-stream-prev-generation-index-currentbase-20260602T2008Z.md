# markerPDF xref-stream Prev generation Index current-base boundary

Slice: `xref-stream-prev-generation-index-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF parsing/text extraction to `pdftext` and `pypdfium2` in `marker/pdf/extract_text.py`, so xref traversal and incremental update ownership are native parser boundaries for this PHP lane.

PDF xref streams map decoded rows to object numbers through `/Index`; for type-1 entries, field 2 is the byte offset and field 3 is the generation. `/Prev` points to older xref sections. A current section row for an object number is authoritative over previous sections, and malformed duplicate rows inside the same current xref stream must not let stale generation-zero metadata or content replace the first current generation row.

## Behavior

`PdfMetadataExtractor::xrefStreamEntriesFromDefinition()` now preserves the first decoded row for an object number inside one xref stream section, matching the existing text extractor behavior. Later duplicate `/Index` rows in the same stream are ignored before the `/Prev` chain is merged.

The focused fixture builds a previous xref stream with generation-zero catalog, page, Info, and XMP metadata, then appends generation-one replacements. The current xref stream uses sparse `/Index` ranges and explicit `/W [1 4 1]` generation bytes, but also includes malformed duplicate rows that point back to stale generation-zero content, Info, and XMP objects. WordPress import now keeps the current XMP title, current Info fields, `en-US` catalog language, and current page text while excluding stale metadata and text.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps current xref-stream generation Index rows before stale Prev duplicates in metadata imports (lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php)
Values are not identical
Expected: 'Current Indexed Generation XMP Title'
Actual: 'Stale Indexed Generation XMP Title'

1 test files, 3 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps current xref-stream generation Index rows before stale Prev duplicates in metadata imports

1 test files, 12 assertions, 0 failures
```

Adjacent xref/metadata gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 902 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-prev-generation-index-currentbase.php
current_metadata_title_selected=true
current_info_title_selected=true
current_generation_text_selected=true
stale_generation_metadata_excluded=true
stale_generation_text_excluded=true
page_count=1
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted xref-stream zero-width `/W` offset repair, duplicate sparse `/Index` text extraction, hybrid table generation precedence, object-stream generation repair, previous object-stream carrier review, latest trailer `/Root` recovery, xref stream filter DecodeParms handling, or encrypted xref-stream metadata priority. The new behavior is specifically metadata extraction for current xref-stream `/Prev` chains with explicit generation bytes and malformed duplicate sparse `/Index` rows.

## Dependency Closure

No new support component is needed. The slice reuses the native direct-object scanner, xref-stream decoder, `/Prev` chain merger, stream decoder, XMP/Info metadata extraction, and text extraction paths. Full upstream runner parity remains blocked by heavyweight Python/runtime dependencies (`pdftext`, `pypdfium2`, Surya/Torch, tabled, Texify, Streamlit/FastAPI, benchmark/model download tooling), none of which are executed here.
