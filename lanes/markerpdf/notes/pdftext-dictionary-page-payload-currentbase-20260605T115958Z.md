# markerPDF pdftext dictionary page-payload core boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T115958Z`

Base accepted HEAD: `7e8909b138157ad54b929884430c08cbeb0317e2`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` for the selected page range and converts each returned page dictionary with `pdftext_format_to_blocks()`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `pdftext.extraction.dictionary_output(...)` sanitizes block, line, and span dictionaries before returning pages. This no-GPU PHP boundary now applies the same trust model to supplied page dictionaries before WordPress review metadata is formed: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/extraction.py

## Implemented Behavior

- `PdfTextDocumentExtractor` now rebuilds each supplied pdftext page dictionary from trusted core keys before conversion: `page`, `bbox`, `width`, `height`, `rotation`, `refs`, and sanitized `blocks`.
- Arbitrary page-level raw bytes, adapter metadata, rendered-image payloads, and nested private payloads are excluded before `PdfTextBlockConverter` stores `pdftext_source`, `char_blocks`, or visible WordPress text.
- Trusted page source geometry and safe pdftext refs remain preserved.
- Added a WordPress smoke that renders the Gutenberg paragraph while reporting `page_payload_excluded=true`, `page_source_preserved=true`, and no Python/model/external-tool execution.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextDocumentExtractor.php

php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-payload-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-payload-currentbase.php

php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR); echo "JSON OK\n";'
JSON OK
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS drops non core pdftext page payload keys before WordPress metadata
...
1 test files, 133 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
Focused test run: 3 selected test files (root lock skipped)
...
PASS drops non core pdftext page payload keys before WordPress metadata
...
3 test files, 462 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-payload-currentbase.php
exit 0; emits page_payload_excluded=true, page_source_preserved=true, page_ref_preserved=true, visible_wordpress_text="Page-level dictionary payload stays out of WordPress.", executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
git diff --check -- lanes/markerpdf
passed
```

Focused delta: +1 focused PASS case and +8 assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`; +1 WordPress smoke example.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP pdftext dictionary conversion, dictionary-output sanitation, safe page-source metadata handling, Markdown post-processing, and the WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell model execution, Texify, Streamlit/FastAPI workers, page-pixel visual recognition, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat page source geometry preservation, span/block/line payload sanitation, keep-chars sanitation, link/ref promotion, disabled-link handling, page-range slicing, blank-page handling, dictionary sorting, layout/order artifact payload sanitation, parser/xref repair, metadata extraction, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, or table/equation supplied-boundary work. The bounded behavior is only page-level pdftext dictionary payload exclusion before native WordPress metadata and paragraph rendering.
