# markerPDF runtime metadata numeric-key boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T072211Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` loads `--metadata_file` with `json.load(f)`, then builds task tuples with `metadata.get(os.path.basename(f))` after chunking and before `mp.Pool(...)`.
- Python JSON object keys remain dictionary string keys even when the basename is numeric-looking, so a WordPress upload file named `0` or `01` can still receive metadata through `metadata.get(...)`.
- The native PHP no-GPU runtime preflight must preserve that review boundary without launching Python, Torch multiprocessing, pdftext, PDFium, model workers, or external PDF tools.

## Change

- `BatchConverter::loadRuntimeMetadataFile()` now walks the decoded top-level JSON object and stringifies keys before storing runtime metadata values, instead of treating PHP integer-cast numeric object keys as a metadata shape failure.
- Runtime metadata review now reports numeric-looking metadata filenames such as `0`, `01`, and `2.pdf`, preserves selected/missing filename lists, and keeps task-argument metadata aligned with `metadata.get(os.path.basename(f))`.
- Added a focused WordPress smoke for numeric-looking upload filenames in the convert.py runtime queue.

## Verification

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
FAIL keeps numeric-string metadata filenames addressable like Python json dict keys
Expected: 'object'
Actual: 'dict'
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
1 test files, 733 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-metadata-numeric-key-currentbase.php
metadata_filenames=["0","01","2.pdf"]
missing_metadata_is_null=true
executes_python_or_models=false
executes_multiprocessing=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted runtime output-folder conflicts, metadata JSON load/shape errors, scalar/list per-file metadata value truthiness, spawn start-method failures, model handoff branching, pool process-count creation, worker cleanup, non-PDF sidecar task preflight, numeric truthiness, negative chunk slicing, text-length exceptions, server/benchmark artifacts, pdftext dictionary slices, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, or supplied table/equation boundaries. The bounded behavior is only numeric-looking top-level metadata JSON filename keys before WordPress runtime task-argument construction.

## Dependency Closure

No new support component is needed. This reuses the native PHP `BatchConverter` runtime preflight, JSON metadata-file loader, task-argument review, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
