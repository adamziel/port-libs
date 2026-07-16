# markerPDF Runtime Preflight Markdown Symlink Boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T213712Z`
Session: `port-dev-markerpdf-runtime-preflight-20260608T213712Z`
Base accepted HEAD: `ba1acddf7dda63f41a17e1f25945a52ff91962c3`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `markdown_exists(out_folder, fname)` at the start of `convert.py::process_single_pdf()` and returns Python `None` before `find_filetype`, `get_length_of_text`, `convert_single_pdf`, or `save_markdown` when it is true.
- Upstream `marker/output.py::markdown_exists()` returns `os.path.exists(get_markdown_filepath(...))`. Therefore a generated Markdown symlink to an existing file counts as already converted, while a broken generated Markdown symlink does not.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py` and `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`.
- Live Python, Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI, and external PDF tools remain intentionally out of scope.

## Patch

- `BatchConverter::processFilePreflightPlan()` now records generated Markdown symlink state for upstream `markdown_exists()` review: path is symlink, target existence/type, broken-symlink state, live-symlink skip state, and broken-symlink non-skip state.
- `BatchConverter::runtimeProcessSinglePdfPreflightReview()` carries those fields into worker review rows and filename maps, including live and broken generated-Markdown symlink filename lists.
- Added a focused current-base test covering direct worker preflight and `convert.py::main` worker-review/result-drain behavior.
- Added a WordPress smoke showing a live generated `.md` symlink skips before filetype/text/converter gates while a broken generated `.md` symlink remains ready for conversion.

## Evidence

Red-first focused run after adding the regression and before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMarkdownExistsSymlinkBoundaryCurrentBaseTest.php`

Result: `1 test files, 10 assertions, 2 failures`; missing `markdown_exists_path_is_symlink` and aggregate symlink filename fields.

Focused run after fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMarkdownExistsSymlinkBoundaryCurrentBaseTest.php`

Result: `1 test files, 70 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-markdown-symlink-boundary-currentbase.php`

Result: emitted `live_symlink_skips_before_filetype=true`, `broken_symlink_does_not_count_as_existing=true`, `ready_control_invoke_converter=true`, `worker_review_carries_symlink_filenames=true`, `live_return_boundary=markdown_exists-return-none`, `broken_return_boundary=conversion-or-empty-output-return-none`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `OutputWriter`, `BatchConverter`, runtime task preflight, filetype/text-length gate boundaries, worker-pool review, and WordPress smoke path. Full upstream runtime execution remains dependency-gated on Python, Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI, and external PDF tools.

## Non-Overlap

This does not repeat accepted runtime output-folder symlink conflicts, input broken symlinks, selected-file disappearance, metadata-file open/load/shape/value boundaries, spawn start-method failures, model handoff/share-memory, conversion-summary ordering, pool process-count creation, worker cleanup, non-PDF sidecar task preflight, numeric truthiness, negative chunk slicing, directory collision at the generated Markdown path, single-document runtime preflight, output artifact metadata, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only generated Markdown symlink semantics at the worker-side `markdown_exists()` boundary before converter/model invocation or Markdown writes.
