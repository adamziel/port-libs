# Runtime Preflight Same Input/Output Folder Current Base

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T182411Z`
Session: `port-dev-markerpdf-runtime-preflight-20260608T182411Z`
Base accepted HEAD: `0ca1726be1212764e1653162e91e283c2a5975b7`

## Source Truth

Upstream `sddai/markerPDF` `convert.py` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` lists input files with
`os.listdir(in_folder)` before `os.makedirs(out_folder, exist_ok=True)`, then
builds task tuples with `(f, out_folder, metadata.get(os.path.basename(f)),
args.min_length)`.

Reference:
`https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Change

`BatchConverter::runtimeMainPreflightPlan()` now records the same input/output
folder boundary explicitly:

- same-folder review reached
- listdir-before-makedirs ordering
- same-folder `os.makedirs(..., exist_ok=True)` directory no-op
- no same-folder runtime guard
- task args keep `out_folder` equal to `in_folder`
- existing regular input files remain task candidates
- generated output artifact directories are filtered only by `isfile`
- no Python, models, multiprocessing, OCR, raster rendering, or external PDF
  tools are executed

This is a native runtime preflight boundary only. It does not launch upstream
`convert.py`, Torch, Surya, Texify, pypdfium, PIL, Streamlit/FastAPI workers, or
model/OCR execution.

## Evidence

Red-first focused check before the implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeInputOutputSameFolderBoundaryCurrentBaseTest.php`

Result: failed after 1 assertion because
`paths.path_resolution.input_output_same_folder_review` was absent.

Focused passing check after the implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeInputOutputSameFolderBoundaryCurrentBaseTest.php`

Result: `1 test files, 41 assertions, 0 failures`.

Adjacent runtime preflight family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeInputOutputSameFolderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeNestedOutputBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeOutputSymlinkBoundaryCurrentBaseTest.php`

Result: `4 test files, 1410 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-input-output-same-folder-currentbase.php`

Result: exits 0 with `input_output_same_folder=true`,
`listdir_runs_before_makedirs=true`, `makedirs_exist_ok_directory_noop=true`,
`no_same_folder_runtime_guard=true`,
`task_args_out_folder_equals_input_folder=true`, existing markdown skip
preserved, same-folder output artifact directories skipped as non-files, and
all execution flags false.

Syntax and metadata checks:

- `php -l lanes/markerpdf/src/BatchConverter.php` - no syntax errors
- `php -l lanes/markerpdf/tests/MarkerRuntimeInputOutputSameFolderBoundaryCurrentBaseTest.php` - no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-input-output-same-folder-currentbase.php` - no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'` - markerpdf json ok
- `git diff --check -- lanes/markerpdf` - clean

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP
filesystem, output-writer, file-list, chunk, task-argument, and
`process_single_pdf` preflight planning. The GPU/model/OCR runtime gap remains
an intentional no-GPU scope exclusion, not a dependency blocker.

## Non-Overlap

This slice does not cover nested output folders, output symlink boundaries,
output file-conflict boundaries, missing input folder checks, chunk/max
admission, metadata loading, worker-count clamping, model handoff, pool launch,
CMap behavior, xref repair, stream filters, annotations, forms, page geometry,
or OCR/model parity. It only covers same input/output folder runtime preflight
on the current accepted base.

## Next

Continue with non-overlapping native markerPDF behavior around searchable-PDF
parser fidelity, including fonts, CMaps, stream filters, xref repair, metadata,
outlines, annotations, forms, page geometry, image/filter metadata, or supplied
table/equation boundaries.
