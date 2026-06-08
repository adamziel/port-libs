# markerPDF runtime single-document trailing basename boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T055434Z`

Base accepted HEAD: `01048f98727ca2e231e798c72d6a8093d9f4eefd`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert_single.py` keeps the original `args.filename` through `convert_single_pdf(...)`, then derives `fname = os.path.basename(fname)` only after conversion returns and before `save_markdown(...)`.
- On the upstream POSIX runtime, `os.path.basename('/wp-content/uploads/file.pdf/')` is an empty string. `marker.output.save_markdown` then maps that empty `fname` to the output folder itself, `.md`, and `_meta.json`.
- This PHP boundary intentionally stays review/supplied-converter only. It does not launch Python, pdfium, OCR, Surya/Texify/Torch, or model workers.

Source URLs used:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert_single.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`

## Patch

- `SingleDocumentConverter` now derives the post-conversion output filename with a POSIX slash split matching upstream `os.path.basename`, instead of PHP `basename()` normalization.
- `runtimePreflightPlan()` no longer turns a trailing-separator single-document path into the last non-empty segment before the upstream output boundary. It records `basename_source` and `empty_basename_after_trailing_separator` in `output_policy`.
- Supplied native single-document conversion now writes the same empty-basename artifact paths upstream would use after a successful converter return: `<output>/.md`, `<output>/_meta.json`, and images directly under `<output>/`.
- `wordpress-marker-runtime-single-trailing-basename-currentbase.php` demonstrates the WordPress review path without executing Python, models, OCR, Streamlit, FastAPI, or external PDF tools.

## Evidence

Red-first probe before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSingleTrailingBasenameBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files, 5 assertions, 2 failures`; both failures showed PHP deriving `editorial-checklist.pdf` where upstream expects an empty basename.

Focused runtime/single-document verification after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeSingleTrailingBasenameBoundaryCurrentBaseTest.php`

Passed: `3 test files, 1296 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-single-trailing-basename-currentbase.php`

Passed: emitted `empty_basename_after_trailing_separator=true`, `existing_markdown_seen=true`, `skips_existing_markdown=false`, `converted_markdown=<tmp>/.md`, `metadata_path=<tmp>/_meta.json`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Adds 2 focused PHP PASS cases and 24 focused assertions for the single-document trailing-separator output boundary.
- Adds 1 WordPress smoke scenario.

## Dependency Closure

No new support component is needed. The patch reuses `SingleDocumentConverter`, `OutputWriter`, the native test harness, and a supplied converter callback. GPU/model execution, OCR, Surya/Texify/Torch, pypdfium/PDFium runtime conversion, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted batch runtime preflight, metadata-file ordering, numeric gates, output-folder conflicts, model handoff/share-memory, multiprocessing pool boundaries, single-document argparse boundaries, general single-document runtime admission, `markdown_exists` directory-path handling, output filename extension splitting, native PDF parser/xref/font/CMap/security/image/form/table/outline behavior, or supplied pdftext layout/order sidecar handling. The bounded behavior is only the post-conversion `convert_single.py` empty basename handoff for trailing-separator single-document paths.
