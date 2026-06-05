# Runtime Preflight Os.Listdir Order Boundary - 2026-06-05

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T091620Z`
Base accepted HEAD: `4aa0cc3d1c79d46c5770f63de91624ccc6645a18`

## Source Truth

- Upstream markerPDF `convert.py::main` at `sddai/markerPDF` pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` builds `files` from `os.listdir(in_folder)` filtered by `os.path.isfile(...)`, then applies chunk and max slicing to that order.
- Source URL: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now preserves filesystem directory order for the convert.py runtime path instead of sorting before chunking.
- Runtime input listing metadata now records `entry_order_source`, `sort_applied_before_chunking`, and `preserves_os_listdir_order`.
- Runtime chunk metadata now records `input_order_source`, `sorts_before_chunking`, and `preserves_input_listing_order`.
- The existing sorted helper behavior remains available for non-runtime batch helpers through the default `inputDirectoryListing()` path.
- The WordPress runtime preflight smoke now emits and validates the no-sort boundary while keeping non-PDF sidecar and metadata preflight checks keyed by basename.

## Red-First Evidence

- Before the implementation change, `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php` failed the new `preserves convert.py os.listdir order through chunk and max slicing` case because runtime preflight sorted entries alphabetically before chunking.

## Verification

- `php -l lanes/markerpdf/src/BatchConverter.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php` => 1 test file, 780 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php` => 2 test files, 891 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php` => smoke passed and emitted `runtime_sort_applied_before_chunking=false`, `runtime_preserves_os_listdir_order=true`, `runtime_sorts_before_chunking=false`, and `runtime_preserves_input_listing_order=true`.

## Non-Overlap

This does not overlap the accepted CCITT Fax alias filter boundary, image/filter review slices, xref repair work, metadata extraction, annotations/forms, or GPU/model/OCR behavior. It is limited to native no-GPU runtime preflight ordering for searchable-PDF import queues and task handoff metadata.

## Dependency Closure

No new support component is required. The slice reuses PHP directory handles (`opendir`/`readdir`) to mirror the upstream `os.listdir` boundary locally and does not run Python, models, external PDF tools, Torch, Streamlit, or FastAPI workers.

## Next

Continue native markerPDF work on non-overlapping searchable-PDF behavior such as fonts/CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
