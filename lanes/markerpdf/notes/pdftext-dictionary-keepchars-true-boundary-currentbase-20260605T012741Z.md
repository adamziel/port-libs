# Pdftext Dictionary keep_chars=true Boundary

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T012741Z`

Base accepted HEAD: `72233410189f75bf7ebbabd39de1ea39ec033f70`

## Source Truth

- Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(..., keep_chars=False)` from `marker/pdf/extract_text.py::get_text_blocks`, then stores returned `page["blocks"]` as Marker `char_blocks`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked pdftext `0.3.18` `dictionary_output` exposes an optional `keep_chars` argument. Its `_process_span` removes `span["chars"]` when `keep_chars` is false; when true, it keeps character dictionaries and converts each `char["bbox"]` through `unnormalize_bbox(...)` before returning the page dictionaries: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py
- Character dictionaries produced by that pdftext path are bounded core records from pdftext inference; this PHP port retains only `char`, legacy local `c`, `bbox`, `rotation`, `font`, and `char_idx` before WordPress review metadata.

## Change

- `PdfTextDocumentExtractor::getTextBlocks()` now accepts `keepChars: true` while preserving the existing default `keep_chars=false` MarkerPDF path.
- `getOrderedTextBlocks()` forwards the same option for supplied dictionary plus layout-order handoffs.
- Retained character dictionaries are sanitized to upstream core character keys, normalized child bboxes are scaled to page coordinates, and arbitrary character/span payload fields are excluded before WordPress review metadata.
- The focused WordPress smoke demonstrates retained character review metadata while visible Gutenberg paragraph text remains `Keptfi chars` and raw payload fields stay absent.

## Non-Overlap

This does not repeat the accepted 2026-06-02 `keep_chars=false` sanitation slice or the accepted span-core allowlist, normalized bbox, link/ref, sort, layout-order, blank-page, OCR/table, parser/xref, font/CMap, image/filter, annotation/form/security, or runtime preflight slices. The bounded behavior here is the optional pdftext `keep_chars=true` side of the dictionary core boundary.

## Verification

Red-first:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result before source change: failed the new keepChars case with `Unknown named parameter $keepChars`; existing 2 cases passed.

After patch:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 27 assertions, 0 failures` (up from 15 assertions).

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `2 test files, 173 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-keep-chars-currentbase.php
```

Result: emitted `keep_chars=true`, `retained_char_count=2`, `first_char_bbox=[60,80,66,104]`, `raw_payload_excluded=true`, and `visible_wordpress_text=Keptfi chars`, with no Python/pdftext/model/external PDF tool execution.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses `PdfTextDocumentExtractor`, `PdfTextBlockConverter`, and `MarkdownPostProcessor`. Live pdftext/PDFium, pypdfium, Surya/Torch/OCR/layout/table models, Texify, Streamlit/FastAPI workers, benchmark tooling, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
