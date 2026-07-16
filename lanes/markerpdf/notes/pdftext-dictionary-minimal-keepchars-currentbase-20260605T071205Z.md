# markerPDF pdftext dictionary minimal keep-chars current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T071205Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T071205Z`
Base accepted HEAD: `7015b6c3156d56de0f0eae60550c6756f26d7797`

## Source Truth

- Upstream markerPDF is pinned in the lane manifest to `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., keep_chars=False)` before converting page dictionaries into Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- markerPDF depends on `pdftext = "^0.3.18"`. In `pdftext` 0.3.18, inference reduces kept character dictionaries to `char` and `bbox`, while the parent span retains `font`, `rotation`, `char_start_idx`, and `char_end_idx`; `dictionary_output(..., keep_chars=True)` then only scales kept char bboxes: https://raw.githubusercontent.com/datalab-to/pdftext/v0.3.18/pdftext/inference.py and https://raw.githubusercontent.com/datalab-to/pdftext/v0.3.18/pdftext/extraction.py

## Change

`PdfTextDocumentExtractor` now accepts locked-pdftext minimal kept character dictionaries at the supplied dictionary core boundary:

- kept chars still require `char` and `bbox`;
- missing char `rotation` is inferred from the parent span rotation, defaulting to `0` when the span omits it;
- missing char `font` is inferred from the sanitized parent span font;
- missing char `char_idx` is inferred from `span.char_start_idx + character_index`;
- explicit malformed char rotation, font, or char_idx values still fail closed;
- private/debug payload keys on spans, fonts, and chars remain excluded before WordPress paragraphs or review metadata.

## Red First

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Failed at `accepts locked pdftext minimal keep chars dictionaries at the core boundary` with `pdftext char 0.rotation is required when keep_chars=true.`

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 94 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `3 test files, 379 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-minimal-keep-chars-currentbase.php
```

Emitted `minimal_char_dictionary_accepted=true`, `char_rotation_inferred_from_span=true`, `char_font_inferred_from_span=true`, `char_index_inferred_from_span_start=true`, `normalized_char_bbox_scaled=true`, `private_payload_excluded=true`, `visible_wordpress_text="Minimal chars"`, and no Python/pdftext/model/external PDF tool execution.

Focused delta: +1 focused PASS case and +13 assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` (`81 -> 94` assertions). Adjacent pdftext family moved from `366 -> 379` assertions.

## Non-overlap

This does not repeat accepted pdftext link/ref preservation, disable_links handling, keep_chars full-character retention, character/font payload sanitation, normalized/off-page bbox scaling, span script flags, page-source metadata, sorting, blank-page preservation, layout/order artifact alignment, parser/xref repair, font/CMap/native PDF extraction, image/filter review, annotations/forms/security preflight, table/equation supplied boundaries, or runtime/model behavior.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied pdftext dictionary sanitizer, block converter, Markdown post-processor, and WordPress smoke path. Live pdftext/PDFium execution, Surya/Torch/OCR/layout/order/table-cell models, Texify, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
