# markerPDF runtime special-file preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T171357Z`
Session: `port-dev-markerpdf-runtime-preflight-20260608T171357Z`
Base accepted HEAD: `6c6778112a1a30eb54d9b7e115ddc72218dff4ab`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` builds candidate paths with `os.listdir(in_folder)`, then keeps only entries where `os.path.isfile(f)` is true before `os.makedirs(out_folder, exist_ok=True)`, chunk slicing, metadata loading, model handoff, `task_args`, and `mp.Pool`.

Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

Under the current no-GPU markerPDF lane rule, this slice records the runtime boundary for WordPress import review without executing Python, Torch, OCR, Surya, Texify, multiprocessing workers, pdftext, pypdfium, or external PDF tools.

## Behavior

`BatchConverter::runtimeMainPreflightPlan()` now exposes skipped non-file listing records for upstream `os.path.isfile` false entries:

- FIFO or other special paths named like PDFs are recorded with `path_type`, `os_path_isfile=false`, and `task_candidate=false`.
- `special_file_basenames` and `fifo_basenames` summarize special queue entries.
- Regular files, including non-PDF sidecars, still become task candidates exactly as upstream `convert.py` does.
- Special non-files stay excluded before chunking, basename metadata lookup, model handoff, task tuple construction, Pool launch, Python/models, multiprocessing, and external tools.

## Verification

Focused slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSpecialFileBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS excludes os path isfile false special files before chunking metadata and task args
1 test files, 31 assertions, 0 failures
```

Adjacent runtime preflight family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeOutputSymlinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeOutputPermissionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeNestedOutputBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeHardlinkIdentityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeSpecialFileBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1475 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-special-file-boundary-currentbase.php
```

The smoke exits 0 and emits `fifo_basenames=["import-pipe.pdf"]`, `task_filenames` excluding `import-pipe.pdf`, `selected_metadata_filenames=["public-report.pdf"]`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimeSpecialFileBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-special-file-boundary-currentbase.php
php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
php -r '$p="lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'
git diff --check -- lanes/markerpdf
```

All exited 0 during this handoff.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted runtime argparse, import order, path normalization, output-folder conflicts, metadata file open/load/shape/value/duplicate-key/numeric-key behavior, hardlink/symlink identity, chunk slicing, empty queues, worker Pool boundaries, pool drain/cleanup, process_single_pdf return handling, model share-memory branches, or single-document runtime preflight. The bounded behavior is specifically `convert.py` queue admission for special filesystem entries that fail `os.path.isfile`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `BatchConverter` runtime preflight planner, filesystem listing helpers, focused `TestRunner`, and WordPress smoke harness. Full live OCR/model execution, Surya/Texify/Torch model workers, GPU/MPS runtime parity, pdftext runtime calls, pypdfium/PDFium rendering, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
