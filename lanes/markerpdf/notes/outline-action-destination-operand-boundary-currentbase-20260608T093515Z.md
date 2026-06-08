# Outline Action Destination Operand Boundary Current Base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260608T093515Z`
Base: `fc9cc5ac780ad879f0d013a4c9808a06a29c2d50`

## Source Truth

- Upstream markerPDF treats PDF outlines/table-of-contents entries as navigation metadata, separate from visible page text.
- PDF GoTo action dictionaries use `/D` as a single local destination value. A dictionary entry such as `/D [3 0 R /FitH 720] 99 0 R` is malformed because the selected destination array is followed by an extra top-level operand before the next key.
- WordPress imports should fail closed at this trust boundary: keep the outline row and the non-executing GoTo action review metadata, but do not promote the partial `/D` array into TOC/navigation rows and do not inspect the tailed decoy URI payload.

## Implementation

- `PdfMetadataExtractor` now checks referenced outline GoTo action dictionaries for tailed `/D` operands before resolving `document_outline.items[*].destination`.
- The same metadata path records `action_destination_operand_boundary_review` with `source=outline_action_destination_operand_boundary`, `status=rejected_malformed_outline_action_d_operand`, operand shape metadata, and trailing reference object numbers.
- `PdfOutlineExtractor` now applies the same referenced-action `/D` guard in TOC destination resolution, destination view/page lookup, action target-context propagation, and catalog/outline action review rows.
- Valid direct outline `/Dest`, item `/A`, action chains, named destinations, and clean GoTo actions remain covered by the adjacent outline tests.

## Evidence

- Red-first focused run before the fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionDestinationOperandBoundaryCurrentBaseTest.php`
  failed with `1` test file, `8` assertions, `2` failures because the malformed action `/D` prefix was promoted as the first TOC row and document outline resolved-destination count was `2`.
- Focused run after the fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionDestinationOperandBoundaryCurrentBaseTest.php`
  passed with `1` test file, `49` assertions, `0` failures.
- Adjacent outline boundary/action run:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionDestinationOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationActionOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`
  passed with `5` test files, `516` assertions, `0` failures.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-outline-action-destination-operand-boundary-currentbase.php`
  exits `0` and reports `toc_titles=["WordPress Action D Clean Appendix"]`, `resolved_destination_count=1`, `unresolved_destination_count=1`, `review_status=rejected_malformed_outline_action_d_operand`, `trailing_reference_object_numbers=[99]`, `decoy_uri_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `lane-status.json` moves `phpPass` from `3020` to `3022`.
- `lane-status.json` moves `wordpressScenarios` from `2499` to `2500`.
- This handoff adds `2` focused PHP PASS cases and `49` focused assertions for the new action `/D` operand-boundary slice.

## Non-Overlap

This does not repeat the accepted outline item `/Dest` and `/A` operand-boundary slice, outline action `/Next` operand-boundary slice, destination action-chain context slice, named-destination metadata, remote GoTo action review, or PageLabels inherited touching-limits work. It is bounded to malformed `/D` values inside referenced local GoTo action dictionaries attached to outline items.

## Dependency Closure

No new support component is required. The slice reuses the native PHP PDF object parser, raw selected object-body dictionary scanner, outline extractor, metadata extractor, and text extractor. It does not invoke OCR, Surya, Texify, Torch, Python model workers, pypdfium, PIL, raster rendering, external PDF tools, PDF action execution, decryption/password validation, or live services.
