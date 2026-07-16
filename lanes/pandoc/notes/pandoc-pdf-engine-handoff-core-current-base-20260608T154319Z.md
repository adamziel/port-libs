# Pandoc PDF Engine Handoff Core Current Base 20260608T154319Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T154319Z`
- Accepted base: `b74dfb666585975f95b4cdb08212431ed64ad41f`
- Implemented one bounded fake-runner PDF-output handoff cluster: produced-PDF stream filter policy metadata across xref streams, object streams, page content streams, image XObjects, form XObjects, and annotation appearance streams.

## Behavior

`PdfEngineHandoff` now exposes `pdfStreamFilterPolicy` on fake runs and `finalPdfStreamFilterPolicy` on fake-run sequences. The summary counts filtered stream surfaces, filter names, and bounded review actions:

- `deferred-decode` for compression and ASCII stream filters such as FlateDecode, LZWDecode, RunLengthDecode, ASCIIHexDecode, and ASCII85Decode.
- `image-codec-review` for image codec filters such as DCTDecode, JPXDecode, JBIG2Decode, and CCITTFaxDecode.
- `requires-decryption` for Crypt filters.
- `unsupported-filter` for unknown filter names.

This records handoff policy only; it does not decode PDF content streams, object streams, image streams, or encrypted streams.

## Evidence

- Baseline focused test before this slice: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 883 assertions, 0 failures`.
- Red-first focused test after adding the expectation failed as expected with `1 test files, 885 assertions, 1 failures` because `pdfStreamFilterPolicy` was absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 892 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed.
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`, `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Non-Overlap

This slice avoids the accepted PDF-engine clusters for XMP/PDF-A/PDF-UA metadata, output intents, tagged structure, catalog URI base, page display metadata, annotation appearance extraction, rich media annotations, and page resource ProcSet/Pattern/Shading metadata. It only adds stream-filter policy grouping over metadata already extracted from fake-produced PDF bytes.

## Dependency Closure

No new native support component is needed. The implementation reuses the existing bounded `PdfEngineHandoff` PDF object, stream, resource, and annotation inspection helpers. External Pandoc, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, Cabal/Haskell runners, online services, live provider tests, and live-service provider tests were not executed and remain out of scope for this lane slice.

## Follow-Up

A useful non-overlapping follow-up would add bounded object-stream member provenance, explicit Crypt-filter preflight details, or richer incremental-update repair metadata while preserving the same no-engine fake-runner boundary.
