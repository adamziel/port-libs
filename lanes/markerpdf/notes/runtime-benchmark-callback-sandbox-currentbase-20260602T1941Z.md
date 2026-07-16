# markerPDF Runtime Benchmark Callback Sandbox

Micro-slice: `runtime-benchmark-callback-sandbox-currentbase`

Session: `port-dev-markerpdf-runtime43pdf-20260602T1935Z`

Base accepted HEAD: `2a344ae8c1b485daa88b3fe8a487f8ab30d2feff`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `benchmarks/overall.py` runs a bounded benchmark loop: enumerate input PDFs, read same-basename reference Markdown, count pages with PDFium, call `convert_single_pdf`, optional `nougat_prediction`, or `naive_get_text`, then write only the runner-owned `{method}_{document}.md` outputs and final JSON/table report.

Primary upstream file inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/benchmarks/overall.py`

## Behavior

- `BenchmarkRunner` now defaults to a supplied-callback sandbox around page-count and conversion callbacks.
- The sandbox fingerprints the staged PDF, reference Markdown, and optional Markdown output directory before each callback, then rejects mutations before runner-owned Markdown writes happen.
- Runtime metadata exposes `callback_sandbox.enabled`, watched inputs, and the fact that runner Markdown writes occur after callback review.
- Converter context now includes `callback_sandbox=true` so WordPress import tooling can audit the supplied-callback boundary.
- `sandbox_callbacks=false` remains available for intentionally unsafe local diagnostic fixtures, and runtime metadata records that opt-out.

## Red First

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`

Failed before implementation: `1 test files, 44 assertions, 3 failures`, including missing `callback_sandbox` runtime metadata and missing mutation blocking for supplied benchmark callbacks.

## Verification

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`

Passed: `1 test files, 62 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-marker-runtime-benchmark-callback-sandbox-currentbase.php`

Passed: emitted two benchmark files, `marker_multicolcnn.md` and `marker_switch_trans.md`, `callback_sandbox.enabled=true`, two callback contexts with `executes_external_tools=false`, `blocked_rogue_callback_write=true`, and `passes_upstream_ci_marker_thresholds=true`.

## Status Delta

- Behavior tests move `718 -> 720` PASS lines.
- Mapped markerPDF semantics move `516 -> 517 / 78`.
- WordPress scenarios move `718 -> 720`.

## Dependency Closure

No new support component is needed. This reuses the native benchmark runner, benchmark report verifier, committed CI benchmark excerpts, PHP file hashing, and WordPress smoke path. Full upstream runtime parity remains dependency-gated on Poetry/Python setup, `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, `tabled-pdf`, Texify, Nougat execution, CUDA profiling, Streamlit/FastAPI/Uvicorn runtime paths, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted runtime benchmark API callback option mapping, benchmark report output persistence, score-file verification, conversion/memory error reporting, marker_server upload error boundaries, parser/xref/font/image/security/table/form/outline/metadata current-base behavior, or live Python/model execution. The bounded behavior is only the native supplied benchmark callback sandbox for `benchmarks/overall.py` staged input/reference/output integrity.
