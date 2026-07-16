# Link Annotation Flag Operand Boundary

Slice: `markerpdf-annotations-links-boundary-current-base-20260608T223415Z`
Base: `a93e698ac06f7885c2a47509237e09731628d097`
Date: 2026-06-08 UTC

## Source Truth

This stays in the native no-GPU markerPDF/PDF-parser scope. Link annotation `/F` is an integer flag field that drives hidden/print review and WordPress link promotion, so malformed direct or indirect values with top-level trailing operands must not donate visibility bits. PDF actions remain review-only and are not executed.

## Behavior

`PdfAnnotationExtractor::intValueAfterName()` and `PdfLinkAnnotationExtractor::integerAfterName()` now reject direct dictionary values and resolved indirect object values when the integer candidate has top-level trailing operands. Valid scalar flag operands still parse normally, valid hidden annotations remain excluded from promoted WordPress links, and malformed tailed values fall back to `0` review flags instead of suppressing or decorating safe links.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFlagOperandBoundaryCurrentBaseTest.php
FAIL: expected annotation flags [4,0,0,0,2], actual [4,2,4,2,2]
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFlagOperandBoundaryCurrentBaseTest.php
1 test file / 44 assertions / 0 failures
```

Adjacent link-boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFlagOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationFlagsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationActionOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationDestinationOperandBoundaryCurrentBaseTest.php
6 test files / 259 assertions / 0 failures
```

Full annotation/link focused family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 \( -name 'PdfAnnotation*Test.php' -o -name 'PdfLink*Test.php' \) | sort)
75 test files / 2885 assertions / 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-flag-operand-boundary-currentbase.php
exit 0; annotation_flags=[4,0,0,0,2]; promoted_link_objects=[7,8,9,10]; valid_hidden_promoted=false; tail_action_leaked=false
```

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP dictionary-value and resolved-indirect trailing-operand guards; no Python, OCR, model, raster, JavaScript/action execution, external PDF tool, or live service path was added.

## Non-Overlap

This does not repeat accepted Link annotation rectangle, action, destination, presentation, string, or general flag metadata coverage. The owned behavior is limited to malformed `/F` flag operands with direct or indirect top-level trailing tokens before hidden/print review and WordPress href promotion.
