# Link Annotation Action Generation Metadata

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260607T071838Z`

Base accepted HEAD: `f59b519bb251aefa4fdb1c3cda61b4eaa10eaee0`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- PDF indirect references carry both object number and generation. The native markerPDF parser already uses the exact `N G R` reference when resolving Link annotations and action dictionaries.
- Annotation action review is metadata only. WordPress import must not execute `/A`, `/AA`, `/Next`, JavaScript, Launch, remote-document, or media actions.

## Implementation

- `PdfActionReviewExtractor::reviewActionsFromValue()` now exposes `action_generation` beside `action_object` for indirect action dictionaries.
- Chained `/Next` action rows and additional `/AA` action rows keep their own indirect generation metadata after recursive action review.
- The existing WordPress generation-boundary smoke now emits action object and generation arrays, proving the promoted Link review rows expose `30 1 R`, `32 1 R`, and `31 1 R` while stale generation-zero action bodies remain excluded.

## Focused Evidence

Red-first focused check after adding assertions and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps link annotation object generations exact before WordPress span promotion (lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 1,
  1 => 1,
)
Actual: array (
)

1 test files, 8 assertions, 1 failures
```

Focused test after the extractor change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps link annotation object generations exact before WordPress span promotion

1 test files, 40 assertions, 0 failures
```

Adjacent annotation action family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectActionSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationFreedActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 698 assertions, 0 failures
```

Syntax:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-annotation-generation-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-link-annotation-generation-boundary-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-generation-boundary-currentbase.php
```

The smoke emits `action_objects=[30,32]`, `action_generations=[1,1]`, `additional_action_objects=[31]`, `additional_action_generations=[1]`, `preserves_action_generation_metadata=true`, `excludes_stale_generation_links=true`, `visible_text_excludes_link_targets=true`, and all PDF action, JavaScript, Python/model, and external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Status Delta

- Adds 8 focused assertions to the existing generation-boundary test file, moving its focused coverage from 32 to 40 assertions.
- `phpPass` and `wordpressScenarios` are intentionally unchanged because this patch extends an existing tracked test and existing WordPress smoke rather than adding a new test file or scenario.
- Mapped upstream denominator is unchanged; this deepens the already mapped annotation/link action boundary.

## Non-Overlap

This does not repeat accepted page `/Annots` ownership, escaped annotation names, exact annotation object-generation selection, optional-content hidden links, duplicate action keys, primary action array/scalar rejection, direct primary action review, previous `/PA`, URI Base, IsMap, remote GoToR, name-tree Limits, object-stream action selection, Link QuadPoints, rotation/UserUnit geometry, widget field inheritance, freed action objects, action object stream selection, CropBox, Page/P generation, parent generation, or indirect numeric operands.

The bounded behavior is specifically metadata visibility for the action object's generation after the already exact indirect action resolution.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, generation-aware reference resolver, action dictionary reviewer, Link annotation span promotion, Markdown merge path, and WordPress smoke renderer. GPU/model execution, OCR, Surya/Texify/Torch, pdftext/PDFium live parity runs, action execution, JavaScript execution, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
