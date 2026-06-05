# markerPDF Image XObject Contents-array graphics state

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T002907Z`

Base accepted HEAD: `810d0706bf9e20b666c6562cd776779e2c68b0d5`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable-PDF text extraction through `marker/pdf/extract_text.py` from image rendering through `marker/pdf/images.py`. Under the current no-GPU markerPDF scope, this PHP lane owns the native PDF parser boundary that reports Image XObject review metadata while keeping raster payload bytes out of WordPress paragraphs.

PDF page `/Contents` arrays are equivalent to one concatenated content stream. Graphics-state operators such as `q`, `cm`, clipping paths, optional-content marked-content wrappers, `Do`, and `Q` can legally span adjacent stream objects. Image XObject placement review must therefore scan the decoded page contents as one logical stream before deciding whether an image was painted and which CTM/bbox applies.

## Implementation

`PdfTextExtractor::imageXObjectBoundaryEntriesForResourceOwner()` now joins decoded content stream arrays with an explicit newline before optional-content filtering and XObject invocation scanning. This keeps existing single-stream behavior unchanged while preserving graphics state across page content arrays for image review rows and nested Form XObject image traversal.

The focused fixture splits `q 10 0 0 5 100 200 cm` into the first page content stream and `/Split#20Image Do Q` into the second. Before the fix, the image review treated the invocation as identity-space `[0, 0, 1, 1]`; after the fix it reports the expected matrix `[10, 0, 0, 5, 100, 200]` and bbox `[100, 200, 110, 205]`.

The WordPress smoke `wordpress-pdf-image-xobject-boundary-currentbase.php` now also splits the Form XObject invocation across two `/Contents` stream objects and emits `page_contents_array_graphics_state_preserved=true` while still excluding image, metadata, alternate, hidden marked-content, and hidden object payload bytes from paragraphs.

## Red-first evidence

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`

Failed before implementation at `preserves graphics state across page Contents arrays for image XObject placement review`:

```text
Expected: [[10.0, 0.0, 0.0, 5.0, 100.0, 200.0]]
Actual:   [[1.0, 0.0, 0.0, 1.0, 0.0, 0.0]]
1 test files, 222 assertions, 1 failures
```

## Verification

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`

```text
1 test files, 232 assertions, 0 failures
```

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`

```text
7 test files, 1567 assertions, 0 failures
```

`php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php`

Emits `page_contents_array_graphics_state_preserved=true`, `image_xobject_count=3`, `invoked_image_xobject_count=1`, `uninvoked_image_xobject_count=2`, `payload_in_visible_text=false`, and the visible WordPress paragraphs `Current Image Boundary Intro` / `Current Image Boundary Outro`.

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

```text
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
```

`php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`

```text
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
```

`php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php`

```text
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
```

`git diff --check -- lanes/markerpdf`

```text
no output; exit 0
```

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, stream filter decoder, content token scanner, optional-content filter, graphics-state matrix math, Form XObject traversal, and WordPress smoke output. Python, PDFium/pypdfium, PIL, OCR/model execution, Surya/Texify/Torch, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-overlap

This does not repeat accepted Image XObject fallback exclusion, optional-content visibility for same-stream image invocations, DCT/CCITT/JPX/JBIG2 preview-only image filters, inline image boundaries, soft-mask/color-space/decode metadata, Form XObject resource inheritance, basic CTM placement, clipping, alternates, metadata streams, encrypted fail-closed review, xref repair, or the prior DCTDecode Flate-prefix stream-boundary patch. The new boundary is specifically Image XObject invocation graphics state across page `/Contents` arrays before WordPress import.
