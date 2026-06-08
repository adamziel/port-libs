# markerpdf object-stream xref parser current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF obtains searchable PDF page text from PDF parser output before OCR/layout/model stages. In this native no-GPU PHP lane, xref and object-stream ownership determines which current-revision objects become WordPress paragraph text.
- PDF 1.5 object streams may use indirect `/N`, `/First`, `/Filter`, and `/DecodeParms` operands. Existing current-base coverage decoded those operands during normal object-stream expansion; this slice applies the same bounded operand recovery inside current-update omitted graph repair.

## Implementation

- `PdfTextExtractor::repairOmittedCurrentUpdateGraphRows()` now builds a current-section object map containing only xref-selected direct objects plus selected compressed helper members.
- The two repair-only object-stream member decoders now use that map when decoding the current `/ObjStm` carrier, so `/N 90 0 R` and `/First 91 0 R` can resolve from selected compressed helper members before stale `/Prev` rows are inherited.
- Missing graph rows remain excluded from the operand map until they are reached through a current compressed member, preserving the accepted fail-closed behavior for direct current page trees and stale previous rows.
- Added `PdfXrefObjectStreamOmittedGraphCompressedOperandCurrentBaseTest.php`, where the latest xref stream selects a compressed catalog member, a direct object-stream carrier, and compressed helper members for the carrier `/N` and `/First`, but omits the reachable compressed `/Pages` and `/Page` member rows. Before the fix, stale previous page rows were selected; after the fix, the current compressed page graph and current content stream are selected.
- Added `wordpress-pdf-xref-object-stream-omitted-graph-compressed-operand-currentbase.php`, which emits Gutenberg paragraph output plus smoke metadata proving current text selection, stale `/Prev` suppression, compressed helper operand selection, and no model/external-tool execution.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCompressedOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs omitted object-stream graph rows whose carrier N and First are compressed helpers
Expected: [Current compressed operand graph page, Compressed N First helpers repaired]
Actual: [Stale compressed operand graph page, Previous helper rows leaked]
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCompressedOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs omitted object-stream graph rows whose carrier N and First are compressed helpers
1 test files, 21 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCompressedOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCompressedOperandCascadeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamNestedHelperObjectStreamCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 96 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStream*CurrentBaseTest.php
Focused test run: 77 selected test files (root lock skipped)
77 test files, 1633 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCompressedOperandCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-omitted-graph-compressed-operand-currentbase.php
No syntax errors detected
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-omitted-graph-compressed-operand-currentbase.php
exits 0 with current_text_selected=true, stale_prev_text_suppressed=true, compressed_operand_helpers_selected=true, compressed_entry_count=5, object_2_owner_policy=xref_selected_object_stream_carrier, object_3_owner_policy=xref_selected_object_stream_carrier, helper_90_selection_policy=explicit_member_index, helper_91_selection_policy=explicit_member_index, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted omitted compressed graph repair, compressed object-stream operand cascade behavior, xref-stream compressed helper operand decoding, zero-width member-index repair, duplicate object-stream rows, malformed `/W` or `/Index`, inherited carrier reuse, hybrid free-entry suppression, object-stream member offset token boundaries, stream-member rejection, attachment associated-file array boundaries, OCR/model execution, or table/equation handoffs. The bounded behavior is only current-update omitted graph repair when the selected object-stream carrier itself needs compressed helper members for `/N` and `/First`.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref-stream decoder, object-stream member table parser, stream filter decoder, text extractor, xref review metadata, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.
