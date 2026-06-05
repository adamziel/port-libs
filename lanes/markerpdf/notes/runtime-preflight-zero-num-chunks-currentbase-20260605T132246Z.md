# markerPDF runtime zero num_chunks preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T132246Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T132246Z`
Base accepted HEAD: `63ef80057b8d1de0508797d5d478036f38041bd9`
Date: `2026-06-05T13:22:46Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned by the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- `convert.py::main` parses `--num_chunks` as an integer, normalizes input/output paths, lists files with `os.listdir` and `os.path.isfile`, calls `os.makedirs(out_folder, exist_ok=True)`, then computes `chunk_size = math.ceil(len(files) / args.num_chunks)`.
- For `--num_chunks=0`, Python raises `ZeroDivisionError: division by zero` at chunk math after listing/output-folder preflight and before metadata loading, spawn start-method setup, model handoff, task args, conversion summary, or multiprocessing.

## Implementation

- `BatchConverter::runtimeMainPreflightErrorBoundary()` now preserves successful input listing and output-folder preflight details when the later runtime stage fails.
- The zero `num_chunks` wrapper boundary now records `chunking_reached=true`, `chunk_error_boundary=chunk-files-failed`, `chunk_error_class=ZeroDivisionError`, and `chunk_error_message=division by zero`.
- The WordPress runtime smoke emits the same zero-chunk review fields while proving metadata, model handoff, task args, summary, multiprocessing, Python/models, and external PDF tools are not reached.
- `UPSTREAM_TEST_MANIFEST.json` maps `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors` from `3` to `4`; `lane-status.json` moves `phpPass` from `1862` to `1863` and WordPress scenarios from `1690` to `1691`.

## Evidence

Red-first focused run after adding the new test and before source edit:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result: `1 test files / 852 assertions / 1 failures`; the new zero `num_chunks` case received `Batch chunk count must be at least one.` instead of upstream `division by zero`.

Focused run after source edit:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result: `1 test files / 892 assertions / 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
```

Result: emitted `zero_num_chunks_error_boundary=chunk-files-failed`, `zero_num_chunks_error_class=ZeroDivisionError`, `zero_num_chunks_upstream_error_message=division by zero`, `zero_num_chunks_listing_success=true`, `zero_num_chunks_output_creation_reached=true`, `zero_num_chunks_metadata_load_reached=false`, `zero_num_chunks_task_args_count=0`, and `zero_num_chunks_summary_reached=false`, with all Python/model/multiprocessing/external-tool execution flags false.

Syntax/JSON checks run:

```sh
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "json ok\n";'
```

Result: no syntax errors and `json ok`.

## Dependency Closure

No new support component is needed. This reuses native PHP runtime planning, filesystem listing, output-folder review, Python slice/chunk boundary reporting, metadata/task gating, and the existing WordPress smoke. Full live runtime parity remains intentionally out of scope under the no-GPU markerPDF direction because it requires Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, Surya/Texify/tabled models, OCR/raster helpers, model downloads, Streamlit/FastAPI, and external PDF tools.

## Non-Overlap

This does not repeat accepted negative `num_chunks` slicing, negative `chunk_idx`, output-folder file/parent conflicts, metadata JSON load/shape/value boundaries, relative metadata paths, duplicate metadata keys, spawn start-method failures, model handoff branches, worker pool creation/cleanup, conversion summary ordering, process_single_pdf return-value gates, text-length exceptions, single-document runtime preflight, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only the zero `--num_chunks` chunk-math failure ordering after listing/output preflight and before metadata/model/pool execution.
