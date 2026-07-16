# markerPDF runtime argparse response-file boundary current base

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T234326Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T234326Z`
Base accepted HEAD: `90d4f94aa4c197664f86617e6dc525760b7286d3`

## Source Truth

Pinned upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses plain `argparse.ArgumentParser(...)` in both runtime entrypoints:

- `convert.py::main` constructs `argparse.ArgumentParser(description="Convert multiple pdfs to markdown.")`, adds `in_folder`, `out_folder`, and batch options, then calls `parser.parse_args()`.
- `convert_single.py::main` constructs `argparse.ArgumentParser()`, adds `filename`, `output`, and single-document options, then calls `parser.parse_args()` before splitting `--langs`.

Neither parser configures `fromfile_prefix_chars`, so Python argparse does not expand `@args.txt` response files. `@`-prefixed values remain literal argv tokens until later runtime code interprets them as paths or option values.

## Native PHP Change

- `BatchConverter::runtimeMainArgumentPreflightPlan()` now records an `argfile_boundary` for batch `convert.py` argv tokens, including `fromfile_prefix_chars=null`, `response_file_expansion_enabled=false`, literal `@` token preservation, and no `@` file reads before `parse_args`.
- `SingleDocumentConverter::runtimeArgumentPreflightPlan()` records the same boundary for `convert_single.py`, including literal `@` filenames and `--langs @wp-langs.txt` splitting to `["@wp-langs.txt"]`.
- Parser plans now expose `fromfile_prefix_chars`, `expands_response_files`, and `at_file_tokens_are_literals` so WordPress preflight can distinguish literal upload paths from unsupported response-file expansion.
- Error plans preserve the same no-read boundary, so a single `@wp-batch-args.txt` token still fails as missing `out_folder` instead of being opened as an argument file.

This is review-only PHP behavior. It does not run Python, Torch, Surya, Texify, OCR/model inference, multiprocessing workers, Streamlit/FastAPI, pdfium rendering, or external PDF tools.

## Evidence

Focused runtime response-file test:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseResponseFileBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps argparse at-file tokens literal before runtime preflight
1 test files, 58 assertions, 0 failures
```

Adjacent runtime argparse family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseResponseFileBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 189 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-argparse-response-file-boundary-currentbase.php
```

Passed and emitted `batch_expands_response_files=false`, `batch_in_folder_literal="@wp-batch-args.txt"`, `batch_metadata_file_literal="@wp-metadata.json"`, `single_filename_literal="@wp-single-upload.pdf"`, `single_langs_literal="@wp-langs.txt"`, `single_parsed_langs=["@wp-langs.txt"]`, `reads_at_files_before_parse_args=false`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted runtime argparse default/error parsing, repeated option last-write wins, output-folder conflicts, input listing, metadata JSON loading/shape/duplicate/scalar handling, relative metadata paths, symlink task identity, pool creation/result drain/worker initialization, process_single_pdf return-value review, model handoff, server runtime artifacts, or native searchable-PDF parser behavior such as fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, image filters, page geometry, outlines, tables, or equations.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP argv normalization and runtime preflight review surfaces. GPU/model execution remains intentionally out of scope under the markerPDF no-GPU directive.
