# Runtime Benchmark API Current Base

Slice: `runtime-benchmark-api-currentbase`
Session: `port-dev-markerpdf-runtime37pdf-20260602T1829Z`
Base: `3439e210d8ddc181cab037bb234e5c258deb5ba1`

## Source Truth

- Upstream `benchmarks/overall.py` at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` builds the method list from Marker plus optional Nougat, passes `marker_batch_multiplier` into `convert_single_pdf`, passes `nougat_batch_size` into Nougat, and names CUDA memory snapshots `model_load.pickle` plus `marker_memory_{idx}.pickle` when `--profile_memory` is enabled.
- Upstream execution still loads Python models, may launch Nougat, and may record CUDA memory history. This PHP slice preserves those runtime boundaries as native metadata and supplied-callback context only.

## Behavior

- `BenchmarkRunner` now accepts upstream-shaped runtime options: `nougat`, `methods`, `marker_batch_multiplier`, `nougat_batch_size`, and `profile_memory`.
- When `nougat` is enabled, supplied converters run in upstream order: `marker`, then `nougat`, independent of PHP array insertion order.
- Supplied converter callbacks receive a fourth context argument with method name, document, benchmark index, non-execution flag, Marker batch multiplier, Nougat batch size, and Marker memory snapshot name when profiling is requested.
- The benchmark result includes a `runtime` report with method order, batch sizes, `model_load.pickle`, per-document Marker memory snapshot names, and `executes_external_tools=false`.
- Missing explicit runtime converters and invalid batch sizes fail before conversion.

## WordPress Path

`examples/wordpress-benchmark-runner.php` now runs a supplied Marker callback and a supplied comparison callback over the committed CI benchmark excerpts, writes `marker_*.md` and `nougat_*.md` files, emits the runtime metadata, and verifies Marker threshold scores before a WordPress PDF import batch reaches editorial review.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`
  - `1 test files, 44 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-benchmark-runner.php`
  - emitted `scenario=wordpress-pdf-benchmark-runner`, runtime methods `marker,nougat`, `model_load_snapshot=model_load.pickle`, Marker snapshots `marker_memory_0.pickle` and `marker_memory_1.pickle`, and `passes_upstream_ci_marker_thresholds=true`.

## Dependency Closure

No new support component is needed. This reuses the native benchmark scorer, benchmark report builder, supplied-callback benchmark runner, committed upstream CI benchmark excerpts, and WordPress smoke path. Full upstream runtime parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, `tabled-pdf`, Texify, Nougat execution, CUDA memory profiling, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted parser/xref/font/image/table/security/page metadata current-base handoffs. The new behavior is specifically the native benchmark runtime/API boundary for upstream `overall.py` option semantics and memory-profile naming.
