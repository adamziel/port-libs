# Runtime Preflight Duplicate Metadata Boundary

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T121204Z`
Base: `fd0309d09309485f1a1f26b7fa8979ce63d82aca`

## Behavior

- Added a native no-execution review boundary for `convert.py` metadata files with duplicate top-level basename keys.
- Source-truth behavior: Python `json.load()` accepts duplicate object keys and keeps the last value. The runtime plan now records top-level metadata key order, duplicate basename counts, selected duplicate filenames, and the last-value-wins policy before task args, model handoff, multiprocessing, or external PDF tools.
- WordPress impact: duplicate upload metadata rows for the same PDF basename are reviewable, selected task args receive the current/last metadata value, and stale duplicate metadata payloads are excluded from the preflight output.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightDuplicateMetadataCurrentBaseTest.php`
  - `1 test files, 36 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`
  - `1 test files, 847 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-duplicate-metadata-currentbase.php`
  - emits `duplicate_key_policy=python-json-load-last-value-wins`, `duplicate_keys=[editorial.pdf]`, `editorial_metadata_title=Current Editorial Import`, `stale_metadata_excluded=true`, and no Python/model/external-tool execution.

## Dependency Closure

No new support component is needed. This reuses the existing native runtime preflight planner and PHP JSON decoder; GPU/OCR/model execution remains intentionally out of scope for this lane.
