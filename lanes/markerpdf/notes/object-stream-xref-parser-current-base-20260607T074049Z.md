# markerpdf object-stream xref parser current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF obtains searchable PDF page text from the PDF parser layer before model/OCR stages. In the native no-GPU PHP lane, latest xref ownership must select the current file-revision object graph before WordPress paragraph extraction.
- PDF 1.5 object streams contain generation-zero indirect objects selected by xref-stream type-2 rows. This slice handles a malformed but recoverable current update: the latest xref stream selects a compressed catalog member and its direct object-stream carrier, but omits reachable compressed `/Pages` and `/Page` member rows. Those reachable current members should be repaired before stale `/Prev` rows are inherited.

## Implementation

- `PdfTextExtractor::repairOmittedCurrentUpdateGraphRows()` now tracks whether a reachable reference came from a trailer, a direct current object, or a current compressed member.
- Reachable generation-zero object-stream members omitted from the current xref section are inferred only when the reference came from another current compressed member and exactly one current selected `/ObjStm` carrier contains a safe matching member.
- Direct current page trees still require explicit xref ownership for compressed members, preserving accepted suppression of stale `/Prev` type-2 rows when a current update replaces an object-stream carrier.
- Added `PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php`, where a stale previous xref table points to stale page text, while the current xref stream selects only a compressed catalog and carrier. The current compressed `/Pages` and `/Page` members are now repaired and the current content stream is selected.
- Added `wordpress-pdf-xref-object-stream-omitted-graph-currentbase.php`, which emits Gutenberg paragraph output plus review metadata proving current text selection, stale Prev suppression, `compressed_entry_count=3`, xref-selected object-stream ownership for objects 2 and 3, and no model/external-tool execution.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs omitted compressed page-tree graph members before stale Prev rows
Values are not identical
Expected: array (
  0 => 'Current omitted graph page',
  1 => 'Reachable compressed members repaired',
)
Actual: array (
  0 => 'Stale omitted graph page',
  1 => 'Previous xref row leaked',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs omitted compressed page-tree graph members before stale Prev rows

1 test files, 18 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStream*CurrentBaseTest.php
Focused test run: 67 selected test files (root lock skipped)
67 test files, 1372 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 647 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-omitted-graph-currentbase.php
No syntax errors detected
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-omitted-graph-currentbase.php
exits 0 with current_text_selected=true, stale_prev_text_suppressed=true, compressed_entry_count=3, object_2_owner_policy=xref_selected_object_stream_carrier, object_3_owner_policy=xref_selected_object_stream_carrier, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
git diff --check -- lanes/markerpdf
exits 0
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted duplicate xref-stream rows, xref-stream row alignment, malformed `/W` or `/Index`, zero-width member-index repair, omitted object-stream carrier repair, inherited carrier reuse, current carrier generation repair, previous hybrid owner suppression, stream-member rejection, object-stream member offset token boundaries, or page-resource entry-tail behavior. The bounded behavior is only current compressed graph repair for reachable omitted `/ObjStm` members when the current trailer root itself resolves through a compressed member.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, xref-stream decoder, object-stream member table parser, stream filter decoder, text extractor, xref review metadata, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
