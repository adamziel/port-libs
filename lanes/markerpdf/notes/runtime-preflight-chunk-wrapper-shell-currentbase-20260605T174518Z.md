# markerPDF runtime preflight chunk wrapper shell boundary current base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T174518Z`

Base: `165d00972e222ec74a0a4ac65ceaafba6ceef98e`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `chunk_convert.py` parses only `in_folder` and `out_folder`, resolves `chunk_convert.sh` with `pkg_resources.resource_filename(__name__, "chunk_convert.sh")`, then constructs `cmd = f"{script_path} {args.in_folder} {args.out_folder}"` and runs `subprocess.run(cmd, shell=True, check=True)`.
- Upstream `chunk_convert.sh` performs `NUM_DEVICES`, `NUM_WORKERS`, input-folder, and output-folder validation after that wrapper subprocess launches, then builds per-device `marker` commands.
- Primary source URLs inspected:
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.py`
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.sh`

## Implementation

- Added `ChunkConversionPlanner::wrapperRuntimePreflightPlan()` as a review-only native boundary for the Python wrapper.
- The plan records argparse success/errors, resource-script lookup, the raw shell command string, `shell=True`, `check=True`, lack of argv-list execution, lack of argument escaping/quoting, and path whitespace/metacharacter hazard metadata.
- The existing `planFromEnvironment()` and `planDeviceJobs()` shell fanout planner remains unchanged.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php
FAIL records chunk_convert.py raw shell wrapper boundary before chunk shell validation
Call to undefined method PortLibs\MarkerPDF\ChunkConversionPlanner::wrapperRuntimePreflightPlan()
FAIL blocks chunk_convert.py wrapper argparse errors before resource lookup or subprocess launch
Call to undefined method PortLibs\MarkerPDF\ChunkConversionPlanner::wrapperRuntimePreflightPlan()
1 test files, 20 assertions, 2 failures
```

Focused passing run after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php
1 test files, 62 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-chunk-convert-wrapper-preflight-currentbase.php
```

The smoke emits `raw_shell_command_path_hazard=true`, `shell_true=true`, `argument_escaping_applied=false`, `env_validation_before_subprocess=false`, `executes_subprocess=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the existing chunk_convert.sh per-device fanout planner, convert.py runtime main preflight, metadata JSON boundaries, process_single_pdf skip gates, model-handoff planning, xref/parser/font/image/security/form/annotation/table slices, or any GPU/model execution. The bounded behavior is only the top-level `chunk_convert.py` wrapper command-construction boundary before shell validation.

## Dependency Closure

No new support component is needed. This reuses lane-local runtime planning and intentionally does not launch shell commands, Python, Torch, pdftext, pypdfium, model workers, or external PDF tools under the current no-GPU markerPDF scope.
