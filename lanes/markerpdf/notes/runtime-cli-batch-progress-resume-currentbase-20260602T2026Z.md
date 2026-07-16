# markerPDF Runtime CLI Batch Progress Resume Current Base

Session: `port-dev-markerpdf-runtime48-20260602T2026Z`

Micro-slice: `runtime-cli-batch-progress-resume-currentbase`

Accepted base: `2bf77cd5f648f9f608014de847ea7b020b711784`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py` builds `(filepath, out_folder, basename metadata, min_length)` task tuples, skips already-written outputs through `marker.output::markdown_exists`, and wraps `pool.imap(process_single_pdf, task_args)` in `tqdm` with `desc="Processing PDFs"` and `unit="pdf"`.
- Upstream `chunk_convert.py` / `chunk_convert.sh` launch `marker` per device with chunk arguments; this slice does not repeat device sharding. It records the per-batch resume/progress boundary that a WordPress queue can inspect before or during native processing.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/chunk_convert.sh`

## Patch

- `BatchConverter::batchProgressResumePlan()` now emits a non-executing batch progress plan with upstream-style `tqdm` iterator metadata, total/initial-completed/pending counts, percent completion, and markdown-exists resume statuses by filename.
- `BatchConverter::processFolder()` now accepts an optional progress callback and emits per-file progress events after `process_single_pdf`-style results, preserving existing skip/error/converted summary counts.
- `wordpress-marker-runtime-batch-progress-resume-currentbase.php` demonstrates a WordPress import resume path: existing markdown is skipped, pending files are processed, empty output is skipped, and progress reaches 100% without Python/model/runtime execution.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php`

Failed as expected with 2 failures: missing `BatchConverter::batchProgressResumePlan()` and unknown named parameter `$progressCallback`.

Final focused run:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php`

Passed: `1 test files, 61 assertions, 0 failures`.

Adjacent runtime/conversion family:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php`

Passed: `5 test files, 246 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-batch-progress-resume-currentbase.php`

Passed: emitted one `skipped-existing`, two pending filenames before processing, progress events for `skipped-existing`, `converted`, and `skipped-empty-output`, summary counts `converted=1`, `skipped=2`, `errors=0`, and all execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php` passed.
- `php -l lanes/markerpdf/tests/BatchConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-batch-progress-resume-currentbase.php` passed.

Metadata/diff checks:

- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "markerpdf metadata json ok\n";'` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP batch converter, output writer, metadata lookup, and existing PDF text-length preflight. Full live upstream parity remains dependency-gated on Python, Torch multiprocessing, Surya/Texify/tabled model loading, pdftext, pypdfium2/PDFium, Streamlit, FastAPI/Uvicorn, and live worker/model infrastructure.

## Non-Overlap

This does not repeat accepted marker_app config planning, convert.py multiprocessing/model-handoff planning, chunk_convert device sharding, marker_server error/polling behavior, benchmark callback/error boundaries, single-document conversion, PDF parser/xref/font/image/form/metadata behavior, or active runtime execution. The bounded behavior is only CLI batch progress and resume bookkeeping around existing markdown outputs on the current base.
