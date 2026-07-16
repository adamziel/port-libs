# Image XObject OPI Proxy Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260606T030948Z`

Accepted base: `55c848dd6906a4e66d6f73da6fcb868d78409b49`

## Source Truth

Native no-GPU markerPDF scope maps searchable PDF text extraction separately from the `marker.pdf.images.render_image` image handoff. Image XObject dictionaries may carry Open Prepress Interface (`/OPI`) proxy dictionaries that describe higher-resolution source imagery. Those dictionaries are media-review metadata and must not become extracted text, launch OCR/model code, or require external PDF tools.

## Behavior

- Added Image XObject `/OPI` proxy review metadata on boundary rows.
- Top-level version entries such as `/1.3` and `/2.0` resolve direct or indirect OPI dictionaries and expose bounded fields: type, OPI version, file specification, image type, dimensions, crop rectangle, position, resolution, and overprint.
- OPI review rows are marked `review_only` with `payload_in_visible_text=false`.
- Nested private OPI decoys are not scanned as version entries, and raster payload bytes remain excluded from visible WordPress text and review JSON.

## Evidence

Red-first focused check before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php`

Result: missing `opi_proxy_present` field after `14` assertions; `1 test files, 14 assertions, 1 failures`.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php`

Result: `1 test files, 35 assertions, 0 failures`.

Adjacent image boundary sweep:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(ImageXObject|PageResourceImageXObject).*CurrentBaseTest\.php' | sort)`

Result: `8 test files, 1325 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-image-xobject-opi-proxy-currentbase.php`

Result: emitted `markerpdf-image-xobject-opi-proxy-currentbase: ok` with clean `Attachment intro/outro` text, `opi_proxy_present=true`, `opi_proxy_review_only=true`, `opi_proxy_payload_in_visible_text=false`, and OPI version `1.3`.

Additional checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-opi-proxy-currentbase.php` => no syntax errors.
- `git diff --check -- lanes/markerpdf` => no whitespace errors.

## Non-Overlap

This does not repeat accepted Image XObject placement, clipping, soft masks, alternates, metadata streams, exact-generation resource references, resource-entry wrappers, Type3 CharProc images, optional-content handling, JSON table/source behavior, or attachment `/AF` boundaries. It only adds OPI proxy dictionary metadata to the existing image boundary review rows.

## Dependency Closure

No new support dependency is needed. The patch reuses native PHP PDF dictionary parsing, exact indirect-object resolution, numeric array parsing, and image boundary review plumbing; no Python, OCR, Surya/Texify/Torch, external PDF renderer, GPU, or live service is used.
