# markerpdf-runtime-preflight-boundary-current-base-20260605T031648Z

## Source Truth

- Upstream `sddai/markerPDF` pinned commit: `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Relevant upstream boundary: `convert.py::main` lists `in_folder` with `os.listdir(in_folder)` before `os.makedirs(out_folder, exist_ok=True)`, metadata JSON loading, spawn start-method setup, model handoff, conversion summary, task tuple construction, and `torch.multiprocessing.Pool`.
- In this no-GPU lane, the port records the boundary only. It does not execute Python, Torch, OCR/model code, multiprocessing, pypdfium/PIL, or external PDF tools.

## Patch

- Added `BatchConverter::runtimeMainPreflightErrorBoundary()` as a WordPress-safe wrapper around the existing throwing `runtimeMainPreflightPlan()` contract.
- Missing input folders are reported as an upstream-style `FileNotFoundError`.
- File-valued input paths are reported as an upstream-style `NotADirectoryError`.
- Both input-folder list failures record that output folder creation, chunking, metadata loading, spawn/model handoff, conversion summary, task args, and pool launch are blocked.
- The existing output-folder conflict, metadata-file, spawn-collision, task-args, and process_single_pdf preflight behaviors remain unchanged.

## Evidence

- `php -l lanes/markerpdf/src/BatchConverter.php`  
  `No syntax errors detected in lanes/markerpdf/src/BatchConverter.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`  
  `No syntax errors detected in lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`  
  `No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`  
  `1 test files, 585 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`  
  `3 test files, 772 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`  
  Exits `0` and emits missing/file-valued input-folder boundary fields.

## Dependency Closure

No new support component is needed. This slice reuses native PHP filesystem inspection plus the existing markerPDF runtime preflight planners. GPU/model execution, live OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-overlap

This slice does not repeat the accepted DCTDecode/CCITT stream-filter boundary work, output-folder file-conflict preflight, metadata JSON order/shape/value boundaries, spawn start-method collision handling, remote polling, benchmark server artifacts, server upload adapters, parser xref repair, annotations/forms, page geometry, or image/filter metadata slices. It only owns the current-base `convert.py::main` input-folder listing failure boundary.
