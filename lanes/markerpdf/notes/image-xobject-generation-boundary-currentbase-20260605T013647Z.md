# markerPDF Image XObject Generation Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260605T013647Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T013647Z`
Base accepted HEAD: `5c1e831a4cd16b50e19b19a5942fd02353b5a990`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from page/image rendering:

- `marker/pdf/extract_text.py::get_text_blocks()` reads text pages through `pdftext.extraction.dictionary_output`.
- `marker/pdf/images.py::render_image()` renders PDF pages with annotations disabled and converts the bitmap to RGB for image handoff.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

The native PHP port does not execute PDFium, PIL, pdftext, Python models, OCR, Poppler, Ghostscript, or external PDF tools. This slice maps the same boundary by keeping Image XObject streams as review-only metadata and resolving `/Resources /XObject` indirect references by exact object generation before any media review hash/dimension fields are reported.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now preserves the generation from each `/Resources /XObject` reference and reads the exact referenced object body for image and nested Form XObject review. A resource entry such as `/Exact#20Image 5 0 R` no longer uses a stale/newer `5 1 obj` stream just because the object number is the same. Review rows now include `object_generation`, and nested Form XObject cycle suppression keys object number plus generation.

The WordPress smoke models an import where object `5 0` is the referenced DeviceRGB image while object `5 1` contains a stale DeviceGray image payload. The visible paragraphs keep only page text, the image review hash/dimensions come from generation `0`, and the stale generation payload is rejected.

## Red-First Evidence

Before the source change, the added focused case failed because image XObject review selected by object number only and had no generation metadata:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL resolves image XObject resource references by exact object generation
Expected: 0
Actual: NULL
1 test files, 257 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 274 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-generation-boundary-currentbase.php
```

The smoke emitted `first_object_number=5`, `first_object_generation=0`, `first_dimensions=[3,1]`, `first_color_space=DeviceRGB`, `stale_generation_rejected=true`, `payload_in_visible_text=false`, and execution flags for Python/models/external PDF tools as false.

## Status Delta

- Behavior tests: `1250 -> 1251` pass / `0` fail.
- Focused assertion growth: existing direct current-base image test file grows from `251` to `274` assertions.
- WordPress scenarios: `1218 -> 1219`.
- Mapped semantics: unchanged; this refines the existing Image XObject boundary row with exact-generation resource-reference behavior.

## Non-Overlap

This does not repeat accepted Image XObject CTM placement, optional-content-hidden image metadata, nested Form XObject image review, page Contents array graphics-state preservation, clipping-path bboxes, image mask/SMask metadata, alternate-image review, inline image boundaries, JPX/JBIG2/DCT/CCITT filter exclusion, color-space/Decode preview rows, object-stream owner repair, xref repair, or PageLabels exact-generation work. The new boundary is only `/Resources /XObject` indirect reference generation selection for image/form review rows.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, direct object body generation cache, page resource dictionary resolver, stream filter decoder, Image XObject review path, Form XObject recursion, and WordPress smoke path. Full live raster parity remains gated on PDFium/PIL or a future native raster backend and remains out of scope under the no-GPU/no-model markerPDF lane direction.
