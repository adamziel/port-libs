# markerPDF image XObject indirect ColorKey mask boundary

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T121003Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable PDF text extraction from image rendering: text comes through the PDF text/parser path, while Image XObjects are rendered through the image handoff before Markdown image insertion. Under the current no-GPU scope, native PHP exposes image parser review metadata and keeps raster payload bytes out of WordPress paragraphs.

PDF array operands can be indirect objects. The existing `/Decode` image review path already resolves indirect numeric operands; this slice applies the same boundary to ColorKey `/Mask` arrays so indirect scalar objects define raw component ranges instead of being mistaken for literal object/generation numbers.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now resolves numeric operands inside image XObject ColorKey `/Mask` arrays through the native object table before building `mask_review`.

The focused fixture uses `/Mask [20 0 R 21 0 R 22 0 R 23 0 R 24 0 R 25 0 R]`, where objects 20-25 resolve to `0 0 120 140 200 255`. Before the patch, the review treated object and generation tokens as raw mask values and emitted six invalid component ranges. After the patch, it emits the three RGB raw-sample ranges and keeps the image payload out of visible WordPress text.

## Evidence

Red-first probe on accepted base:

```text
mask_review.ranges=[20..0, 21..0, 22..0, 23..0, 24..0, 25..0]
mask_review.component_count=6
mask_review.valid_for_components=false
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect numeric operands in image XObject ColorKey Mask arrays

1 test files, 732 assertions, 0 failures
```

Image family gate:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -name 'PdfImage*Test.php' | sort)
Focused test run: 20 selected test files (root lock skipped)
20 test files, 2133 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-colorkey-indirect-mask-currentbase.php
```

The smoke exits 0 and emits `indirect_mask_operands_resolved=true`, `image_decode_applied_before_rgb=true`, `image_payload_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

`git diff --check -- lanes/markerpdf` passed with no output.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, Form XObject image-resource inheritance, optional-content visibility, direct ColorKey arrays, mask streams, soft masks, `/Decode` array review, ICC/Indexed/DeviceN color-space preview metadata, DCT/CCITT/JPX/JBIG2 preview-only filter boundaries, inline image payload tokenization, or raster RGB preview parity.

The bounded behavior is specifically indirect scalar operand resolution inside Image XObject ColorKey `/Mask` arrays before WordPress media review.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP object parser, exact-generation object resolver, image XObject review path, stream decoder, ColorKey mask metadata builder, and WordPress smoke renderer. Full raster parity remains dependency-gated by PDFium/pypdfium, Pillow image conversion, OCR/layout models, and model-server paths; none were executed.
