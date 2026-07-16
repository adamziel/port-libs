## pandoc-pdf-engine-handoff-core-current-base-20260608T122725Z

Base accepted HEAD: ec3fcd8c06ee8c4227512440abc785082910ef66

Scope:
- Added bounded native FlateDecode handling for produced-PDF XMP metadata streams in `PdfEngineHandoff` fake-runner inspection.
- The fake runner now decodes single-filter `/Filter /FlateDecode` metadata streams with the existing lane-local `DeflateStream`, exposes `decodedFilter` and `compressedBytes` provenance, and reports `pdf-byte-xmp-metadata-decoded:FlateDecode` plus compressed-byte diagnostics.
- Unsupported filters, non-null DecodeParms, malformed compressed metadata, and too-large decoded metadata remain fail-closed as skipped/too-large handoff metadata.
- Updated the WordPress PDF review-packet smoke so its catalog XMP packet is Flate-compressed while still surfacing the decoded XMP fields.

Non-overlap:
- This slice extends only the produced-PDF XMP metadata handoff path.
- It does not repeat accepted PDF engine slices for URI base, PDF/A/PDF/UA namespace extraction, XMP extension schemas, output intents, page metadata, page display/timing/viewports, tagged structure, destinations, annotations, active actions, optional content, signatures, embedded files, legal attestation, RichMedia, resource streams, linearization, or engine sidecar/log diagnostics.
- No Pandoc, Cabal/Haskell runner, TeX/PDF engine, Typst, browser renderer, roff renderer, external PDF validator, online service, live provider test, or live-service provider test was executed.

Focused evidence:
- Baseline before this patch: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 854 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 861 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case and `+7` assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed with `pdf engine handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`, `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check passed: `git diff --check -- lanes/pandoc`.
- Root harness: not run - isolated micro-slice.

Status delta:
- `lane-status.json` `phpPass`: `1640` -> `1641`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2060` -> `2061`.
- `pdfEngineHandoffCoreCases`: `12` -> `13`.
- `mappedPdfEngineHandoffCoreCases`: `12` -> `13`.
- `pdfEngineHandoffCoreAssertions`: `108` -> `115`.

Dependency closure:
- No new native PHP support component is needed. This slice reuses the existing `PdfEngineHandoff` PDF byte parser and the existing lane-local `DeflateStream` support helper.
- Remaining compressed PDF stream work such as predictor DecodeParms, page-level compressed metadata coverage, filtered resource decoding, full PDF stream-filter parity, real renderer execution, and external validator parity stays separate.
