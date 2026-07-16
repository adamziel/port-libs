# Runtime Argparse Equals-Token Boundary Current Base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260607T160535Z`

Accepted base: `d7345d84e10d05caceef0325f9e5a6fc93289f0b`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses default Python `argparse.ArgumentParser` in both `convert.py::main` and `convert_single.py::main`. Default argparse keeps `allow_abbrev=true`, accepts unique long-option prefixes including equals-form tokens such as `--metadata=...` or `--max=...`, and reports the original raw token for unknown or ambiguous equals-form options such as `--gpu=1` and `--m=3`.

## Change

- `BatchConverter::runtimeMainArgumentPreflightPlan()` now preserves the raw CLI token in unknown/ambiguous equals-form argparse errors while still resolving successful abbreviations to canonical options before value parsing.
- `SingleDocumentConverter::runtimeArgumentPreflightPlan()` applies the same raw-token error boundary for `convert_single.py`.
- Added `MarkerRuntimeArgparseEqualsTokenBoundaryCurrentBaseTest.php` for the batch and single-document runtime surfaces.
- Updated `wordpress-marker-runtime-argparse-boundary-currentbase.php` to expose the WordPress review fields for unknown equals-token errors, ambiguous equals-token errors, and successful equals-form abbreviations.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseEqualsTokenBoundaryCurrentBaseTest.php
FAIL preserves convert.py equals-token argparse errors while allowing equals abbreviations
Expected: '--gpu=1'
Actual: '--gpu'
FAIL preserves convert_single.py equals-token argparse errors while allowing equals abbreviations
Expected: '--gpu=1'
Actual: '--gpu'
1 test files, 4 assertions, 2 failures
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseEqualsTokenBoundaryCurrentBaseTest.php
1 test files, 38 assertions, 0 failures
```

Adjacent argparse family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseResponseFileBoundaryCurrentBaseTest.php
3 test files, 216 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-argparse-boundary-currentbase.php
```

Result: emits `unknown_equals_option_error="unrecognized arguments: --gpu=1"`, `ambiguous_equals_option_error` containing `ambiguous option: --m=3`, `metadata_equals_abbrev_value="wordpress-metadata.json"`, `workers_equals_abbrev_value=3`, `num_chunks_equals_abbrev_value=2`, `single_unknown_equals_option_error="unrecognized arguments: --gpu=1"`, `single_max_equals_abbrev_value=3`, `single_start_equals_abbrev_value=2`, `single_batch_equals_abbrev_value=4`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted runtime import-order setup, numeric gate handling, repeated options, response-file literal handling, input/output path normalization, output-folder conflicts, metadata loading/shape/value/duplicate-key behavior, chunk slicing, empty task queues, invalid worker Pool creation, pool result drain, pool cleanup, MPS model handoff, worker init, share-memory error boundaries, parser/xref/font/image/security/form/table behavior, or any GPU/model/OCR execution. The bounded behavior is only default argparse raw-token preservation for equals-form unknown/ambiguous options plus equals-form abbreviation admission.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP runtime argument planners and WordPress smoke renderer. Live Python, Torch, Surya/Texify/OCR models, pypdfium/PDFium, multiprocessing, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.

## Final Verification

```text
php -l lanes/markerpdf/src/BatchConverter.php
No syntax errors detected in lanes/markerpdf/src/BatchConverter.php

php -l lanes/markerpdf/src/SingleDocumentConverter.php
No syntax errors detected in lanes/markerpdf/src/SingleDocumentConverter.php

php -l lanes/markerpdf/tests/MarkerRuntimeArgparseEqualsTokenBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/MarkerRuntimeArgparseEqualsTokenBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-marker-runtime-argparse-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-argparse-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "ok\n";'
ok

git diff --check -- lanes/markerpdf
clean
```

Root harness: not run - isolated micro-slice.

## Next Task

Continue with non-overlapping native markerPDF behavior around searchable-PDF parser fidelity, stream filters, fonts/CMaps, xref repair, metadata, annotations/forms/security preflight, image/filter metadata, page geometry, or supplied-boundary table/equation handoffs.
