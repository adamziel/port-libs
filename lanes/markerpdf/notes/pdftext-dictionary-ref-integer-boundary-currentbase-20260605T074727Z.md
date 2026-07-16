# markerPDF pdftext dictionary ref integer boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T074727Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T074727Z`
Base accepted HEAD: `04872dbb3131d5a034d1e365b9c27ae699e2563e`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` and converts returned page dictionaries into Marker page/block/span structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `pdftext.pdf.links.PageReference` stores internal references with integer `page` and `idx` fields; its `ref`/`url` are derived as `page-{page}-{idx}` and `#{ref}`. Fractional values at this boundary should fail closed rather than truncating to a different WordPress target.

## Change

`PdfTextBlockConverter::pdftextRefs()` now validates page-reference integer metadata as finite whole numbers before preserving source refs or synthesizing internal anchors:

- `refs[*].page` must be an integer-valued number;
- `refs[*].dest_page` must be an integer-valued number;
- `refs[*].idx` must be an integer-valued number;
- coordinate and bbox metadata remain numeric floats as before.

This prevents supplied pdftext dictionaries from turning values such as `page=9.5` or `idx=2.5` into truncated anchors like `#page-9-2`.

## Red First

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
FAIL rejects fractional pdftext reference integer metadata before anchor synthesis
Expected exception InvalidArgumentException was not thrown
1 test files, 95 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextBlockConverter.php
No syntax errors detected in lanes/markerpdf/src/PdfTextBlockConverter.php

php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-ref-integer-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-ref-integer-boundary-currentbase.php
```

```text
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
```

Passed with no output.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 97 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
3 test files, 390 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-ref-integer-boundary-currentbase.php
```

The smoke emitted `valid_reference_anchor_synthesized=true`, `fractional_page_rejected=true`, `fractional_dest_page_rejected=true`, `fractional_idx_rejected=true`, `visible_wordpress_text="Review linked section before import"`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused TestRunner PASS case and +3 focused assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`, raising the focused file from 94 to 97 assertions and `lane-status.json` `phpPass` from 1588 to 1589. Mapped upstream denominator is unchanged; this reuses the existing pdftext dictionary core support component.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native supplied pdftext dictionary conversion, page-reference metadata sanitation, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.

## Non-overlap

This does not repeat accepted pdftext span URL promotion, supplied `ref`/`url` preservation, unsafe reference URL exclusion, anchor synthesis for valid integer PageReference rows, keep-chars sanitation, character/font key filtering, bbox normalization, block sorting, blank-page handling, sparse layout/order matching, OCR/table supplied-boundary routing, parser/xref recovery, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, or runtime preflight. The bounded behavior is only fail-closed integer validation for `pdftext_source.refs` before WordPress anchor synthesis.
