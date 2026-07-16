# markerPDF runtime preflight option-value boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T051058Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T051058Z`
Base accepted HEAD: `27a0520cbee0c34db64918d6587918843c9b97db`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` builds an `argparse.ArgumentParser` with `--metadata_file` as a string option, then parses arguments before abspath, listdir, output creation, metadata loading, model handoff, task tuple construction, or multiprocessing.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- Python `argparse` treats a separate option-looking token such as `--metadata_file -x` as a missing value and exits with code 2 before runtime filesystem/model work. The equals form `--metadata_file=-x` remains a valid string value, and negative-number-looking separate values such as `-1` remain valid.

## Change

- `BatchConverter::runtimeMainArgumentPreflightPlan()` now applies a native argparse option-value boundary for separate option-looking values instead of only rejecting `--`-prefixed values.
- Negative numeric-looking values still pass through for upstream-compatible `--max -1` and `--metadata_file -1` behavior.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits the WordPress review fields `short_option_metadata_arg_success=false`, `short_option_metadata_arg_error`, `short_option_metadata_arg_blocks_runtime=true`, `short_option_metadata_arg_filesystem_touched=false`, `equals_short_metadata_arg_value=-x`, and `negative_numeric_metadata_arg_value=-1`.

## Red-First Evidence

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed before the source edit:

```text
FAIL matches argparse metadata_file option-looking value boundaries before runtime preflight
Values are not identical
Expected: false
Actual: true
1 test files, 1143 assertions, 1 failures
```

## Focused Verification

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed after the source edit:

```text
1 test files, 1163 assertions, 0 failures
```

Adjacent runtime/batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/BatchConverterTest.php`

```text
2 test files, 1274 assertions, 0 failures
```

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted:

```text
short_option_metadata_arg_success=false
short_option_metadata_arg_error="argument --metadata_file: expected one argument"
short_option_metadata_arg_blocks_runtime=true
short_option_metadata_arg_filesystem_touched=false
equals_short_metadata_arg_value="-x"
negative_numeric_metadata_arg_value="-1"
executes_python_or_models=false
executes_multiprocessing=false
executes_external_pdf_tools=false
```

Changed PHP syntax checks:

```text
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
```

All reported no syntax errors.

`php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`

Passed.

`git diff --check -- lanes/markerpdf`

Passed with no output.

## Non-Overlap

This does not repeat accepted runtime numeric truthiness, empty metadata-file truthiness, relative metadata-file cwd resolution, output-folder creation ordering, listdir/file filtering, symlink task identity, pool result draining, worker cleanup, model share-memory handoff, process_single_pdf min-length gates, or native PDF parser/font/xref/security/image/table/form/outline behavior. The bounded behavior is only the `argparse` missing-value boundary for separate short-option-looking `--metadata_file` values before runtime preflight.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP runtime argument preflight planner and WordPress smoke. Full upstream runtime execution remains out of scope under the no-GPU markerPDF rule because it requires Python, Torch multiprocessing, Surya/Texify/tabled models, `pdftext`, `pypdfium2`/PDFium, OCR/raster helpers, and Streamlit/FastAPI runtime paths.
