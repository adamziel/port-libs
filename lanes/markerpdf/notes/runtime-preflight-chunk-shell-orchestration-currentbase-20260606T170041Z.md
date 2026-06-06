# markerpdf runtime preflight chunk shell orchestration current-base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T170041Z`
Base accepted HEAD: `2fafdab3d147dccac973662b1b9ba5c7bdadcbfd`

## Upstream source truth

- `sddai/markerPDF` pinned `chunk_convert.py` builds one raw command string from `script_path`, `in_folder`, and `out_folder`, then calls `subprocess.run(..., shell=True, check=True)`.
- `sddai/markerPDF` pinned `chunk_convert.sh` validates `NUM_DEVICES`, `NUM_WORKERS`, input, and output, installs a SIGINT cleanup trap, launches one `marker` command per device through `eval $cmd &`, sleeps five seconds between launches, and finishes with `wait`.

Pinned sources:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.sh

## Patch summary

- `ChunkConversionPlanner::planDeviceJobs()` now emits review-only `shell_orchestration` metadata for SIGINT cleanup, backgrounded launches, eval usage, five-second pacing, final wait semantics, and no-execution flags.
- Each planned device job now carries `shell_launch` metadata with the upstream command assignment pattern, optional `METADATA_FILE` and `MIN_LENGTH` append patterns, raw unescaped command fragments, path-hazard detection, and no-execution flags.
- `wordpress-chunk-convert-queue.php` now validates and reports trap/background/eval/wait metadata while preserving `executes_subprocess=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Verification evidence

Red-first:

- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeChunkShellOrchestrationCurrentBaseTest.php` failed before the source change because `shell_orchestration` was missing: `1 test files / 1 assertions / 1 failure`.

After implementation:

- `php -l lanes/markerpdf/src/ChunkConversionPlanner.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimeChunkShellOrchestrationCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-chunk-convert-queue.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeChunkShellOrchestrationCurrentBaseTest.php` => `1 test files / 41 assertions / 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeChunkShellOrchestrationCurrentBaseTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php` => `2 test files / 146 assertions / 0 failures`.
- `php lanes/markerpdf/examples/wordpress-chunk-convert-queue.php` => emits trap/background/eval/wait review metadata and no shell/Python/model/external-tool execution.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not touch the accepted DCTDecode image/filter work, PDF parser stream recovery, OCR/model paths, Streamlit/FastAPI model workers, or live benchmark parity. It is limited to the native runtime preflight boundary for chunk conversion shell orchestration.

## Dependency closure

No new support component is needed. The patch reuses the existing native `ChunkConversionPlanner` review-only runtime planner and does not launch shell subprocesses, Python, CUDA, Torch, OCR/model workers, or external PDF tools.

Next in-scope markerPDF work should continue with native searchable-PDF parser/converter behavior or supplied-boundary handoffs, especially remaining runtime preflight boundaries that can be mapped without executing upstream model dependencies.
