# Runtime Selected File Gone Boundary Current Base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260607T135621Z`
Base: `bcde00c99f7f103f12aeb62e041494db8ca298a6`
Date: 2026-06-07 UTC

## Source Truth

Upstream `sddai/markerPDF` `convert.py::main` selects task paths from
`os.listdir(input_folder)` after `os.path.isfile`, then hands task tuples to
`torch.multiprocessing.Pool(...).imap(process_single_pdf, task_args)`.
`process_single_pdf` checks `markdown_exists` first and only applies
`find_filetype(filepath)` when `min_length` is truthy. If filetype cannot be
determined, upstream prints the unknown-filetype diagnostic and returns `0`
without calling `convert_single_pdf`.

## Behavior Ported

`BatchConverter::processFilePreflightPlan()` now records selected task path
availability at the worker boundary:

- `selected-input-missing-before-worker-preflight`
- `selected-input-broken-symlink-before-worker-preflight`
- `selected-input-not-file-before-worker-preflight`
- `selected-input-unreadable-before-worker-preflight`

The runtime process-single review aggregates those boundaries by filename.
For a queued PDF path that disappears before the worker filetype gate, a
truthy `min_length` follows upstream `find_filetype` unknown-kind behavior:
`filetype=other`, `status=skipped-unsupported-filetype`, and
`upstream_return_value=0`. With no `min_length`, the plan preserves the
upstream converter-stage boundary and does not invent an earlier filetype
block.

## Evidence

Red-first focused run before source change:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSelectedFileGoneBoundaryCurrentBaseTest.php`

Result: failed with undefined `worker_file_availability_boundary` keys for
missing selected inputs.

After implementation:

`php -l lanes/markerpdf/src/BatchConverter.php`

Result: no syntax errors.

`php -l lanes/markerpdf/tests/MarkerRuntimeSelectedFileGoneBoundaryCurrentBaseTest.php`

Result: no syntax errors.

`php -l lanes/markerpdf/examples/wordpress-marker-runtime-selected-file-gone-currentbase.php`

Result: no syntax errors.

`php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`

Result: `lane-status json ok`.

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSelectedFileGoneBoundaryCurrentBaseTest.php`

Result: `1 test files, 41 assertions, 0 failures`.

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(BatchConverterTest|FiletypeDetectorTest|MarkerRuntime(Preflight|MarkdownExistsPath|FiletypeReview|MetadataFileOpen|OutputSymlink|WorkerInit|PoolContextManager|ShareMemoryError|EmptyQueueModelHandoff|SelectedFileGone).*CurrentBaseTest)\.php$|/BatchConverterTest\.php$|/FiletypeDetectorTest\.php$' | sort)`

Result: `13 test files, 1819 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-marker-runtime-selected-file-gone-currentbase.php`

Result: exits 0 and emits
`worker_file_availability_boundary=selected-input-missing-before-worker-preflight`,
`upstream_return_value=0`, `should_invoke_converter=false`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

`git diff --check -- lanes/markerpdf`

Result: no whitespace errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This is a native PHP runtime preflight
review that reuses existing filesystem probes, `FiletypeDetector`, and
`OutputWriter`; it does not run Python, Torch, OCR, Surya, Texify, pdftext,
pypdfium, Streamlit, FastAPI, model workers, raster decoding, external PDF
tools, or online services.

## Non-Overlap

This slice does not repeat the accepted runtime metadata, output symlink,
markdown-exists, filetype review, worker-init, pool context, share-memory, or
empty-queue boundaries. It covers the later worker-side availability state for
already selected task paths that vanish or become unavailable before
`process_single_pdf` reaches the optional filetype gate.
