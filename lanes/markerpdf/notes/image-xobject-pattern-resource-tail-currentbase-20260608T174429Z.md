# markerPDF Image XObject Pattern Resource Tail Current Base

Session: `port-dev-markerpdf-image-xobject-20260608T174429Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T174429Z`
Base accepted HEAD: `00ea4d517c515ab21e88a62bfef7ac09185dceae`

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction separate from the image rendering handoff in `marker/pdf/images.py`. In the native no-GPU PHP scope, image XObject streams remain review-only media metadata and their raster payloads must not become WordPress paragraphs.

PDF resource dictionaries are name-to-object maps. A `/Pattern` resource entry whose indirect reference is followed by extra top-level operands before the next resource name is malformed. The native image review path already rejected the same boundary for `/XObject` entries; this slice applies that fail-closed boundary to `/Pattern` entries before traversing tiling-pattern streams and nested Image XObjects.

## Behavior

- `PdfTextExtractor::patternResourceReferences()` now rejects malformed Pattern resource values with non-name top-level tails.
- A valid sibling Pattern resource and a Pattern resource followed only by a PDF comment remain accepted.
- Tailed Pattern resources are not traversed into nested Image XObjects, so their decoded hashes and resource names stay out of review JSON.
- Visible WordPress text stays limited to surrounding searchable text; image payload bytes stay out of Gutenberg paragraphs.

## Red-First Evidence

Before the source change, the new focused test accepted the malformed Pattern resource and counted its nested image:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectPatternResourceEntryTailBoundaryCurrentBaseTest.php
FAIL rejects malformed tiling Pattern resource entry tails before image XObject traversal
Expected: 2
Actual: 3
1 test files, 4 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectPatternResourceEntryTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed tiling Pattern resource entry tails before image XObject traversal
1 test files, 43 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectPatternResourceEntryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectResourceEntryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1378 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-pattern-resource-tail-currentbase.php
```

The smoke exits 0 and emits `image_xobject_count=2`, `invoked_image_xobject_count=2`, `malformed_pattern_resource_entry_excluded=true`, `valid_pattern_image_painted=true`, `comment_tail_pattern_image_painted=true`, `bad_payload_hash_excluded_from_review=true`, `payload_in_visible_text=false`, `payload_in_review_json=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectPatternResourceEntryTailBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-pattern-resource-tail-currentbase.php
```

All reported no syntax errors.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, direct `/XObject` resource entry tails, Pattern wrapper resolution, tiling Pattern image traversal, Pattern/Form `/BBox` operand tails, q/Q current-path clipping, CTM placement, Form XObject Matrix/BBox behavior, optional content, masks/SMask, Decode, color-space metadata, filter metadata, Type3 CharProc image review, malformed `Do`, malformed `cm`, resource-generation selection, stream filters, xref repair, annotations, forms, metadata, security preflight, OCR/model work, or supplied table/equation handoffs. The bounded behavior is only fail-closed `/Pattern` resource entry tails before tiling-pattern Image XObject traversal.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level resource reference parser, pattern resource traversal, stream decoder, image XObject review rows, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium or PIL raster parity, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
