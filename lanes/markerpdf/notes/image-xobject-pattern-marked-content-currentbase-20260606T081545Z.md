# Image XObject Pattern Marked-Content Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260606T081545Z`
Base: `ebe3852fd7a4b86c1c6805bcbe033ba165d43ceb`

## Source Truth

- Upstream markerPDF hands image extraction through image metadata/render boundaries, but this no-GPU lane cannot run `pypdfium2`, PIL, Surya, Texify, Torch, or live OCR/model workers.
- The native parser boundary for this slice is PDF PatternType 1 painting: a page content stream can select a tiling pattern and paint a path; image XObjects invoked from the pattern stream are still painted under the page paint operation's marked-content context.

## Behavior Added

- `PdfTextExtractor::contentPatternPaintInvocationDetails()` now tracks BMC/BDC/EMC marked-content stacks the same way direct image `/XObject Do` invocation scanning does.
- Page `/Resources /Properties` entries are passed into pattern paint scanning, so property-resource BDC operands survive into recursive pattern image review.
- Image XObjects painted from tiling pattern streams now expose `invocation_marked_content`, `marked_content_review_only`, MCIDs, tag stacks, and resource-property provenance without promoting raster payload bytes into visible WordPress text.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL propagates marked-content metadata from pattern paints to image XObject review entries
1 test files, 1137 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS propagates marked-content metadata from pattern paints to image XObject review entries
1 test files, 1156 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-pattern-marked-content-currentbase.php
```

The smoke emitted `image_xobject_count=2`, `invoked_image_xobject_count=2`, `figure_pattern_mcid=17`, `property_pattern_mcid=18`, `property_source=Resources.Properties`, `payload_in_visible_text=false`, `marked_content_review_only=true`, and no Python/model/external-tool execution flags.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-pattern-marked-content-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-pattern-marked-content-currentbase.php

php -r '...json_decode(file_get_contents("lanes/markerpdf/lane-status.json"))...'
lane-status.json valid

git diff --check -- lanes/markerpdf
0 whitespace errors
```

## Non-Overlap

This does not repeat direct image marked-content invocation metadata, artifact-marked image filtering, tiling/stroking pattern image extraction, q/Q marked-content restoration, image-mask pattern color, CTM, Decode, soft-mask, optional-content, or page-boundary image slices. It only bridges page-level marked-content context into pattern paint details before recursive image XObject review.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP tokenizer, resource dictionary parser, pattern resource traversal, marked-content property parser, and image XObject boundary review. GPU/model/OCR/raster execution remains intentionally out of scope.
