# markerPDF Runtime Preflight: Chunk Wrapper Resource Script Path Boundary

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T221740Z`
Base accepted HEAD: `e1f112b8ea648ea7e836cfb9bbd4f19dce3d5584`

## Source Truth

- Upstream `sddai/markerPDF` `chunk_convert.py` resolves `chunk_convert.sh` with `pkg_resources.resource_filename(...)`, builds a single command string containing the script path plus input and output folders, then calls `subprocess.run(..., shell=True, check=True)`.
- Upstream `chunk_convert.sh` performs its environment and folder checks after the Python wrapper has already launched the shell command.
- Source references:
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.py`
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.sh`

## Behavior Ported

`ChunkConversionPlanner::wrapperRuntimePreflightPlan()` now records the packaged `chunk_convert.sh` resource script path as part of the same raw shell-command boundary already used for `in_folder` and `out_folder`.

The new metadata exposes:

- `subprocess.raw_script_path_fragment`
- `shell_boundary.raw_script_path_fragment`
- `shell_boundary.resource_script_contains_shell_whitespace`
- `shell_boundary.resource_script_contains_shell_metacharacters`

This keeps the PHP port review-only. It does not execute shell, Python, CUDA, OCR, multiprocessing, models, or external PDF tools.

## Red-Side Probe

Before the source change, a script path containing whitespace and shell metacharacters was preserved in `resource_script.script_path`, but it was not counted in the raw command hazard fields:

```bash
php -r 'require "tools/bootstrap.php"; $p = new PortLibs\MarkerPDF\ChunkConversionPlanner(); $plan = $p->wrapperRuntimePreflightPlan(["/wp/uploads/source", "/wp/uploads/out"], "/opt/wp plugins/marker; touch /tmp/markerpdf-script-owned/chunk_convert.sh"); echo "script_path=" . $plan["resource_script"]["script_path"] . "\n"; echo "raw_shell_command_path_hazard=" . (($plan["shell_boundary"]["raw_shell_command_path_hazard"] ?? false) ? "true" : "false") . "\n"; echo "resource_script_contains_shell_whitespace=" . (array_key_exists("resource_script_contains_shell_whitespace", $plan["shell_boundary"] ?? []) ? "present" : "missing") . "\n";'
```

Observed before edit:

```text
script_path=/opt/wp plugins/marker; touch /tmp/markerpdf-script-owned/chunk_convert.sh
raw_shell_command_path_hazard=false
resource_script_contains_shell_whitespace=missing
```

## Verification

```bash
php -l lanes/markerpdf/src/ChunkConversionPlanner.php
php -l lanes/markerpdf/tests/MarkerRuntimeChunkWrapperResourcePathBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-chunk-convert-wrapper-preflight-currentbase.php
```

Result: no syntax errors detected in all changed PHP files.

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeChunkWrapperResourcePathBoundaryCurrentBaseTest.php
```

Result: `1 test files, 29 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeChunkWrapperResourcePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php lanes/markerpdf/tests/MarkerRuntimeChunkShellOrchestrationCurrentBaseTest.php
```

Result: `3 test files, 175 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-chunk-convert-wrapper-preflight-currentbase.php
```

Result: WordPress smoke reports `plugin_script_path_contains_shell_whitespace=true`, `plugin_script_path_raw_shell_hazard=true`, `shell_true=true`, `argument_escaping_applied=false`, `executes_subprocess=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice avoids the accepted runtime preflight work for chunk wrapper option-looking folders, chunk shell trap/background orchestration, chunk shell `MIN_LENGTH`, file-list admission, empty queues, output parent paths, and single-document conversion preflights. The new boundary is specifically the resolved packaged script path that upstream places before the input/output folders in the `shell=True` command string.

## Dependency Closure

No new support component is needed. The behavior is a native PHP review/preflight metadata extension over the existing `ChunkConversionPlanner`; it reuses the existing runtime planner test harness and WordPress smoke path.
