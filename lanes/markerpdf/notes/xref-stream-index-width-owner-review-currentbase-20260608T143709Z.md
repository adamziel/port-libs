# markerpdf xref-stream W Index owner review current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF obtains searchable PDF text from the PDF parser layer before model/OCR stages. In the native PHP no-GPU lane, xref stream `/W` and `/Index` operands must be resolved from the current xref-selected object graph before object-stream members are expanded.
- Existing current-base coverage already proved indirect `/W` and `/Index` helper arrays are honored for extraction. This slice adds import-review evidence so WordPress preflight can see whether those helper operands were selected by the current xref stream instead of silently using scanned fallback objects.

## Implementation

- `PdfTextExtractor::extractXrefStreamFilterLengthOwnerReview()` now reviews indirect `/W` and `/Index` operands alongside `/Filter`, `/Length`, and `/Size`.
- The review adds top-level and per-entry `indirect_w_count` and `indirect_index_count`, exposes `w_operand` and `index_operand` owner rows, and includes those operands in the selected/unresolved owner policy.
- Added a focused PDF fixture where `/W`, `/Index`, and `/Size` are indirect xref-selected helper objects. The selected xref stream uses those helpers to choose current object-stream page object `4`, while stale fallback object-stream text remains excluded.
- Added a WordPress smoke that renders only current Gutenberg paragraphs and records `indirect_w_count=1`, `indirect_index_count=1`, `indirect_size_count=1`, `xref_selected_operand_count=3`, all three helper owner policies as `xref_selected_direct_object`, and no Python/model/external-tool execution.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndexWidthOwnerReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reviews indirect xref-stream W and Index owners before object-stream current-base selection
Values are not identical
Expected: 1
Actual: NULL

1 test files, 22 assertions, 1 failures
PHP Warning:  Undefined array key "indirect_w_count" in lanes/markerpdf/tests/PdfParserXrefStreamIndexWidthOwnerReviewCurrentBaseTest.php on line 125
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndexWidthOwnerReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reviews indirect xref-stream W and Index owners before object-stream current-base selection

1 test files, 46 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndexWidthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamIndirectIntegerArrayElementsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedSizeDefaultRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOffsetBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
PASS rejects compressed xref-stream Filter helpers whose object-stream offsets start inside comments
PASS resolves xref-stream Filter operands from current compressed object-stream helpers
PASS resolves compressed xref-stream Size helper before default row-range object-stream selection
PASS reviews xref-stream indirect Filter and Length owners before current-base WordPress text extraction
PASS reviews indirect xref-stream W and Index owners before object-stream current-base selection
PASS resolves indirect xref-stream W and Index arrays before object-stream current-base selection
PASS resolves indirect integer elements in xref-stream W and Index arrays before object-stream selection

7 test files, 217 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-index-width-owner-review-currentbase.php
exits 0 with uses_current_indirect_xref_array_page=true, index_width_owners_reviewed=true, excluded_stale_indirect_xref_array_page=true, indirect_w_count=1, indirect_index_count=1, indirect_size_count=1, xref_selected_operand_count=3, decoded_with_current_operands=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserXrefStreamIndexWidthOwnerReviewCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserXrefStreamIndexWidthOwnerReviewCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-index-width-owner-review-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-stream-index-width-owner-review-currentbase.php
```

```text
git diff --check -- lanes/markerpdf
exits 0
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream header parsing, object-stream member index repair, object-stream offset/token-boundary rejection, duplicate xref-stream row handling, compressed `/Size` default range repair, indirect `/W` and `/Index` extraction behavior, indirect integer array element resolution, compressed `/Filter` helper ownership, or xref stream `/Prev`/hybrid owner repair. The bounded behavior is only review visibility for current xref-selected `/W` and `/Index` helper operands.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, xref-stream decoder, object-stream expander, operand-owner review helpers, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI workers, JavaScript/PDF action execution, decryption/password validation, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
