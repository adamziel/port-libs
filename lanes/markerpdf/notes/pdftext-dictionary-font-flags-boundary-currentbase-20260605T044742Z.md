# markerPDF pdftext dictionary font flags boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T044742Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T044742Z`
Base accepted HEAD: `7ab42d625cf7e087d60c6d4170fd43b20e2c75a0`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::pdftext_format_to_blocks()` reads `s["font"]["flags"]` when converting pdftext span dictionaries into Marker spans: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext==0.3.18` `dictionary_output()` processes structured span dictionaries from pdftext inference and keeps font dictionaries supplied by pdftext: https://raw.githubusercontent.com/datalab-to/pdftext/v0.3.18/pdftext/extraction.py
- The current pdftext schema defines span and character font dictionaries with `name`, `flags`, `size`, and `weight`: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/schema.py and https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/pdf/chars.py

## Change

`PdfTextDocumentExtractor` and `PdfTextBlockConverter` now reject supplied pdftext font dictionaries that omit the upstream-required `flags` key:

- normal span fonts are checked before Marker page conversion;
- kept character fonts are checked at the `keepChars: true` sanitation boundary;
- explicit `flags => null` remains accepted for existing fixtures and nullable upstream/PDFium-style data;
- existing payload-key sanitation and visible WordPress paragraph rendering remain unchanged.

## Red First

Before the source change, the focused regression failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
FAIL rejects missing pdftext font flags at the dictionary core boundary
Expected exception InvalidArgumentException was not thrown
1 test files, 61 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/src/PdfTextBlockConverter.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 62 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
3 test files, 300 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

The WordPress smoke emitted `missing_span_font_flags_rejected=true`, `missing_char_font_flags_rejected=true`, `malformed_keep_chars_row_rejected=true`, `legacy_c_alias_excluded=true`, `font_payload_excluded=true`, `visible_wordpress_text=Character dictionary import`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
git diff --check -- lanes/markerpdf
```

No whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext page-range slicing, `keep_chars=false` raw character removal, optional `keepChars: true` retention, normalized bbox scaling, character/font payload-key allowlisting, malformed kept-character row type validation, link/ref sanitation, span script flags, block sorting, blank-page preservation, layout/order supplied boundaries, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, or runtime preflight. The bounded behavior is only fail-closed validation for the upstream-required `flags` key on supplied pdftext span and kept-character font dictionaries.

## Dependency Closure

No new support component is needed. This reuses native supplied pdftext dictionary conversion, metadata sanitation, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
