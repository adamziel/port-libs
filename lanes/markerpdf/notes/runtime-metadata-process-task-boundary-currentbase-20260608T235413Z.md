# markerPDF runtime metadata process-task boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T235413Z`
Session: `port-dev-markerpdf-runtime-preflight-20260608T235413Z`
Base accepted HEAD: `d882dae9d858147bc44d510727ef5cac23951c50`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` builds task tuples with `metadata.get(os.path.basename(f))` and passes that value through `process_single_pdf` to `marker/convert.py::convert_single_pdf`.

`convert_single_pdf` only evaluates `metadata.get("languages", langs)` when the metadata value is truthy. Therefore truthy non-mapping per-file metadata, such as a string or list, fails inside conversion and is caught by `process_single_pdf`; falsy non-mapping metadata, such as `0`, skips the lookup and may convert normally.

Primary sources inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`

Under the current no-GPU markerPDF lane rule, this slice records the runtime boundary without executing Python, Torch, OCR, Surya, Texify, multiprocessing workers, pdftext, pypdfium, or external PDF tools.

## Behavior

`BatchConverter::processTask()` now preserves mixed metadata values through the executable `processFile()` boundary instead of failing early on PHP type narrowing:

- truthy string metadata reaches the converter, raises the upstream-shaped `'str' object has no attribute 'get'` failure, returns the process-single-PDF exception boundary, and writes no Markdown;
- truthy list metadata does the same for the upstream-shaped `'list' object has no attribute 'get'` failure;
- falsy integer `0` metadata records the falsy non-dict boundary and still converts;
- dictionary metadata still converts with no non-mapping boundary.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataProcessTaskBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps process_single_pdf task metadata values mixed until converter-side metadata lookup
PortLibs\MarkerPDF\BatchConverter::processFile(): Argument #3 ($metadata) must be of type ?array, string given, called in .../lanes/markerpdf/src/BatchConverter.php on line 124
1 test files, 0 assertions, 1 failures
```

## Verification

Focused slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataProcessTaskBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps process_single_pdf task metadata values mixed until converter-side metadata lookup
1 test files, 26 assertions, 0 failures
```

Adjacent runtime metadata/preflight family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataProcessTaskBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeMetadataTaskArgBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 1316 assertions, 0 failures
```

Core BatchConverter regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 111 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-metadata-process-task-boundary-currentbase.php
```

The smoke exits 0 and emits `truthy_string_status="error"`, `truthy_string_error_boundary="conversion-exception-print-return-none"`, `truthy_list_status="error"`, `falsy_zero_status="converted"`, `dict_status="converted"`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene for this handoff:

```text
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimeMetadataProcessTaskBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-metadata-process-task-boundary-currentbase.php
php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All exited 0 during this handoff.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused behavior PASS cases move `3602 -> 3603`.
- WordPress scenarios move `2906 -> 2907`.
- No manifest mapped-coverage row changed in this slice.

## Non-Overlap

This does not repeat runtime argparse, input/output path creation, metadata file load/shape/duplicate-key handling, basename metadata lookup, task-arg tuple review, process-single-PDF preflight summaries, markdown-exists boundaries, min-length gates, model handoff, pool result drain, worker cleanup, model-list arity, or native PDF parser/font/xref/security/image/table behavior. The bounded behavior is specifically executable `processTask()` preservation of mixed per-file metadata values until converter-side `metadata.get` handling.

## Dependency Closure

No new support component is needed. This reuses the native PHP `BatchConverter`, `OutputWriter`, focused `TestRunner`, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model loading, GPU/MPS execution, pypdfium/PDFium rendering, pdftext runtime extraction, multiprocessing workers, Streamlit/FastAPI workers, and exact upstream benchmark parity remain intentionally out of scope under the no-GPU markerPDF lane rule.
