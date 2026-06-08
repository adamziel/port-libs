# markerPDF Classic XRef Object-Owned Review Boundary

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T191506Z`

Accepted base: `d057fc34a05090199b091f73d0a8aa3124240396`

## Behavior

markerPDF imports searchable PDFs through parser layers before any OCR/model stages. In native no-GPU scope, annotation/link action review must use the same current classic xref rebuild boundary as searchable text extraction. A damaged producer can append a current classic xref table without a selectable final `startxref`, then leave a private object whose literal body contains `startxref`.

This patch updates `PdfClassicXrefRebuilder` so object-owned `startxref` tokens remain unselectable, but can still bound the classic xref rebuild scan. Annotation and link review now select current URI/additional-action rows instead of an older valid `startxref` section with stale URI/JavaScript actions.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildReviewObjectOwnedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses ignored object-owned startxref only as a classic rebuild boundary before annotation review
Values are not identical
Expected: 'Current object-owned review'
Actual: 'Stale object-owned review'

1 test files, 6 assertions, 1 failures
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildReviewObjectOwnedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ignored object-owned startxref only as a classic rebuild boundary before annotation review

1 test files, 23 assertions, 0 failures
```

Adjacent xref/review regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildReviewObjectOwnedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildActionReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildObjectOwnedStartxrefCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildUnclosedStartxrefCompositeCurrentBaseTest.php
6 test files, 823 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-review-object-owned-currentbase.php
```

The smoke exits 0 and emits `uses_current_text=true`, `annotation_uri_current=true`, `additional_action_current=true`, `markdown_link_current=true`, `excludes_stale_uri=true`, `excludes_stale_javascript=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted classic xref damaged out-of-file rebuild, stale older-table startxref repair for text/metadata/attachments, post-EOF xref garbage, commented xref/startxref tokens, array/composite-contained decoys, name-token `/startxref`, name-delimited `xref/Decoy`, object-owned text/metadata attachment rebuild, malformed operand tails, or unclosed composite xref decoys. The bounded behavior is only the shared annotation/link review rebuild helper using an ignored object-owned `startxref` token as a scan boundary while keeping the token operand unselectable.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, PDF token-boundary helpers, classic xref table parser, annotation/link review extractors, and WordPress block smoke renderer. GPU/OCR/model execution, pypdfium/PIL, JavaScript execution, and external PDF tools remain intentionally out of scope.
