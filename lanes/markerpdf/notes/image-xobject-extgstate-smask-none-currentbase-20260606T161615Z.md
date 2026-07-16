# markerPDF Image XObject ExtGState SMask None Boundary Current Base

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T161615Z`
Base accepted HEAD: `75fa47f3fd4a092265a672a9ef4ebfe9b906474c`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from page/image rendering: text comes through pdftext/PDFium text extraction, while image data is rendered through `marker.pdf.images.render_image`.

This no-GPU PHP slice maps the parser-side graphics-state boundary for Image XObjects. A PDF ExtGState `/SMask /None` disables the current soft mask before a subsequent `Do` image paint. WordPress import should keep the image invocation and applied ExtGState resources for review, but it should not report `/None` as a live soft mask or leak raster payload bytes into paragraphs.

## Behavior

`PdfTextExtractor::applyExtGStateReviewToInvocationState()` now treats a resolved `graphics_state_soft_mask_none` review row as a state clear:

- an earlier `/SMask 22 0 R` soft mask remains visible on images painted before `/SMask /None`;
- applying `/SMask /None` clears `invocation_graphics_states[*].soft_mask` before the next Image XObject;
- applied ExtGState resource names, alpha, and blend mode metadata remain review-only and auditable;
- decoded image payload hashes remain available, but payload bytes stay out of visible WordPress text and review JSON.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL clears ExtGState soft mask with SMask None before Image XObject review
Values are not identical
Expected: NULL
Actual: array (
  'type' => 'graphics_state_soft_mask_none',
  'present' => false,
  'payload_in_visible_text' => false,
  'review_only' => true,
)
1 test files, 13 assertions, 1 failures
```

## Verification

Focused after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS clears ExtGState soft mask with SMask None before Image XObject review
1 test files, 26 assertions, 0 failures
```

The WordPress smoke is:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-extgstate-smask-none-currentbase.php
```

It emits a metadata comment with `cleared_soft_mask_is_null=true`, `cleared_extgstate_resources=["Soft Mask State","No Soft Mask State"]`, `masked_soft_mask_type="graphics_state_soft_mask"`, both execution flags false, and visible Gutenberg paragraphs containing only the searchable page text.

Additional focused verification:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-extgstate-smask-none-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-extgstate-smask-none-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 1286 assertions, 0 failures

php -r '$json=file_get_contents("lanes/markerpdf/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
passed with no output
```

Root harness status: not run - isolated micro-slice.

## Status Delta

- Adds 1 focused PHP PASS case and 26 focused assertions for the ExtGState `/SMask /None` Image XObject boundary.
- Adds 1 WordPress smoke/example for import review metadata.
- `phpPass` expected to move `2604 -> 2605`; `wordpressScenarios` expected to move `2206 -> 2207`.
- Mapped upstream denominator unchanged; this refines the already mapped Image XObject render/text boundary.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, malformed `cm`/`Do`, text-object or compatibility-section gates, optional content, artifact suppression, clipping/page geometry, generic ExtGState metadata capture, transparent alpha suppression, image dictionary `/SMask /None`, SMask stream metadata, masks, Decode arrays, JPX `SMaskInData`, alternate images, OPI metadata, color spaces, Type3 CharProc image review, pattern image paints, encrypted fail-closed review, OCR, model execution, or raster rendering.

The bounded behavior is only ExtGState `/SMask /None` clearing a previously active graphics-state soft mask before Image XObject invocation review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, resource dictionary parser, ExtGState review path, content tokenizer, graphics-state q/Q handling, Image XObject review rows, stream decoder, and WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
