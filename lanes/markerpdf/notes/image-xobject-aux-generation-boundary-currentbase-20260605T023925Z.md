# markerPDF Image XObject Auxiliary Generation Boundary

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T023925Z`

Base accepted HEAD: `c875ac9271a83e1f32853d36d56f4807ebfe7a2e`

## Source Truth

Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from image rendering: `marker/pdf/extract_text.py` obtains page text through the pdftext/PDFium boundary, while `marker/pdf/images.py` renders image regions as raster media. Native PHP must therefore treat Image XObject payloads, masks, metadata streams, and alternate-image streams as review-only media metadata, not visible WordPress paragraph text.

The PDF object model makes indirect references generation-specific. If an image dictionary says `/Mask 6 0 R`, `/Metadata 9 0 R`, or `/Alternates [<< /Image 12 0 R >>]`, a same-number `6 1`, `9 1`, or `12 1` object referenced elsewhere in the graph must not replace the generation-zero image auxiliary stream.

## Implementation

`PdfTextExtractor` now resolves Image XObject auxiliary stream bodies by exact object number and generation before decoding review metadata:

- `/Mask` stream references use the exact referenced generation before building image-mask or explicit-mask review rows.
- `/Alternates` `/Image` references use exact object references before recording alternate image review rows.
- `/Metadata` stream references use exact object references before recording XML metadata stream review rows.

This keeps the existing review array shape stable while closing the same-number generation boundary.

## Evidence

Red-first after adding the focused case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 300 assertions, 1 failures
FAIL resolves image XObject auxiliary streams by exact object generation
Expected: 4
Actual: 9
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 327 assertions, 0 failures
```

The existing WordPress image XObject smoke now includes stale generation-one decoys for mask, metadata, and print alternate streams. It emits:

- `stale_mask_generation_rejected=true`
- `stale_metadata_generation_rejected=true`
- `stale_print_alternate_generation_rejected=true`
- `payload_in_visible_text=false`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Status Delta

Focused behavior tests: `1308 -> 1309` PASS cases for markerPDF.

Focused assertion count for `PdfImageXObjectBoundaryCurrentBaseTest.php`: `293 -> 327` assertions.

Mapped behavior: image XObject auxiliary stream references now match exact object-generation ownership for review-only WordPress media metadata.

## Non-Overlap

This does not repeat accepted Image XObject placement, optional-content visibility, SMask exact-generation, resource-reference exact-generation, image metadata field extraction, alternate-image review, ColorKey mask arrays, DCTDecode/CCITT/JPX/JBIG2 filter review, inline image boundaries, or live raster execution. The bounded behavior is specifically generation-exact auxiliary Image XObject references for `/Mask`, `/Metadata`, and `/Alternates /Image`.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, direct object generation map, stream dictionary/payload decoder, image XObject review builder, and WordPress smoke path. Full raster parity remains intentionally out of scope without pypdfium2/PIL/PDFium or future native raster backends, and no Python, models, OCR, or external PDF tools are executed.
