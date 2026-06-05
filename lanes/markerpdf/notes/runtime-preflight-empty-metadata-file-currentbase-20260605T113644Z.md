# markerPDF runtime empty metadata_file boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T113644Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T113644Z`
Base accepted HEAD: `9dd308c8531f969b5929f305821c04496af3829b`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` keeps `--metadata_file=` as the parsed empty string, but the runtime branch uses Python truthiness with `if args.metadata_file:` before `os.path.abspath()` and `json.load(f)`.
- The same upstream `convert.py::main` later attaches per-file metadata with `metadata.get(os.path.basename(f))`, so an empty metadata-file argument should not attempt to open or decode a JSON file before task tuple construction.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- Live Python, Torch multiprocessing, pdftext, pypdfium2/PDFium, Surya/Texify/tabled models, Streamlit/FastAPI, and external PDF tools remain intentionally out of scope.

## Patch

- `BatchConverter::runtimeMainArgumentPreflightPlan()` now separates a present empty `metadata_file` argv value from a truthy metadata-file runtime load.
- The semantic boundary records `metadata_file_truthy_for_json_load=false` and `empty_metadata_file_skips_json_load=true` for `--metadata_file=`.
- `BatchConverter::runtimeMainPreflightPlan()` already treats `metadataFile: ''` as no file load; the new focused test proves it keeps explicit `metadataByFilename` task metadata and ignores malformed `metadata.json` decoys in file-system paths.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits the empty metadata-file boundary for WordPress import review.
- `UPSTREAM_TEST_MANIFEST.json` maps `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors` from `3` to `4`; `lane-status.json` moves `phpPass` and `wordpressScenarios` from `1782` to `1783` and `1622` to `1623`.

## Evidence

Red-first focused run after adding the test and before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files, 828 assertions, 1 failures`; the new empty metadata-file case expected `metadata_file_read_deferred_until_after_chunk_files=false` but the old plan returned true.

Focused assigned gate after patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 847 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `empty_metadata_file_arg_value=""`, `empty_metadata_file_truthy_for_json_load=false`, `empty_metadata_file_skips_json_load=true`, `empty_metadata_file_runtime_source=metadataByFilename argument`, `empty_metadata_file_runtime_metadata_file=null`, selected metadata only for `ready-for-marker.pdf`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP batch runtime planner, argparse mirror, metadata-file branch semantics, task-argument preview, and existing WordPress runtime smoke. Full upstream runtime execution remains dependency-gated on Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, Surya/Texify/tabled model workers, model downloads, Streamlit/FastAPI/Uvicorn paths, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted runtime argparse admission, readable malformed metadata JSON errors, relative metadata-file resolution, metadata shape/value handoff, output-folder conflicts, input-folder listing failures, numeric truthiness gates, negative chunk slicing, spawn start-method collisions, model handoff branches, conversion summary ordering, worker pool creation/cleanup, per-file `process_single_pdf` preflight, single-document runtime preflight, server runtime artifacts, benchmark artifacts, or native PDF parser/xref/font/image/form/outline/security metadata behavior. The bounded behavior is only empty `--metadata_file=` being parsed but falsy for the upstream JSON-load branch before model handoff and worker launch.
