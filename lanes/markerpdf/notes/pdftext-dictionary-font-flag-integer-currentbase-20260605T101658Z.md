# markerPDF pdftext dictionary font flag integer boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T101658Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T101658Z`
Base accepted HEAD: `c34bd22c970934de29fc9d7c3cbf7a358b8b07cc`

## Source Truth

- Upstream markerPDF remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)` before Marker page conversion.
- markerPDF/pdftext font dictionaries carry `name`, `flags`, `weight`, and `size`; `flags` is a PDF font descriptor bitfield, so non-null values must be integer-valued before native WordPress style metadata is emitted.

## Change

- `PdfTextDocumentExtractor` now normalizes non-null supplied pdftext font `flags` through integer metadata before storing sanitized span and kept-character font dictionaries.
- `PdfTextBlockConverter` now rejects fractional span font flags at the direct converter boundary instead of truncating them with `(int)`.
- Nullable `flags` remain accepted for existing PDFium/pdftext-style data, and integer-valued floats such as `33.0` are accepted and normalized to `33`.
- The existing WordPress pdftext character-core smoke now records fractional span/character flag rejection and integral-float acceptance.

## Red First

Before the source change, a probe using `flags => 1.5` reached WordPress style metadata as:

```text
'Helvetica_fixed_pitch'
```

That showed the previous converter truncated a malformed fractional bitfield instead of failing closed.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/src/PdfTextBlockConverter.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/tests/PdfTextBlockConverterTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 112 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `2 test files, 154 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `3 test files, 441 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

The smoke emitted `fractional_span_font_flags_rejected=true`, `fractional_char_font_flags_rejected=true`, `integral_float_font_flags_accepted=true`, `visible_wordpress_text="Character dictionary import"`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases and +7 focused assertions.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted missing font flag validation, font payload key sanitation, keep_chars minimal dictionaries, character index validation, script flags, link/ref sanitation, normalized/off-page bbox scaling, selected blank-page handling, sorting, layout/order artifact alignment, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table/equation supplied boundaries, runtime model paths, or XMP metadata packet work. The bounded behavior is specifically non-null pdftext font flags as integer bitfields at the supplied dictionary core and direct converter boundaries.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, kept-character sanitation, direct Marker page conversion, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium, Surya/Torch/OCR/layout/order models, Texify, tabled-pdf, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
