# Pandoc PDF Engine Handoff Current-Base Slice

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T073948Z`
Base accepted HEAD: `2754d86eb105729f15180756c0192f0180869ecd`

## Behavior Added

- `PdfEngineHandoff` now extracts bounded PDF/A XMP extension schema declarations from produced PDF metadata streams.
- The fake runner exposes the extension handoff under `pdfXmpMetadata.pdfaExtensionSchemas`, including schema name, namespace URI, prefix, and bounded property records with name, value type, category, and description.
- Produced-PDF diagnostics now include `pdf-byte-pdfa-extension-schemas:*`, `pdf-byte-pdfa-extension-properties:*`, and `pdf-byte-pdfa-extension-prefix:*` entries for reviewer triage.
- XMP localized document title/description lookup is now scoped to DC metadata before falling back to unqualified fields, so nested PDF/A extension property descriptions are not misreported as document-level descriptions.
- The WordPress PDF engine handoff smoke now carries a bounded review-metadata extension schema for source slug and reviewer role without invoking Pandoc or any PDF engine.

## Source Truth

- Source truth is the lane-local PDF engine handoff contract for produced-PDF fake-runner diagnostics and static Pandoc inventory mapping.
- This slice ports bounded metadata handoff behavior only. It does not shell out to Pandoc, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, JavaScript, online services, live provider tests, or live-service provider tests.

## Focused Verification

- Red-first observation: the new `PdfEngineHandoffTest.php` case initially failed because nested `pdfaProperty:description` text was treated as document description metadata. The final implementation scopes document title/description lookup to DC metadata and keeps extension property descriptions in `pdfaExtensionSchemas`.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` -> `1 test files, 812 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` -> `pdf engine handoff self-test ok`
- `php -l lanes/pandoc/src/PdfEngineHandoff.php` -> no syntax errors
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` -> no syntax errors
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` -> no syntax errors
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo "$f ok\n"; }'` -> both lane JSON files ok
- `git diff --check -- lanes/pandoc` -> passed
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `lanes/pandoc/tests/PdfEngineHandoffTest.php`: +1 focused PHP PASS case and +8 focused assertions.
- Focused PDF engine handoff coverage: `804 -> 812` assertions in `PdfEngineHandoffTest.php`.
- `lanes/pandoc/lane-status.json`: `phpPass` `1564 -> 1565`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: mapped denominator `1985 -> 1986`.
- PDF engine handoff inventory: `12 -> 13` mapped/core cases and `108 -> 116` focused assertions.

## Non-Overlap

This slice does not repeat the accepted PDF engine handoff clusters for sidecars/logs, SyncTeX, recorder/include-graph diagnostics, XMP packet hash and PDF/A/PDF-UA IDs, output intents/ICC profile handoff, tagged MarkInfo/StructTreeRoot metadata, catalog URI base metadata, page display metadata, page metadata streams, outlines, named destinations, annotations, forms, active actions, or encryption/security preflight.

The new surface is specifically PDF/A XMP extension schema/property declaration handoff from produced PDF bytes.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native `PdfEngineHandoff` fake-runner/PDF byte inspection path and PHP DOM/libxml for bounded XMP extension-schema parsing. The activation gate remains the focused lane test and WordPress smoke above; external engines and validators stay intentionally out of scope for this support-library slice.
