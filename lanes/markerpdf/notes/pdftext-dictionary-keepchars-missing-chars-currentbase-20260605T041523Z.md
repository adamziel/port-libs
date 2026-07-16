# markerPDF pdftext dictionary keep_chars missing chars boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T041523Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T041523Z`
Base accepted HEAD: `885f79b544126701ac9263486315593117b46de0`

## Source Truth

- Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py::get_text_blocks()` and converts the returned page dictionaries without OCR/model execution.
- Locked `pdftext==0.3.18` `dictionary_output()` removes `span["chars"]` when `keep_chars=False`; when `keep_chars=True`, it iterates `span["chars"]` and scales each kept character bbox before returning the dictionary page.

## Change

`PdfTextDocumentExtractor` now requires every supplied pdftext span to contain a `chars` list when `keepChars: true`.

This preserves the native PHP dictionary-output boundary for WordPress review metadata:

- valid kept-character dictionaries still pass through the existing `char`, `bbox`, `rotation`, `font`, and `char_idx` sanitation;
- malformed spans with no `chars` array fail closed before `PdfTextBlockConverter` or Gutenberg paragraph rendering;
- `keepChars: false` remains unchanged and continues to drop raw character payloads.

The existing WordPress char-core smoke now covers both malformed kept-character rows and the missing `chars` array boundary.

## Red First

After adding the focused regression and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 55 assertions, 1 failures
FAIL rejects malformed pdftext keep chars rows before WordPress rendering
Expected exception InvalidArgumentException was not thrown
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextDocumentExtractor.php

php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 60 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
3 test files, 287 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

The smoke emitted `missing_keep_chars_array_rejected=true`, `malformed_keep_chars_row_rejected=true`, `legacy_c_alias_excluded=true`, `font_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case and +1 focused assertion in the pdftext dictionary core boundary test.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext page-range slicing, `keep_chars=false` raw character removal, optional `keepChars: true` retention for valid rows, character/font payload allowlisting, malformed character-field validation, normalized bbox scaling, link/ref sanitation, span script flags, block sorting, blank-page preservation, layout/order artifact alignment, OCR/table supplied boundaries, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, or runtime preflight.

The bounded behavior is only the fail-closed missing-`chars` span boundary when callers explicitly request kept pdftext character dictionaries.

## Dependency Closure

No new support component is needed. This reuses native supplied pdftext dictionary conversion, character metadata sanitation, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
