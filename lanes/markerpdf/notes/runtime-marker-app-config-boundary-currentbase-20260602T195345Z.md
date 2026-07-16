# markerPDF Runtime Marker App Config Boundary

Micro-slice: `runtime-marker-app-config-boundary-currentbase`

Session: `port-dev-markerpdf-runtime44-20260602T1949Z`

Base accepted HEAD: `897b69532c5e798e5593546ffafd7329358413f2`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker_app.py` sets `PYTORCH_ENABLE_MPS_FALLBACK=1`, `IN_STREAMLIT=true`, and `PDFTEXT_CPU_WORKERS=1`, then configures Streamlit with `layout="wide"` and two equal columns.
- `marker_app.py` exposes a PDF-only uploader, a `Languages` multiselect over sorted `CODE_TO_LANGUAGE.values()` with `default=[]` and `max_selections=4`, `Max pages to parse` with `min_value=1` and `value=10`, `Force OCR on all pages` with default `False`, and a `Run Marker` gate before `convert_pdf(filename, languages, max_pages, ocr_all_pages)`.
- `run_marker_app.py` launches `streamlit run marker_app.py` with an environment overlay, but this PHP slice records only the app-config boundary and does not execute Streamlit.
- `marker/settings.py` supplies the same supported PDF-only filetype boundary and CPU worker defaults used by the native lane settings.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_app.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/run_marker_app.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/settings.py`

## Patch

- `MarkerRuntimePlanner::markerAppConfigPlan()` records the Streamlit page layout, PDF upload control, language multiselect, max-page input, OCR checkbox, preview controls, stop gates, import-time environment, and normalized `convert_single_pdf` arguments.
- The plan validates upstream control bounds: language selections must be upstream Surya language labels, must be unique, and are capped at four; `max_pages` must be at least one; OCR force input must be boolean-like.
- `wordpress-marker-runtime-app-config-boundary-currentbase.php` demonstrates a WordPress import worker inspecting the Marker app config without launching Streamlit, PDFium, PIL, Python, or model code.

## Evidence

Focused runtime test:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `1 test files, 76 assertions, 0 failures`.

Adjacent runtime/settings/language family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/OcrLanguageTest.php lanes/markerpdf/tests/MarkerSettingsTest.php`

Passed: `3 test files, 126 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-app-config-boundary-currentbase.php`

Passed: emitted `scenario=wordpress-marker-runtime-app-config-boundary-currentbase`, language limit `4`, selected languages `English,Spanish`, `conversion_args.langs=English,Spanish`, `max_pages=6`, `ocr_all_pages=true`, both stop gates true, and all execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/MarkerRuntimePlanner.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-app-config-boundary-currentbase.php`

All reported no syntax errors.

## Status Delta

- Behavior tests move `742 -> 744` PASS lines.
- Focused `MarkerRuntimePlannerTest.php` moved from 8 to 10 test cases and from 42 to 76 assertions.
- Mapped markerPDF semantics move `529 -> 530 / 78`.
- WordPress scenarios move `742 -> 744`.

## Dependency Closure

No new support component is needed. This reuses the native runtime planner, `OcrLanguage` Surya language inventory, existing marker settings defaults, and WordPress smoke harness. Full upstream markerPDF runtime parity remains gated on Poetry/Python setup, Streamlit, `pypdfium2`/PDFium, PIL, Surya/Torch model downloads, `pdftext`, tabled-pdf, Texify, FastAPI/Uvicorn, and live PDF/model execution.

## Non-Overlap

This does not repeat accepted Streamlit command planning, marker_app import environment, convert.py multiprocessing/model handoff, benchmark callback sandboxing, benchmark report/output verification, marker_server upload/local/remote error boundaries, parser/xref/font/image/security/table/form/outline/metadata current-base behavior, or live Python/model execution. The bounded behavior is only `marker_app.py` Streamlit app control configuration and the normalized conversion argument boundary for WordPress preflight.
