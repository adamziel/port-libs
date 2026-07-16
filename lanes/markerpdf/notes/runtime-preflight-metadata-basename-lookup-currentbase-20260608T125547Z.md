# Runtime Preflight Metadata Basename Lookup Current Base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T125547Z`
Base: `5a97c92b05af687aad8c076c25c38a1771bc03ed`

## Source Truth

Pinned upstream `sddai/markerPDF` `convert.py` builds worker task args as:

- `files = [os.path.join(in_folder, f) for f in os.listdir(in_folder)]`
- `metadata = json.load(open(metadata_file))` when `--metadata_file` is present
- `task_args = [(f, out_folder, metadata.get(os.path.basename(f)), args.min_length) for f in files_to_convert]`

This means metadata JSON keys that are absolute paths, relative paths, or
Windows-style paths remain valid JSON object keys, but they do not match a
queued file unless the key exactly equals `os.path.basename(f)`.

Upstream reference: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Patch

- Added `metadata_basename_lookup_review` to `BatchConverter::runtimeMainPreflightPlan()`.
- The review records path-like metadata keys, their basename interpretations,
  selected basename matches, exact basename keys that override path-like
  decoys, and selected files that remain missing because only a path-like key
  exists.
- Worker task args remain basename-only: path-like metadata values are excluded
  from `task_args` unless an exact basename key is present.
- Added a WordPress smoke for attachment metadata JSON containing absolute,
  relative, and Windows-style path-shaped keys.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataBasenameLookupBoundaryCurrentBaseTest.php
FAIL ... Undefined array key "metadata_basename_lookup_review"
1 test files, 3 assertions, 2 failures
```

After source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataBasenameLookupBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps path-shaped metadata file keys out of task args because convert.py looks up basenames
PASS applies the same basename-only metadata review to direct runtime metadata arguments
1 test files, 37 assertions, 0 failures
```

Adjacent runtime family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataBasenameLookupBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeMetadataTaskArgBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightDuplicateMetadataCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
4 test files, 1363 assertions, 0 failures
```

Smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-metadata-basename-lookup-currentbase.php
basename_only_lookup_preserved=true
path_like_metadata_values_excluded_from_task_args=true
task_args_count=3
executes_python_or_models=false
executes_multiprocessing=false
executes_external_pdf_tools=false
```

## Non-Overlap

This slice does not change live OCR, Surya/Texify/Torch, GPU/model execution,
Streamlit/FastAPI workers, exact upstream model benchmark parity, xref repair,
fonts, CMaps, outlines, annotations, forms, page geometry, image filters, or
named destination handling. It only exposes and tests the runtime
`metadata.get(os.path.basename(f))` task-argument boundary.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
runtime preflight planner and JSON metadata loader. No external PDF tools,
Python model workers, multiprocessing, OCR, raster rendering, or network
services are required.

## Next

Continue with non-overlapping native markerPDF behavior: xref repair,
object-stream/filter metadata, fonts, CMaps, annotations, forms, page geometry,
image/filter metadata, or supplied-boundary table/equation handoffs.
