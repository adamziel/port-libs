# Image XObject Entry Wrapper Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260605T235452Z`

Accepted base: `2faaff9b4c7feb3668f3a00ab001ec20d779e5ce`

## Source Truth

Native no-GPU markerPDF scope maps searchable PDF text extraction separately from `marker.pdf.images.render_image` media handoff. Image and Form XObject resources can be reached through an indirect resource-entry wrapper object, and those wrapper references must resolve before image-boundary review. Raster payload bytes remain excluded from extracted WordPress text and review JSON.

## Behavior

- Added exact-generation-safe XObject entry wrapper resolution for `/Resources /XObject` dictionaries.
- Wrapper objects whose entire body is another indirect reference now resolve to the real Image/Form target before subtype classification, placement tracking, nested Form traversal, and decoded image hash review.
- Cyclic wrappers still fail closed with `body => null` and do not become image review rows.
- Direct XObject stream references continue to preserve accepted exact-generation behavior.

## Evidence

Red-first focused check before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectEntryWrapperBoundaryCurrentBaseTest.php`

Result: expected wrapped image count `2`, actual `0`; `1 test files, 11 assertions, 1 failures`.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectEntryWrapperBoundaryCurrentBaseTest.php`

Result: `1 test files, 28 assertions, 0 failures`.

Adjacent image boundary sweep:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(ImageXObject|PageResourceImageXObject).*Test\.php' | sort)`

Result: `5 test files, 1190 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-image-xobject-entry-wrapper-currentbase.php`

Result: emitted `markerpdf-image-xobject-entry-wrapper-currentbase: ok` with clean text and resolved wrapped image object hashes.

## Non-Overlap

This does not repeat accepted direct Image XObject placement, Form XObject matrices, optional content, exact generation auxiliary rows, Type3 CharProc image XObjects, resource category object boundaries, or malformed Do/cm operand handling. It only closes resource-entry wrapper indirection before the already accepted image-boundary review pipeline.

## Dependency Closure

No new support dependency is needed. The patch reuses native PHP PDF object parsing and bounded indirect-reference resolution; no Python, OCR, model, GPU, external PDF renderer, or live service is used.
