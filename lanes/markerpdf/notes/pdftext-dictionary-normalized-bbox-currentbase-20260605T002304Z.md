# markerPDF pdftext dictionary normalized bbox current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T002304Z`

## Source Truth

- Upstream `sddai/markerPDF` is pinned at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., keep_chars=False)` and then converts each selected dictionary page with `pdftext_format_to_blocks()`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext==0.3.18` `dictionary_output()` scales block, line, and span bboxes with `unnormalize_bbox(span["bbox"], page_width, page_height)` before markerPDF consumes the dictionaries: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py and https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/pdf/utils.py

## Change

`PdfTextDocumentExtractor` now mirrors the pdftext child-bbox scaling boundary for supplied pages that still carry raw normalized PDFium child geometry:

- page bboxes remain the existing absolute page-source metadata;
- block, line, and span bboxes that look normalized are scaled by supplied page `width` and `height`;
- scaled bboxes use pdftext's one-decimal rounding;
- already-absolute bboxes stay unchanged;
- `keep_chars=false` sanitation still removes raw character payloads and non-core block/line/span keys before WordPress rendering.

The WordPress smoke records scaled block/line/span metadata, visible paragraph text, and no Python/pdftext/PDFium/model/external-tool execution.

## Red-First Evidence

Before the source change:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Failed at `unnormalizes pdftext dictionary child bboxes before WordPress import`: expected `[61.2,158.4,428.4,190.1]`, actual `[0.1,0.2,0.7,0.24]`.

## Verification

```sh
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-normalized-bbox-currentbase.php
```

All syntax checks passed.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `1 test files, 122 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `2 test files, 159 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-normalized-bbox-currentbase.php
```

Result: emitted `normalized_block_bbox_scaled=true`, `normalized_span_bbox_scaled=true`, `raw_chars_excluded=true`, `non_core_line_payload_excluded=true`, `visible_wordpress_text="Normalized bbox dictionary import"`, and no Python/model/external PDF tool execution.

```sh
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

Both checks passed.

## Non-Overlap

This does not repeat pdftext page-range slicing, dictionary options metadata, source page dimensions, keep-chars sanitation, non-core span payload stripping, pdftext text post-processing, dictionary sorting, selected blank-page handling, layout/order artifact range trimming, keyed layout/order collision handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically pdftext normalized child-bbox scaling before Marker page conversion.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary extractor, block converter, Markdown post-processor, and WordPress smoke path. Live `pdftext`, `pypdfium2`/PDFium, Surya/Torch/OCR/layout/order models, tabled-pdf, Texify, Streamlit/FastAPI runtimes, benchmark tooling, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
