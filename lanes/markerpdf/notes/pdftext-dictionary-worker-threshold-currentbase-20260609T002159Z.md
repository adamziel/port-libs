# markerPDF pdftext dictionary worker-threshold boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260609T002159Z`

Session: `port-dev-markerpdf-pdftext-dictionary-20260609T002159Z`

Base accepted HEAD: `72cabc3f4f492b184408152fdc147cadc8cc603f`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)`, then enumerates the returned page dictionaries before converting each page into Marker `Page` and `Span` structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` v0.3.18 clamps requested workers in `_get_pages()` with `min(workers, len(page_range) // settings.WORKER_PAGE_THRESHOLD)` and uses the sequential path when the effective worker count is `None`, `0`, or `1`: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py
- Locked `pdftext` v0.3.18 sets `WORKER_PAGE_THRESHOLD` to `10`: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/settings.py

## Implemented Behavior

- `PdfTextDocumentExtractor` now records `metadata.pdftext_worker_plan` whenever callers supply `workers`.
- The plan preserves the requested worker count, selected page count, threshold `10`, effective worker count, and whether upstream would use multiprocessing or the sequential fallback.
- `metadata.pdftext_options.workers` remains the requested value for compatibility with existing review metadata.
- Selected page slicing, source page numbers, relative span IDs, dictionary-output sanitation, and hidden payload exclusion are unchanged.

## Verification

Red before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreWorkerThresholdBoundaryCurrentBaseTest.php
FAIL records upstream pdftext worker threshold decisions at the dictionary core boundary
Undefined array key "pdftext_worker_plan"
1 test files, 2 assertions, 1 failures
```

Green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreWorkerThresholdBoundaryCurrentBaseTest.php
1 test files, 18 assertions, 0 failures
```

Adjacent pdftext verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreWorkerThresholdBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
4 test files, 815 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-worker-threshold-currentbase.php --self-test
exits 0 with effective_workers=2, small_range_sequential_fallback=true, hidden_worker_payload_excluded=true, executes_python_pdftext=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreWorkerThresholdBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-worker-threshold-currentbase.php
git diff --check -- lanes/markerpdf
```

Focused delta: +1 focused PASS file, +18 focused assertions, and +1 WordPress smoke. `lane-status.json` `phpPass` moves `3613 -> 3614`; `wordpressScenarios` moves `2917 -> 2918`; mapped upstream denominator is unchanged.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, dictionary-output sanitation, Markdown/WordPress rendering, and the existing smoke harness. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell models, Texify, Streamlit/FastAPI workers, page-pixel recognition, multiprocessing execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted dictionary_output envelope unwrapping, raw JSON page maps, duplicate/overflow/negative page-key guards, selected blank pages, sorting, quote-loosebox metadata, zero-worker admission, keep-chars sanitation, link/ref preservation, disabled-link handling, empty-span filtering, source dimension/bbox normalization, Unicode repair, layout/order artifact routing, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security preflight, runtime preflight, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is only the upstream `pdftext` worker-threshold clamp for selected dictionary-output page ranges.
