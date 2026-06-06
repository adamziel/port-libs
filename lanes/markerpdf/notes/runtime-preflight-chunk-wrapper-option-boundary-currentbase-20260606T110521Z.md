# markerPDF runtime preflight: chunk wrapper option-looking argv boundary

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T110521Z`  
Base accepted HEAD: `6e1c2ccf2c17dca7dfe3543ea597a270de5896cf`

## Source truth

- Upstream `chunk_convert.py` at `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` defines only two argparse positionals, `in_folder` and `out_folder`, then resolves `chunk_convert.sh` and runs one raw shell string via `subprocess.run(cmd, shell=True, check=True)`.
- Upstream `chunk_convert.sh` validates `NUM_DEVICES`, `NUM_WORKERS`, `$1`, and `$2` after the Python wrapper has already parsed argv and launched the shell script.
- Local argparse probe:
  - `["--wp-source", "/wp/output"]` exits with code `2` before resource lookup because the first token is option-looking and only `/wp/output` remains as a positional, so `out_folder` is missing.
  - `["--", "--wp-source", "/wp/output"]` succeeds and sets `in_folder="--wp-source"` and `out_folder="/wp/output"`.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.sh

## Implementation

- `ChunkConversionPlanner::wrapperRuntimePreflightPlan()` now models argparse option-token handling before the wrapper reaches `pkg_resources.resource_filename()` or `subprocess.run()`.
- Option-looking folder paths before `--` are treated as parser failures and block script lookup/subprocess launch.
- The `--` separator is preserved as wrapper argv metadata but removed from parsed positionals, matching upstream argparse behavior before the raw shell command is constructed.
- The existing WordPress wrapper smoke now checks both the fail-closed option-looking input path and the separator-allowed path without executing shell, Python, model workers, or external PDF tools.

## Evidence

Red-side probe before source edit:

```text
php -r 'require "tools/bootstrap.php"; $p = new PortLibs\MarkerPDF\ChunkConversionPlanner(); $plan = $p->wrapperRuntimePreflightPlan(["--wp-source", "/wp/output"], "/opt/marker/chunk_convert.sh"); echo "parse_args_success=" . ($plan["parse_args"]["parse_args_success"] ? "true" : "false") . "\n"; echo "command=" . ($plan["subprocess"]["command"] ?? "null") . "\n";'
parse_args_success=true
command=/opt/marker/chunk_convert.sh --wp-source /wp/output
```

Upstream argparse probe:

```text
["--wp-source", "/wp/output"] EXIT 2
["--", "--wp-source", "/wp/output"] OK --wp-source /wp/output
```

Focused PHP verification after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records chunk_convert.py raw shell wrapper boundary before chunk shell validation
PASS blocks chunk_convert.py wrapper argparse errors before resource lookup or subprocess launch
PASS requires argparse separator for chunk_convert.py wrapper option-looking folder paths
PASS plans chunk_convert.sh marker jobs across CUDA devices
PASS omits optional chunk_convert.sh flags when environment variables are empty
PASS passes non-empty chunk_convert.sh MIN_LENGTH values through before marker argparse
PASS mirrors chunk_convert.sh validation for required environment and folders
PASS produces WordPress queue shards without executing marker subprocesses

1 test files, 105 assertions, 0 failures
```

Lint and smoke:

```text
php -l lanes/markerpdf/src/ChunkConversionPlanner.php
No syntax errors detected in lanes/markerpdf/src/ChunkConversionPlanner.php

php -l lanes/markerpdf/tests/ChunkConversionPlannerTest.php
No syntax errors detected in lanes/markerpdf/tests/ChunkConversionPlannerTest.php

php -l lanes/markerpdf/examples/wordpress-chunk-convert-wrapper-preflight-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-chunk-convert-wrapper-preflight-currentbase.php

php lanes/markerpdf/examples/wordpress-chunk-convert-wrapper-preflight-currentbase.php
option_like_path_without_separator_blocks=true
option_like_path_with_separator_parse_args_success=true
executes_subprocess=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Dependency closure

No new support component is needed. This slice reuses native PHP runtime-preflight planning and `argparse` source behavior only; GPU/OCR/model execution, Python subprocess execution, and external PDF tools remain intentionally out of scope for the current no-GPU markerPDF lane.

## Next

Continue non-overlapping native markerPDF work around searchable-PDF parser fidelity: fonts/CMaps, stream filters, xref repair, metadata, outlines, annotations/forms, page geometry, image/filter metadata, security preflight, and supplied-boundary table/equation handoffs.
