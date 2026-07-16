# markerPDF Image XObject OPI Generation Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260606T173400Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T173400Z`
Base accepted HEAD: `774ab44297f61bcddb7f6f77f43857ca6546cc90`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image extraction/rendering. The current upstream project documents extracted images as separate output alongside rendered text/metadata and exposes `--disable_image_extraction` as a distinct conversion option. In this native no-GPU lane, Image XObject payload bytes stay out of WordPress paragraph text while review metadata records the parser-side media handoff.

PDF indirect references include object number and generation. Image XObject auxiliary dictionaries such as `/OPI` proxy metadata must therefore resolve the exact `N G R` target before WordPress media review; stale same-object-number proxy dictionaries are not authoritative for the current image.

## Behavior

- `PdfTextExtractor::pdfDictionaryFromValue()` now resolves indirect dictionary values by exact object generation instead of object number alone.
- Image XObject OPI proxy review preserves current-generation high-resolution proxy metadata such as file specification, image type, dimensions, crop, position, resolution, and overprint state.
- Stale same-object-number OPI dictionaries and raster payload bytes remain excluded from visible WordPress text and serialized payload content.
- Soft-mask Image XObject review rows omit absent DCT stream-boundary diagnostics, restoring the current-base focused review contract while keeping real DCT boundary rows available when present.

## Red First

The current accepted base had a focused Image XObject boundary regression before this source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL records image XObject SMask stream metadata by exact generation without leaking alpha bytes
1 test files, 1244 assertions, 1 failures
```

The new OPI generation fixture is additive coverage for exact-generation dictionary metadata on the same image-review path.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1260 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 68 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-opi-generation-currentbase.php
```

The smoke exits 0 and emits `opi_version=2`, `opi_file_specification=current-highres-wordpress-hero.tif`, `stale_generation_excluded=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/diff checks run for this handoff:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-opi-generation-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR);'
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused TestRunner pass count: `2625 -> 2626`.
- WordPress smoke scenarios: `2223 -> 2224`.
- Focused Image XObject boundary current-base suite: `1244` assertions red on accepted base, then `1260` assertions green after the source change.
- Focused OPI proxy suite: `68` assertions green with one new behavior case.
- Manifest row added: `pdfImageXObjectOpiGenerationBoundaryCurrentBaseBehaviors = 1`, mapped count `1`.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, Form XObject inheritance, optional content, clipping, q/Q current-path handling, ExtGState transparency, masks/SMask image decoding, SMask Matte, Decode arrays, named ColorSpace, JPX `SMaskInData`, image masks, tiling/stroking patterns, Type3 CharProc image review, malformed `Do`, malformed `cm`, compatibility/text-object exclusion, duplicate resource names, pattern wrappers, resource entry tails, or encrypted fail-closed review.

The bounded behavior is only generation-exact indirect dictionary resolution for Image XObject OPI proxy review plus the current-base soft-mask review contract repair needed to keep the focused image boundary suite green.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-indexed direct-object inventory, dictionary parser, Image XObject review rows, stream decoders, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, model workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
