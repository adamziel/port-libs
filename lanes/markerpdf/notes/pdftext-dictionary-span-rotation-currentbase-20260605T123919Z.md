# markerPDF pdftext dictionary span rotation boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T123919Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T123919Z`
Base accepted HEAD: `b075679df11f2da22eb4cf1f317dbce011ea97e8`

## Source Truth

- Upstream markerPDF remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` and then converts each selected dictionary page through `pdftext_format_to_blocks()`.
- Locked pdftext `0.3.18` `pdftext.inference::update_span()` records the first character's `rotation` on each span, and `dictionary_output()` does not rewrite that span-level rotation before returning page dictionaries.

## Change

- `PdfTextBlockConverter` now preserves fractional pdftext span rotation metadata instead of truncating it through `(int)`.
- Integer-valued span rotations such as `270.0` still normalize to `270` for stable review metadata.
- Added a focused regression proving visible WordPress span metadata and stored `char_blocks` both retain the same fractional span rotation while safe span links continue to promote normally.
- Added a WordPress smoke for the supplied pdftext dictionary span-rotation boundary.

## Red First

Before the source change, this current-base probe showed rendered span metadata truncating the upstream-style rotation while `char_blocks` kept it:

```text
php -r '... PdfTextDocumentExtractor()->getTextBlocks([... rotation => 12.5 ...]) ...'
12
12.5
```

The new focused assertion expects both sides to preserve `12.5`.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextBlockConverter.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-span-rotation-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 141 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 470 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-span-rotation-currentbase.php
```

The smoke emitted `fractional_span_rotation_preserved=true`, `integral_float_span_rotation_normalized=true`, `safe_link_still_promoted=true`, `span_rotation_payload_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext page-range slicing, keep_chars false/true sanitation, character rotation preservation, page-rotation right-angle validation, page/ref integer validation, finite numeric validation, font flag validation, link/ref sanitation, disable_links behavior, normalized/off-page bbox scaling, script flags, page-source metadata, sorting, blank-page preservation, layout/order artifact alignment, parser/xref recovery, font/CMap/native PDF extraction, image/filter metadata, annotations/forms/security preflight, table/equation supplied boundaries, or runtime/model behavior. The bounded behavior is only fractional span-level `rotation` metadata at the supplied pdftext dictionary core boundary.

## Dependency Closure

No new support component is needed. This reuses native supplied pdftext dictionary conversion, Markdown/WordPress smoke rendering, focused PHP tests, and the existing `pdf-text-dictionary-core` support row. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, benchmark model downloads, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
