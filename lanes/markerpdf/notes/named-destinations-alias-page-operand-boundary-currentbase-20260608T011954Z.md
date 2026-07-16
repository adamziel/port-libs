# markerpdf named-destinations alias page-operand boundary current-base

Slice: `markerpdf-named-destinations-boundary-current-base-20260608T011954Z`  
Base accepted HEAD: `7ed7b0181dae439571f64983f19fbb9b6bfce3fe`

## Behavior

Explicit destination arrays must use a page object reference or an in-range page index as operand 0. A named-destination alias is valid when it is the whole destination value, but not when it appears as the page operand inside an explicit array such as:

```pdf
(Alias Page Operand) [/Real#20Target /FitH 111]
```

The standalone `PdfNamedDestinationExtractor`, document metadata, and outline navigation already failed this closed. `PdfActionReviewExtractor` still recursively resolved the array's first operand through the destination map, which promoted malformed `/Dest` and `/A << /S /GoTo /D ... >>` annotations into WordPress local links.

## Implementation

- `PdfActionReviewExtractor::destinationPageFromValue()` now resolves only real page operands: page references, resolved page references, or in-range page indexes.
- Destination-map admission for explicit destination arrays now validates operand 0 with the same page-operand helper instead of accepting string/name aliases recursively.
- Whole-destination aliases and action-dictionary alias chains remain covered by the adjacent alias-cycle test.

## Evidence

Red-first probe before the fix showed both malformed annotations to `Alias Page Operand` promoted as `local-destination` links for page 1.

Focused verification after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationAliasPageOperandBoundaryCurrentBaseTest.php
# 1 test files, 32 assertions, 0 failures
```

Adjacent named-destination family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationAliasPageOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationActionAliasCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDirectNamesDuplicateDestsBoundaryCurrentBaseTest.php
# 4 test files, 129 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-named-destination-alias-page-operand-currentbase.php
# exits 0; promoted_annotation_objects=[9,10], bad_dest_annotation_unpromoted=true, bad_goto_action_unpromoted=true
```

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF object parser, named-destination map parsing, annotation action review, link-span promotion, and Markdown post-processing paths. It does not execute Python, OCR, GPU/model workers, raster rendering, external PDF tools, or live services.

## Non-Overlap

This does not repeat accepted named-destination page operand validation, surplus operand validation, duplicate direct `Names`/`Dests` handling, action alias-cycle handling, outline alias review, or lightweight outline parent-operand work. The new boundary is specifically the action-review/link-promotion path for explicit destination arrays whose first operand is a destination alias.
