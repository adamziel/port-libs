# Runtime Preflight Metadata File Load Boundary

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T103005Z`

Accepted base: `17084c137d0018e6cf17e49bcac91c3e1cb47745`

## Source Truth

Upstream markerPDF `convert.py::main` resolves `args.metadata_file` with `os.path.abspath()` after input listing, output folder creation, and chunk selection, then executes `json.load(open(args.metadata_file))` before `torch.multiprocessing.set_start_method("spawn")`, model handoff, conversion summary, task tuple construction, and `Pool.imap(process_single_pdf, task_args)`.

This no-GPU native PHP lane records that runtime boundary for WordPress import review without opening Python, Torch, model workers, multiprocessing, or external PDF tools.

## Implementation

`BatchConverter::runtimeMainPreflightPlan()` now treats metadata file open/read failures as structured runtime preflight metadata:

- `metadata.metadata_load_reached=true`
- `metadata.metadata_load_success=false`
- `metadata.metadata_error_boundary=metadata-file-load-failed`
- `metadata.metadata_error_class=FileNotFoundError`, `IsADirectoryError`, `PermissionError`, or `OSError` depending on the metadata path
- spawn start-method, model handoff, conversion summary, task args, and worker-pool launch are blocked before any model/runtime execution

Malformed JSON remains the existing `metadata-file-json-load-failed` boundary, and list/scalar JSON still reaches the later `metadata.get` task-args boundary. Missing input folders and invalid chunk counts still fail before metadata loading.

## Evidence

Red-first probe before the fix:

```text
InvalidArgumentException: Batch metadata file is not readable: /tmp/.../missing.json
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 825 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
missing_metadata_file_error_boundary=metadata-file-load-failed
missing_metadata_file_error_class=FileNotFoundError
missing_metadata_file_blocks_spawn=true
missing_metadata_file_blocks_model_handoff=true
missing_metadata_file_task_args_count=0
missing_metadata_file_summary_reached=false
```

Additional checks:

```text
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR);'
git diff --check -- lanes/markerpdf
```

All passed.

## Non-Overlap

This does not repeat accepted worker cleanup, text-length exception, output-folder conflict, malformed metadata JSON, list-shaped metadata, per-file scalar metadata, relative metadata path, file-list order, worker-count Pool creation, process_single_pdf return-value, post-conversion, save_markdown, inline image, OCR/model, parser, xref, CMap, font, image/filter, annotation, form, security, or table/equation handoff slices.

The bounded behavior is only missing/unreadable `metadata_file` load failure handling in the runtime preflight plan after chunk selection and before spawn/model/task/pool stages.

## Dependency Closure

No new support component is needed. This reuses native PHP runtime planning, filesystem path classification, JSON metadata review, and existing WordPress runtime smoke paths. Live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, and exact upstream benchmark/model parity remain intentionally out of scope for the no-GPU markerPDF lane.
