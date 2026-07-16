# markerPDF pdftext dictionary UTF-8 text boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T030245Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260606T030245Z`
Base accepted HEAD: `cca834562d07e08bf6acde5f6e2abd9f69ed0825`

## Source Truth

- Upstream markerPDF remains pinned in the lane manifest at `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)`, then converts each Python dictionary page into Marker page blocks.
- `pdftext.extraction.dictionary_output(...)` post-processes span text in Python Unicode strings before Marker conversion. Native supplied dictionaries therefore must reject malformed byte strings before WordPress rendering or kept-character review metadata.

Source links used:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Change

- `PdfTextDocumentExtractor` now rejects invalid UTF-8 in supplied pdftext span text before dictionary postprocessing.
- `PdfTextDocumentExtractor` now rejects invalid UTF-8 in kept `chars[*].char` values when `keepChars: true`.
- Valid UTF-8 text remains importable, including existing mojibake-repair cases where the bytes are valid UTF-8 but semantically mis-decoded.
- Added a WordPress smoke proving invalid span and kept-character text bytes fail closed while valid text renders as a Gutenberg paragraph without Python/pdftext/model execution.

## Red First

Before the source change, this probe accepted malformed span text bytes:

```text
php -r '... "Bad " . chr(0xC3) . chr(0x28) . " text\n" ...'
accepted
```

That meant native supplied dictionaries could carry byte strings that upstream Python `dictionary_output()` would not produce.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-utf8-text-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 210 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `3 test files, 544 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-utf8-text-boundary-currentbase.php
```

The smoke emitted `invalid_span_text_rejected=true`, `invalid_char_text_rejected=true`, `valid_utf8_text_imported=true`, `kept_char_utf8_preserved=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case and +4 focused assertions.

```text
git diff --check -- lanes/markerpdf
```

Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted pdftext dictionary link/ref preservation, disable-links behavior, character/font payload sanitation, character index validation, font flag integer validation, finite-number validation, source-dimension validation, superscript/subscript flags, normalized/rotated/off-page bbox scaling, mojibake repair, quote-loosebox option recording, sorting, blank-page preservation, layout/order artifact matching, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, runtime preflight, or supplied table/equation/OCR boundaries. The bounded behavior is only malformed UTF-8 text byte rejection at the supplied pdftext dictionary core boundary.

## Dependency Closure

No new support component is needed. This reuses native PHP supplied pdftext dictionary sanitation, kept-character sanitation, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium, Surya/Torch/OCR/layout/order/table models, Texify, tabled-pdf, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
