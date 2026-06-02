# markerPDF Runtime Server Remote Polling Error Current Base

Micro-slice: `runtime-server-remote-polling-error-currentbase`
Session: `port-dev-markerpdf-runtime42pdf-20260602T1927Z`
Base accepted HEAD: `0962bc173d9405ad2a4150597c334fce11dba6e5`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` implements `marker_server.py::convert_pdf_remote` by:

- posting multipart form data to `app.state.DATALAB_URL` with `X-API-Key`;
- reading `request_check_url` directly from `response.json()`;
- polling that URL up to 300 times with `await asyncio.sleep(2)`;
- reading `data["status"]` on every poll and breaking only when it equals `complete`;
- returning the final poll payload without inventing a timeout error.

This PHP slice keeps that behavior as a native callback boundary. It does not execute `requests`, FastAPI, Uvicorn, Python, Datalab, or model code.

## Behavior

- `MarkerServerAdapter::convertPdfRemote()` now rejects non-array decoded remote payloads before `request_check_url` or `status` access.
- Poll responses must include the upstream `status` key, matching upstream's direct `data["status"]` access.
- The bounded PHP `maxPolls` test seam must be positive, preventing a zero-poll path that upstream's fixed `range(300)` loop never has.
- Max-poll exhaustion still returns the last upstream payload; the PHP port does not create a local timeout error that upstream does not emit.
- `MarkerServerAdapter::remotePollingPlan()` records the fixed POST/GET polling contract, 300 poll count, two-second upstream interval, status key, completion marker, and non-execution flags.
- `wordpress-marker-runtime-server-remote-polling-error-currentbase.php` demonstrates missing-status rejection and max-poll exhaustion for a WordPress import queue without live HTTP.

## Evidence

Red-first focused run before source fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerServerAdapterTest.php`

Failed as expected: `1 test files, 38 assertions, 2 failures` for accepting a poll payload without `status` and missing `remotePollingPlan()`.

Final focused run:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerServerAdapterTest.php`

Passed: `1 test files, 52 assertions, 0 failures`.

Expected PASS-line movement: `711 -> 717` markerPDF PHP PASS cases from six new focused server-remote tests.

## Verification To Rerun

- `php -l lanes/markerpdf/src/MarkerServerAdapter.php`
- `php -l lanes/markerpdf/tests/MarkerServerAdapterTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-server-remote-polling-error-currentbase.php`
- `php lanes/markerpdf/examples/wordpress-marker-runtime-server-remote-polling-error-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerServerAdapterTest.php`
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'`
- `git diff --check -- lanes/markerpdf`

## Dependency Closure

No new support component is needed. This slice reuses the existing native `MarkerServerAdapter`, supplied remote-client callback boundary, local fixture file handling, JSON lane status/manifest artifacts, and WordPress smoke pattern. Full upstream runtime parity remains gated on live FastAPI/Uvicorn execution, Python `requests`, Datalab API access, Poetry, `pdftext`, `pypdfium2`, Surya/Torch model downloads, `tabled-pdf`, Texify, Streamlit paths, benchmark workflows, and OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted runtime benchmark API callbacks, conversion boundary planning, convert.py benchmark-error review metadata, marker server local/upload happy paths, PDF parser/xref/font/image/security/form/page/table/outline/metadata slices, or live runtime execution. The bounded behavior is only upstream marker server remote polling error and exhaustion semantics.
