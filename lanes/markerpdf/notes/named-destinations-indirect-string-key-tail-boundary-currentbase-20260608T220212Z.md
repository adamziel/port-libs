# Named Destination Indirect String Key Tail Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T220212Z`

Accepted base: `57b759a699f569c167c3b49c30b46a9decbf4d26`

## Behavior

Catalog `/Names /Dests` name-tree readers now reject indirect string key objects unless the referenced object resolves to exactly one top-level PDF string. This closes the boundary where a malformed object such as `(Tailed Key) /Extra` was accepted as a valid name-tree key while ignoring the trailing top-level operand.

The guard is applied across:

- `PdfNamedDestinationExtractor`
- `PdfMetadataExtractor`
- `PdfOutlineExtractor`
- `PdfActionReviewExtractor`

This keeps tailed indirect key rows out of document destination metadata, outline/TOC navigation, local annotation action review, link promotion, Markdown spans, and visible text while preserving valid direct name-tree keys and legacy catalog `/Dests` rows.

## Evidence

Red-first focused regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectStringKeyTailBoundaryCurrentBaseTest.php`

Result before patch: failed with `Tailed Key` admitted as a named destination and local annotation action.

Focused regression after patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectStringKeyTailBoundaryCurrentBaseTest.php`

Result: `1 test files, 46 assertions, 0 failures`

New focused PASS cases: `2`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-key-tail-currentbase.php`

Result: exits `0`, promotes only `Valid Target`, `LegacyOk`, and the safe URI link. The tailed indirect key destination, outline title, and `FitH 710` payload remain absent from WordPress-visible output.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object parser/name-tree walkers and does not execute Python, OCR, GPU models, raster rendering, PDFium, or external PDF tools.

## Non-Overlap

This slice is additive to accepted native named-destination work for generation-exact references, duplicate/stale catalog rows, stream-carrier rejection, scalar `/Kids`, direct child rejection, indirect array operand boundaries, sparse indirect string key recovery, decoded-key collisions, and view operand normalization. It specifically owns indirect string objects used as name-tree keys when the referenced string body has trailing top-level operands.
