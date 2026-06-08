# Named Destination Indirect Array Operand Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T150235Z`

Accepted base: `5630749445dec12d9837e4ce484cdb4300d60c36`

## Behavior

Catalog `/Names /Dests` name-tree readers now reject indirect `/Kids`, `/Names`, and `/Limits` operands unless the referenced object resolves to exactly one top-level array. This closes the boundary where a malformed object such as `[23 0 R] 99 0 R` or `[(Name) [4 0 R /XYZ 72 640 0]] 98 0 R` was parsed as the first array while ignoring the trailing top-level operand.

The guard is applied across:

- `PdfNamedDestinationExtractor`
- `PdfMetadataExtractor`
- `PdfOutlineExtractor`
- `PdfActionReviewExtractor`

This keeps malformed named destinations out of document metadata, TOC/navigation rows, annotation action review, link promotion, Markdown spans, and visible text while preserving valid name-tree and legacy `/Dests` rows.

## Evidence

Focused regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArrayOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 48 assertions, 0 failures`

New focused PASS cases: `2`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-array-operand-boundary-currentbase.php`

Result: exits `0`, promotes only `Valid Target`, `LegacyOk`, and the safe URI link. Tailed `/Kids` and `/Names` destination rows and outline titles remain absent from WordPress-visible output.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object parser/name-tree walkers and does not execute Python, OCR, GPU models, raster rendering, PDFium, or external PDF tools.

## Non-Overlap

This slice is additive to the accepted native named-destination work for generation-exact references, duplicate/stale catalog rows, stream-carrier rejection, scalar `/Kids`, direct child rejection, and view operand normalization. It specifically owns indirect array object boundary validation for `/Kids`, `/Names`, and `/Limits` operands.
