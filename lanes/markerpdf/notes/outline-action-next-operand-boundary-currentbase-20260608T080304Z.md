# Outline Action Next Operand Boundary Current Base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260608T080304Z`
Base: `3ce1ddaf86364b7c1332f264ea0b1bfd575a80dc`

## Source Truth

- Upstream markerPDF exposes PDF outline/table-of-contents metadata as document navigation metadata, separate from visible page text.
- PDF action dictionaries allow `/Next` action chains, but the selected dictionary value must be a single action or an array. A top-level value such as `/Next 13 0 R 14 0 R` is malformed because it leaves extra operands in the same dictionary value slot.
- WordPress imports should fail closed at this trust boundary: keep the safe local `/S /GoTo` action and resolved destination review metadata, but do not traverse tailed URI or JavaScript followups into navigation review, document metadata, remote actions, or Gutenberg paragraphs.

## Implementation

- `PdfMetadataExtractor::documentOutlineActionChainRows()` now checks the raw action dictionary `/Next` value for trailing top-level operands before extending `document_outline` action-chain summaries.
- `PdfOutlineExtractor` now applies the same guard when building action target context and review-action rows for referenced action dictionaries.
- Valid `/Next` arrays and ordinary action chains remain covered by the adjacent outline action-chain tests.

## Evidence

- Red-first focused run before the fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionNextOperandBoundaryCurrentBaseTest.php`
  failed after `26` assertions because the malformed `/Next` tail was traversed into a URI followup.
- Focused run after the fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionNextOperandBoundaryCurrentBaseTest.php`
  passed with `1` test file, `51` assertions, `0` failures.
- Adjacent outline action-chain run:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionNextOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataActionChainBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationFitActionChainCurrentBaseTest.php`
  passed with `6` test files, `316` assertions, `0` failures.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-outline-action-next-operand-boundary-currentbase.php`
  exits `0` and reports `malformed_next_followups_excluded=true`, `metadata_payload_excluded=true`, `navigation_payload_excluded=true`, `visible_text_excludes_outline_action_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `lane-status.json` moves `phpPass` from `2983` to `2984`.
- `lane-status.json` moves `wordpressScenarios` from `2474` to `2475`.
- `UPSTREAM_TEST_MANIFEST.json` adds `pdfOutlineActionNextOperandBoundaryCurrentBase` as one mapped behavior.

## Non-Overlap

This does not repeat the accepted outline action-chain metadata slice, destination action-chain slice, outline destination action operand-boundary slice, outline sibling `/Next` operand boundary, or direct local action metadata. It is bounded to malformed `/Next` values inside referenced outline action dictionaries.

## Dependency Closure

No new support component is required. The slice reuses the native PHP PDF parser/object dictionary scanner and outline/metadata extractors. It does not invoke OCR, Surya, Texify, Torch, Python model workers, pypdfium, PIL, raster rendering, external PDF tools, or live services.
