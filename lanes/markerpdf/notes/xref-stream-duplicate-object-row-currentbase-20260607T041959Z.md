# markerpdf xref-stream duplicate object row current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF obtains searchable PDF page text from the PDF parser layer before model/OCR stages. In the native PHP no-GPU lane, xref-stream ownership must therefore select the current file-revision object graph before WordPress paragraph extraction.
- PDF xref streams map decoded rows to object numbers through `/Index`. The native classic xref-table parser already lets later rows in the same section replace earlier rows for the same object number. This slice applies the same current-section ownership rule to xref streams so a later free/direct row can suppress an earlier compressed-object row in the same xref stream.

## Implementation

- `PdfTextExtractor::xrefStreamEntriesFromDefinition()` and `PdfMetadataExtractor::xrefStreamEntriesFromDefinition()` no longer keep the first duplicate decoded row for an object number. Later rows in the same xref stream now overwrite earlier rows, matching classic xref-table row assignment.
- Added a focused fixture where the selected xref stream first advertises object `4` as a type-2 member of `/ObjStm 6`, then later marks object `4` free in the same decoded xref stream. The same stream first advertises stale compressed Info object `10`, then later frees it. The later free rows now win, so stale compressed page text and stale Info metadata are excluded while the current direct guard page imports.
- Added a WordPress smoke that renders only the current guard paragraph and records `compressed_entry_count=0`, `stale_type2_row_suppressed=true`, `stale_info_metadata_suppressed=true`, `later_free_row_selected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamDuplicateObjectRowCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses later duplicate xref-stream object rows before object-stream expansion
Values are not identical
Expected: array (
  0 => 'Current duplicate-row guard page',
)
Actual: array (
  0 => 'Current duplicate-row guard page',
  1 => 'Stale duplicate xref-stream page',
  2 => 'Earlier type two row leaked',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamDuplicateObjectRowCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses later duplicate xref-stream object rows before object-stream expansion

1 test files, 18 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamDuplicateObjectRowCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefObjectStreamInheritedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOutlineNullWhitespaceCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMetadataOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 181 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 884 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 629 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfXrefStreamDuplicateObjectRowCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-duplicate-object-row-currentbase.php
No syntax errors detected
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-duplicate-object-row-currentbase.php
exits 0 with current guard paragraph only, stale type-2 page suppressed, stale Info metadata suppressed, later free row selected, no Python/model/OCR execution, and no external PDF tools.
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream member-index repair, zero-width type-2 index recovery, object-stream carrier base preservation, current free-carrier repair, inherited carrier reuse, incomplete object-stream header failure, object-stream offset/token-boundary rejection, xref-stream /Prev hybrid owner repair, xref-stream free-entry suppression, classic xref rebuild, or stream filter operand-owner slices. The bounded behavior is only duplicate object-number rows inside one current xref stream, where the later decoded row now owns the object before object-stream expansion.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, xref-stream decoder, object-stream expander, text extractor, object-stream review metadata, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
