# markerPDF pdftext dictionary character codepoint current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T144926Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260606T144926Z`
Base accepted HEAD: `329b34568a5e9ea6b4a71ed3f0baabdca2830c90`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` and converts returned page dictionaries into Marker `Page`, `Block`, `Line`, and `Span` objects without OCR/model execution: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Current `pdftext.pdf.chars.get_chars()` emits each kept character row from `chr(FPDFText_GetUnicode(...))`, so optional `keep_chars` metadata is one Unicode character per `char` row before `dictionary_output()` stores it: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/pdf/chars.py
- `pdftext.schema.Char` defines kept character dictionaries around one `char`, bbox, rotation, font, and `char_idx` row: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/schema.py

## Change

`PdfTextDocumentExtractor` now requires each supplied `keepChars: true` character row to contain exactly one valid UTF-8 codepoint:

- astral-plane characters such as emoji remain valid single character rows;
- empty strings, multi-character strings, and combining sequences are rejected before WordPress review metadata;
- existing bbox, rotation, font, `char_idx`, and payload-key sanitation remains unchanged.

## Red First

After adding the focused regression and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
FAIL accepts single-codepoint pdftext characters and rejects multi-codepoint rows before WordPress rendering
Expected exception InvalidArgumentException was not thrown
1 test files, 274 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-codepoint-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 276 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
3 test files, 621 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-codepoint-currentbase.php
```

The WordPress smoke emitted `single_codepoint_character_rows_accepted=true`, `empty_character_row_rejected=true`, `two_character_row_rejected=true`, `combining_sequence_row_rejected=true`, `visible_wordpress_text="Emoji character rows"`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext page-range slicing, dictionary-output envelope unwrapping, JSON object normalization, link/ref preservation, disable-links behavior, empty-span dropping, `keep_chars` array presence, malformed character field type validation, character index validation, font flag validation, payload-key sanitation, Unicode span repair, normalized/off-page bbox scaling, quote-loosebox option recording, sorting, blank-page preservation, layout-order artifact matching, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, runtime preflight, or table/equation/OCR supplied boundaries. The bounded behavior is only the one-codepoint `char` invariant for optional pdftext kept-character metadata.

## Dependency Closure

No new support component is needed. This reuses native supplied pdftext dictionary conversion, keep-chars validation, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
