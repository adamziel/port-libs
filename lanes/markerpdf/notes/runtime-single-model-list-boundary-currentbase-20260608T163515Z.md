# markerPDF runtime single model-list boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T163515Z`

Session: `port-dev-markerpdf-runtime-preflight-20260608T163515Z`

Accepted base: `989d72297d7b2e126aa296fdd7e44238e330f68d`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `load_all_models()` in `convert_single.py` before `convert_single_pdf(...)`.

`marker/models.py::load_all_models()` constructs models in this order:

1. `setup_detection_model`
2. `setup_layout_model`
3. `setup_order_model`
4. `setup_recognition_model`
5. `setup_texify_model`
6. `setup_table_rec_model`

It then returns `model_lst = [texify, layout, order, detection, ocr, table_model]`. The single-document runtime passes that list directly to `convert_single_pdf`. Unlike batch `convert.py`, this path has no parent `share_memory()` loop and no multiprocessing pool.

Source URLs used:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert_single.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/models.py`

## Patch

- `SingleDocumentConverter::runtimePreflightPlan()` now records `model_load_sequence` with the upstream construction order, returned slot order, slot setup functions, model loaders, processor loaders, checkpoint/default device sources, and no-execution flags.
- `model_boundary` now exposes the six-slot `model_lst` order, `load_all_models()` source, recognition-model always-loaded boundary, and single-document no-`share_memory()` boundary.
- `conversion_call` now records that `convert_single_pdf` receives `model_lst returned by load_all_models()`.
- `wordpress-marker-runtime-single-model-list-currentbase.php` emits a WordPress single-upload runtime smoke for the same model-list handoff without running Python, Torch, OCR, Surya, Texify, multiprocessing, or external PDF tools.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSingleModelListBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning:  Undefined array key "model_load_sequence" ...
FAIL records convert_single load_all_models slot order before single pdf conversion
Expected: 'marker.models.load_all_models'
Actual: NULL
1 test files, 4 assertions, 1 failures
```

## Verification

Focused new slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSingleModelListBoundaryCurrentBaseTest.php
1 test files, 33 assertions, 0 failures
```

Focused runtime/single-document regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSingleModelListBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/MarkerRuntimeSingleImportOrderBoundaryCurrentBaseTest.php
4 test files, 1344 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-single-model-list-currentbase.php
```

The smoke exits `0` and emits `model_slot_order=["texify","layout","order","detection","ocr","table_model"]`, `model_slot_count=6`, `recognition_model_always_loaded_for_single_document=true`, `single_document_share_memory_loop=false`, `conversion_model_argument_source="model_lst returned by load_all_models()"`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused behavior PASS cases move `3296 -> 3297`.
- WordPress scenarios move `2687 -> 2688`.
- Mapped runtime manifest gains `markerRuntimeSingleModelListBoundaryCurrentBase`.

## Non-Overlap

This does not repeat batch `convert.py` worker init, MPS model handoff, parent `share_memory()` slot review, empty model-list handling, pool cleanup, task-arg tuples, `process_single_pdf`, output save errors, single argparse import order, or native PDF parser/font/xref/security/image/table behavior. The new behavior is only the single-document `convert_single.py` direct `load_all_models()` model-list sequence and `convert_single_pdf` model argument boundary.

## Dependency Closure

No new support component is needed. This slice reuses the native `SingleDocumentConverter`, `OutputWriter`, lane manifest, and focused PHP test harness. Live OCR, Surya/Texify/Torch model loading, GPU/MPS execution, pypdfium/PDFium rendering, pdftext runtime extraction, multiprocessing workers, and exact upstream benchmark parity remain intentionally out of scope under the no-GPU markerPDF lane rule.
