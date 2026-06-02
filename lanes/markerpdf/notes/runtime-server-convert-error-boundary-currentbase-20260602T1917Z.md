# markerPDF Runtime Server Convert Error Boundary

Micro-slice: `runtime-server-convert-error-boundary-currentbase`

Accepted base: `4dc1f21b98948ff243f10a6054e126d012098006`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker_server.py::convert_pdf_from_upload()` rejects non-PDF uploads before conversion, then wraps upload save/read plus local or remote conversion in `try/except/finally`. Exceptions inside that body return `{"success": False, "error": str(e)}` and the uploaded file is removed in `finally`.
- The upload endpoint branches directly to `convert_pdf_local()` or `convert_pdf_remote()` after saving the file. The direct `/marker` endpoint is the path that applies the local-mode `extract_images=True` and `paginate=False` assertion before calling local conversion.
- Direct `marker_server.py::convert_pdf_remote()` still relies on `request_check_url` from the remote JSON response and does not have the upload wrapper's catch boundary.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Patch

- `MarkerServerAdapter::convertPdfFromUpload()` now reads/writes the uploaded bytes and calls local or remote conversion inside the upstream-style guarded body.
- Uploaded local PDFs now branch directly to local conversion, keeping the direct `/marker` option guard separate from the upload route.
- Valid PDF upload failures now return `success=false` error payloads for unreadable upload payloads and malformed remote API conversion responses.
- The temporary uploaded PDF is removed after failed conversion just as it is after successful conversion.
- Non-PDF upload rejection remains an exception boundary, matching the upstream FastAPI `HTTPException` behavior before the guarded conversion block.
- `wordpress-marker-server-convert-error-boundary-currentbase.php` demonstrates a WordPress import endpoint receiving a failed remote conversion payload and confirming upload cleanup without running FastAPI, Uvicorn, requests, Python, models, or external PDF tools.

## Evidence

Red-first focused run after adding the tests:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerServerAdapterTest.php`

Failed as expected with 2 failures:

- `Remote marker API response is missing request_check_url.`
- `Uploaded PDF payload must provide bytes.`

Final focused server run:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerServerAdapterTest.php`

Passed: `1 test files, 41 assertions, 0 failures`.

Adjacent runtime/server family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/ModelPipelinePlannerTest.php`

Passed: `6 test files, 174 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-server-convert-error-boundary-currentbase.php`

Passed: emitted `success=false`, `error="Remote marker API response is missing request_check_url."`, `request_count=1`, `remote_filename="editorial-remote-error.pdf"`, `upload_removed=true`, and execution flags for FastAPI/Uvicorn/Python/models/external PDF tools all false.

PHP lint:

- `php -l lanes/markerpdf/src/MarkerServerAdapter.php`
- `php -l lanes/markerpdf/tests/MarkerServerAdapterTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-server-convert-error-boundary-currentbase.php`

All reported no syntax errors.

Lane metadata validation:

`php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " OK\n"; }'`

Both JSON files decoded successfully.

Whitespace check:

`git diff --check -- lanes/markerpdf`

Passed with no output.

## Dependency Closure

No new support component is needed. This reuses the existing native `MarkerServerAdapter`, supplied remote callback boundary, upload temp-file path, and WordPress smoke path. Full upstream markerPDF runtime parity remains gated by FastAPI/Uvicorn, `requests`, pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, model downloads, and live Python multiprocessing/server infrastructure.

## Non-Overlap

This does not repeat accepted runtime conversion pool planning, Streamlit runtime preflight, marker server direct `/marker` local option guards, marker server success-shape/upload success handling, local converter success/failure normalization, remote polling success, benchmark API callbacks, or PDF parser/security/font/xref/table/image/form/outline slices. The bounded behavior is only the `marker_server.py::convert_pdf_from_upload()` direct upload conversion route plus save/convert exception boundary and upload cleanup on valid-PDF upload failures.
