# markerPDF annotations links NewWindow operand boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T175612Z`

Base accepted HEAD: `f2ba04d4070c87822ee15c9bf00e9247a5017259`

## Source Truth

- PDF action dictionaries may store operands indirectly. Link annotation `/A << /S /GoToR ... /NewWindow 20 0 R >>` with object `20 0 obj true endobj` should carry the same remote-document review metadata as direct `/NewWindow true`.
- `/Launch` remains blocked/review-only even when `/NewWindow` is resolved, and chained URI followups do not donate a WordPress link.
- Under the current markerPDF no-GPU scope, this is native searchable-PDF parser behavior only: no Python, pdftext/pypdfium execution, OCR, Surya/Texify/Torch models, PDF action execution, or external PDF tools.

## Implementation

- `PdfActionReviewExtractor` now uses the existing resolved `boolValue()` helper for `/NewWindow` on `GoToR` and `Launch` action review rows.
- Added `PdfLinkAnnotationNewWindowOperandBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-link-annotation-new-window-operand-currentbase.php`.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationNewWindowOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect Link action NewWindow booleans before WordPress review metadata promotion
Values are not identical
Expected: true
Actual: NULL
1 test files, 4 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationNewWindowOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect Link action NewWindow booleans before WordPress review metadata promotion
1 test files, 38 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-new-window-operand-currentbase.php --self-test
```

The smoke emits `annotation_new_window_values=[true,false,true,null]`, `promoted_link_objects=[7,8,10]`, `remote_new_window_values=[true,false]`, `launch_promoted=false`, `annotation_payload_text_visible=false`, and all PDF action/model/external-tool execution flags false.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRViewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationActionOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationFileSpecDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationNewWindowOperandBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 272 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotation*Test.php
Focused test run: 54 selected test files (root lock skipped)
54 test files, 1951 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotation*Test.php lanes/markerpdf/tests/PdfPageAnnotation*Test.php lanes/markerpdf/tests/PdfLinkAnnotationNewWindowOperandBoundaryCurrentBaseTest.php
Focused test run: 25 selected test files (root lock skipped)
25 test files, 1265 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/Pdf*Action*Test.php lanes/markerpdf/tests/PdfLinkAnnotationNewWindowOperandBoundaryCurrentBaseTest.php
Focused test run: 74 selected test files (root lock skipped)
74 test files, 3613 assertions, 0 failures
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PHP behavior cases: `3352 -> 3353`.
- WordPress scenarios: `2731 -> 2732`.
- Added 38 focused assertions in the new test file.

## Non-Overlap

This does not repeat accepted direct `NewWindow` metadata, remote GoToR destination dictionary validation, duplicate Filespec key rejection, `/IsMap`, URI Base, previous URI `/PA`, primary action scalar/array rejection, hidden/no-view/optional-content filtering, generation/xref action selection, QuadPoints, rotated/UserUnit geometry, or Image XObject CTM recovery.

The bounded behavior is only indirect boolean operand resolution for Link annotation action `/NewWindow` review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF tokenizer/object resolver, action review parser, Link annotation extractor, supplied span model, Markdown merge path, and WordPress smoke path. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated and intentionally out of scope for this no-GPU parser slice.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around annotations, forms, fonts/CMaps, stream filters, xref repair, metadata, outlines, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
