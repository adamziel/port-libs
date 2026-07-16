# markerPDF runtime model-list arity boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T230937Z`
Session: `port-dev-markerpdf-runtime-preflight-20260608T230937Z`
Accepted base: `e4c5b8530d7050cd247624ff66dfa0499e76de2a`

## Source Truth

- Upstream `sddai/markerPDF` `convert.py` loads models in the parent process for non-MPS devices, runs `model.share_memory()` for each model, passes the list to `worker_init`, and only then drains `process_single_pdf` results from a `Pool`.
- Upstream `marker/convert.py` `convert_single_pdf` unpacks `model_lst` into exactly six slots: texify, layout, order, detection, OCR, and table-recognition models. Partial or overfull lists therefore fail at the downstream Python unpack boundary, not during parent model handoff.

Raw source reviewed:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`

## Patch

- `BatchConverter` now records the expected six-slot `convert_single_pdf` model-list arity during runtime preflight review.
- Partial lists record `ValueError: not enough values to unpack (expected 6, got N)`.
- Overfull lists record `ValueError: too many values to unpack (expected 6)`.
- The boundary remains non-blocking before conversion summary, task tuple construction, `worker_init`, and `Pool` admission, matching upstream `process_single_pdf` error handling.
- The implementation remains review-only and does not launch Python, multiprocessing, OCR/model workers, PDFium/PIL, or external PDF tools.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeModelListArityBoundaryCurrentBaseTest.php`
  - `1 test files, 59 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntime*Model*CurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeWorkerInitBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeShareMemoryErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePoolCleanupMpsBoundaryCurrentBaseTest.php`
  - `7 test files, 334 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`
  - `1 test files, 1246 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-marker-runtime-model-list-arity-currentbase.php`
  - exits `0`, records `model_slot_count=5`, `model_list_arity_error_boundary=convert-single-pdf-model-unpack-failed`, `pool_launchable=true`, and all live execution flags false.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP batch runtime preflight planner, model handoff review, worker initializer review, and focused TestRunner harness. Live OCR/model execution remains intentionally out of scope under the no-GPU markerPDF lane rule.

## Non-Overlap

This slice does not repeat empty model-list handoff, share-memory failure, MPS cleanup, worker-init shared-model loading, input/output path normalization, metadata JSON boundaries, chunk slicing, invalid worker pool risks, or process_single_pdf return-value review. It only covers partial and overfull model-list arity boundaries deferred to `convert_single_pdf`.

## Next

Continue with native no-GPU markerPDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
