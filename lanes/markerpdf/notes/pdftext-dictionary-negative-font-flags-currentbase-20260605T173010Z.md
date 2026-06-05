# markerPDF pdftext dictionary negative font flags current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T173010Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T173010Z`
Base accepted HEAD: `3def3c127d89fb2d9ff534915066695347ee7763`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF extraction to `pdftext.extraction.dictionary_output(...)` and converts returned span font dictionaries into Marker spans with `font_flags_decomposer`.
- `pdftext.extraction.dictionary_output(...)` returns PDF font descriptor `flags` as font bitfield metadata. Native supplied dictionaries should therefore accept `null` or non-negative integer-valued bitfields, while rejecting negative values that PHP bit operations would otherwise decompose into misleading style flags.

## Change

- `PdfTextDocumentExtractor` now validates supplied span and kept-character `font.flags` as non-negative integer-valued metadata before storing sanitized `char_blocks` or WordPress-visible span style metadata.
- `PdfTextBlockConverter` applies the same non-negative check at the direct converter boundary.
- The existing WordPress pdftext character-core smoke now reports negative span and character flag rejection without running Python, pdftext, models, or external PDF tools.

## Red First

Before the source change, the focused regression failed because `flags => -1` and `flags => -33` reached the converter:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
FAIL rejects negative pdftext font flags at the converter boundary
Expected exception InvalidArgumentException was not thrown
FAIL rejects negative pdftext font flags before WordPress style metadata
Expected exception InvalidArgumentException was not thrown
2 test files, 219 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextDocumentExtractor.php

php -l lanes/markerpdf/src/PdfTextBlockConverter.php
No syntax errors detected in lanes/markerpdf/src/PdfTextBlockConverter.php

php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/tests/PdfTextBlockConverterTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextBlockConverterTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
2 test files, 224 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
3 test files, 511 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

The smoke emitted `negative_span_font_flags_rejected=true`, `negative_char_font_flags_rejected=true`, `integral_float_font_flags_accepted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR); echo $path . " JSON OK\n"; }'
lanes/markerpdf/lane-status.json JSON OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json JSON OK

git diff --check -- lanes/markerpdf
passed
```

Focused delta: +2 focused PASS cases and +5 assertions in the two directly changed test files, plus the existing WordPress smoke now covers negative font flags.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, direct Marker page conversion, font flag decomposition, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell model execution, Texify, Streamlit/FastAPI workers, page-pixel visual recognition, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-overlap

This does not repeat missing/fractional font flag validation, font payload-key sanitation, keep-chars validation, page/ref sanitation, normalized/off-page bbox scaling, link disabling, script flags, sorting, blank-page preservation, layout/order supplied artifact matching, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, runtime model paths, or table/equation supplied-boundary work. The bounded behavior is specifically negative pdftext font descriptor flags at the supplied dictionary core and direct converter boundaries.
