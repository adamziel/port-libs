# markerpdf outline lightweight parent operand boundary current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF exposes outline/TOC metadata from the PDF document outline (`pdf_toc`) separately from visible page text. In the no-GPU PHP lane, native outline parsing must therefore fail closed on malformed outline relationship operands before metadata is promoted into WordPress navigation review.
- A top-level outline item relationship such as `/Parent 5 0 R 9 0 R` has a valid-looking first indirect reference followed by an extra top-level operand. This slice treats that as malformed instead of accepting the first reference and letting a decoy outline item enter lightweight `pdf_toc` fallback or navigation metadata.

## Implementation

- `PdfTextExtractor` now rejects lightweight outline `First`, `Last`, and `Next` references when their top-level operands have trailing top-level data before building fallback `pdf_toc` rows.
- `PdfTextExtractor::lightweightOutlineItemParentMatches()` and `lightweightOutlineItemPrevMatches()` now reject tailed `/Parent` and `/Prev` operands before comparing the first reference.
- `PdfOutlineExtractor` now carries the current outline object id into parent/previous sibling checks and rejects tailed `/Parent` and `/Prev` operands before promoting items into the rich TOC/navigation review path.
- Added a focused fixture where the catalog outline root points at an item whose `/Parent` operand is `/Parent 5 0 R 9 0 R`. The item also carries a valid-looking destination/action so the test proves the row is excluded from lightweight `pdf_toc`, rich TOC/navigation review, and document outline metadata.
- Added a WordPress smoke that verifies the malformed outline title/action are excluded while visible page text and trailer Info title remain available.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightParentOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed outline Parent references before lightweight pdf_toc fallback promotion
Expected: []
Actual: [{'title':'Malformed Parent Operand Chapter','level':1,'page':0}]
FAIL keeps malformed lightweight Parent operand rows out of navigation review metadata
Expected: []
Actual: navigation outline row for object 6

1 test files, 4 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightParentOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed outline Parent references before lightweight pdf_toc fallback promotion
PASS keeps malformed lightweight Parent operand rows out of navigation review metadata

1 test files, 19 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightParentOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataNextOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataPrevBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataMissingParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 642 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-lightweight-parent-operand-boundary-currentbase.php
malformed_parent_operand_rejected=true
trailing_parent_decoy_excluded=true
malformed_action_excluded=true
visible_text_excludes_outline_metadata=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline `/Next`, `/Prev`, `/Last`, valid-parent ownership, missing-parent, catalog operand, trailer-root, duplicate-root, metadata-stream/reference, or xref/filter/CMap parser slices. The bounded behavior is only tailed top-level `/Parent` and directly coupled `/Prev` outline relationship operands before lightweight TOC fallback and rich navigation promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, top-level dictionary operand boundary checks, outline extractor, text extractor fallback, metadata extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
