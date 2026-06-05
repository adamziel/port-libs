# markerPDF runtime preflight chunk MIN_LENGTH shell boundary

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T224310Z`

Base: `ee26489bdb651a4b12ce158e3b8859ff31df6834`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `chunk_convert.sh` appends `--min_length $MIN_LENGTH` when the environment value is non-empty. It does not validate positivity or integer shape in the shell script.
- The invoked `marker` command reaches `convert.py`, whose argparse definition parses `--min_length` with `type=int`. Therefore `MIN_LENGTH=0` and negative integers are passed through to `convert.py`, while non-integer values fail at argparse after the shell command boundary.
- Primary source URLs inspected:
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.sh`
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Implementation

- `ChunkConversionPlanner::planFromEnvironment()` now reads `MIN_LENGTH` as an optional raw non-empty shell string instead of requiring a positive PHP integer.
- `ChunkConversionPlanner::planDeviceJobs()` accepts `int|string|null` for the optional min-length flag, preserves `0`, negative, and non-integer strings in queue `argv`, and records that integer validation is deferred to `convert.py argparse --min_length type=int`.
- The WordPress chunk queue smoke now uses `MIN_LENGTH=0` and emits the optional-flag review metadata while still refusing to execute shell subprocesses, Python, models, or external PDF tools.

## Evidence

Red-first focused check before the source edit:

```text
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\MarkerPDF\ChunkConversionPlanner(); try { $p->planFromEnvironment("/in","/out", ["NUM_DEVICES"=>"1", "NUM_WORKERS"=>"2", "MIN_LENGTH"=>"0"]); echo "unexpected-pass\n"; } catch (Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }'
InvalidArgumentException: MIN_LENGTH must be at least one.
```

Focused test after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php
1 test files, 77 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-chunk-convert-queue.php
```

The smoke emits `min_length=0`, `min_length_included=true`, `min_length_integer_validation_deferred_to_marker_argparse=true`, `executes_subprocess=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted `chunk_convert.py` raw shell wrapper boundary, chunk device sharding basics, convert.py runtime main preflight, metadata JSON loading boundaries, process_single_pdf skip gates, model-handoff planning, server/upload runtime behavior, xref/parser/font/image/security/form/annotation/table slices, or any GPU/model execution. The bounded behavior is only the `chunk_convert.sh` optional `MIN_LENGTH` shell-condition boundary before marker/convert.py argparse validation.

## Dependency Closure

No new support component is needed. This reuses lane-local runtime queue planning and intentionally does not launch shell commands, Python, Torch, pdftext, pypdfium, model workers, or external PDF tools under the current no-GPU markerPDF scope.
