# markerPDF runtime input/output abspath preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T094032Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T094032Z`
Base accepted HEAD: `333ee46512d5ab2039cf170209aca42d287f1569`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `convert.py::main` parses CLI arguments, then computes `in_folder = os.path.abspath(args.in_folder)` and `out_folder = os.path.abspath(args.out_folder)` before `os.listdir(in_folder)`, `os.makedirs(out_folder, exist_ok=True)`, chunking, metadata loading, model handoff, task tuple construction, or `mp.Pool`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Change

- `BatchConverter::runtimeMainPreflightPlan()` and `runtimeMainPreflightErrorBoundary()` now expose `paths.path_resolution` review metadata for the input/output `os.path.abspath` boundary.
- The review records original input/output arguments, process-cwd vs already-absolute resolution, absolute paths used by input listing/output creation, and that abspath itself does not touch the filesystem or execute Python/model/multiprocessing/external tools.
- The existing WordPress runtime preflight smoke now emits relative input/output abspath fields alongside the already accepted metadata-file relative-path review.
- `UPSTREAM_TEST_MANIFEST.json` moves `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors` and its mapped count from `3` to `4`.

## Evidence

Focused runtime test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result: `1 test files, 1196 assertions, 0 failures`.

Adjacent runtime/batch check:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php
```

Result: `3 test files, 1410 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
```

Result: exits `0` and emits `relative_input_output_abspath_base=[process_cwd,process_cwd]`, `relative_input_listing_uses_absolute_input=true`, `relative_output_creation_uses_absolute_output=true`, `relative_input_output_filesystem_touched_by_abspath=false`, `executes_python_or_models=false`, `runtime_executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

Syntax/JSON/diff:

```sh
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

Result: PHP lint passed, manifest/status JSON parsed, and diff check passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP runtime preflight planner, path normalization helper, filesystem review, task tuple review, and WordPress smoke path. Full upstream runtime execution remains intentionally out of scope under the no-GPU markerPDF rule because it requires Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI paths, and external PDF tooling.

## Non-Overlap

This does not repeat accepted relative `metadata_file` cwd resolution, empty metadata-file truthiness, argparse option-value boundaries, input-folder list failures, output-folder target/parent conflicts, os.listdir file-only ordering, symlink task identity, chunk/max slicing, worker-count/Pool creation, result drain, worker cleanup, process_single_pdf min-length/filetype gates, post-conversion error boundaries, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only input/output folder `os.path.abspath` resolution before listdir and makedirs.
