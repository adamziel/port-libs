# markerPDF Image XObject Auxiliary Generation Review Boundary

Session: `port-dev-markerpdf-image-xobject-20260605T052905Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T052905Z`
Base accepted HEAD: `4730cc6d01f7dd13815bc0b8f8150bc3c9a09645`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable page text extraction separate from image rendering: `marker/pdf/extract_text.py` produces text pages, while `marker/pdf/images.py` renders page/crop images through PDFium/PIL and converts them to RGB. Under the current no-GPU scope, the native PHP lane records parser-side Image XObject review metadata without rasterizing images or promoting stream bytes into WordPress paragraphs.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` already resolved `/Mask`, `/Metadata`, and `/Alternates` image references by exact object generation. This slice now exposes that selected generation in review metadata:

- top-level image rows include `mask_generation`;
- `/Mask` stream reviews include `object_generation`;
- image metadata stream reviews include `object_generation`;
- alternate image reviews include `object_generation`.

The focused fixture uses current generation-one mask, metadata, and print-alternate streams with stale generation-zero decoys. The review now reports the generation-one selections while keeping all current and stale auxiliary stream payload bytes out of visible WordPress text.

## Verification

Focused red-first boundary before the source change failed on the new auxiliary-generation review assertions because the selected stream generations were not exposed:

```text
FAIL exposes exact object generations for image XObject auxiliary review rows
```

After the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 459 assertions, 0 failures
```

Adjacent image/parser family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
5 test files, 1971 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
```

The smoke emits `first_mask_generation=0`, `first_mask_review_generation=0`, `first_metadata_generation=0`, `first_print_alternate_generation=0`, `first_screen_alternate_generation=0`, stale auxiliary generation rejection flags, clean paragraph text, and Python/model/external-tool execution flags false.

## Status Delta

- Behavior tests: `1469 -> 1470` pass / `0` fail.
- Focused assertion growth: `PdfImageXObjectBoundaryCurrentBaseTest.php` grew from `430` to `459` assertions.
- WordPress scenario: existing image XObject boundary smoke now audits exact-generation mask, metadata, and alternate image review fields.
- Mapped denominator: unchanged; this refines the already mapped `pdfImageXObjectBoundaryBehaviors` row.

## Non-overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, Form XObject traversal, page `/Contents` array graphics-state preservation, rectangular clipping, optional-content visibility, resource-reference generation selection, SMask generation review, ColorKey mask arrays, named ColorSpace resources, top-level XObject dictionary parsing, DCT/CCITT/JPX/JBIG2 preview-only filters, inline image boundaries, or PDF text extraction. The bounded behavior is specifically exposing selected object generations for auxiliary Image XObject mask, metadata, and alternate-image review rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, exact-generation object lookup, stream dictionary decoder, image XObject review builder, and WordPress smoke renderer. Full raster parity remains dependency-gated on pypdfium2/PDFium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, Poppler, Ghostscript, and external PDF tools were not run.
