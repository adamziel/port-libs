# markerPDF runtime metadata_file relative path boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T075735Z`

Base accepted HEAD: `6bf66e4eb549e893548d86a7960f7cf19c5eeeba`

## Source truth

Pinned upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` has `convert.py::main` parse CLI args, normalize `in_folder` and `out_folder` with `os.path.abspath`, list input files with `os.listdir` plus `os.path.isfile`, create `out_folder`, chunk files, then for a truthy `args.metadata_file` runs `metadata_file = os.path.abspath(args.metadata_file)` before `open(metadata_file)` and `json.load(f)`.

That means a relative `--metadata_file ./runtime/../relative-metadata.json` is resolved against the current process directory, not against the WordPress upload/input folder or Marker output folder. Regular files in the input folder with the same basename still remain convert.py task candidates because extension filtering does not happen before task tuple construction.

## Native PHP change

- `BatchConverter::absolutePath()` now mirrors Python `os.path.abspath` for non-existing paths by normalizing dot segments without requiring `realpath()`.
- `BatchConverter::runtimeMainPreflightPlan()` and the safe error-boundary wrapper now include metadata path provenance:
  - original `metadata_file_input`;
  - `metadata_file_abspath_call`;
  - `metadata_file_abspath_order`;
  - `metadata_file_abspath_base`;
  - process cwd;
  - explicit false markers for input-folder and output-folder relative resolution;
  - input/output candidate paths and existence flags for review.
- The new focused test proves the process-cwd metadata file is loaded while input/output decoy metadata files are not used as metadata. The input-folder decoy remains selected as a regular file candidate, preserving upstream `os.listdir` behavior.

## Focused evidence

Before patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
1 test files, 733 assertions, 0 failures
```

Red-first probe with the new case failed at 751 assertions because the expected missing-metadata list initially forgot that the input-folder decoy metadata file remains a regular convert.py task candidate. The final assertions preserve that boundary.

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
1 test files, 761 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
```

The smoke exits 0 and emits `relative_metadata_loaded_from_process_cwd=true`, `relative_metadata_ignored_input_output_decoys=true`, `relative_metadata_abspath_base=process_cwd`, selected filenames including `relative-metadata.json`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted runtime input-folder errors, output-folder creation conflicts, metadata JSON decode errors, metadata shape/value boundaries, numeric gates, spawn-start failures, pool creation, task sidecars, process return values, post-conversion errors, save-markdown exceptions, single-document preflight, or DCTDecode/native PDF parser slices. The new boundary is specifically relative `--metadata_file` path normalization and provenance before `json.load`.

## Dependency closure

No new support component is needed. This reuses the native `BatchConverter` runtime preflight model, PHP filesystem checks, metadata JSON loader, task-argument planner, and WordPress smoke path. Python, Torch, pdftext, pypdfium/PDFium, Surya/Texify models, multiprocessing workers, Streamlit/FastAPI, OCR/model execution, and external PDF tools were not executed.
