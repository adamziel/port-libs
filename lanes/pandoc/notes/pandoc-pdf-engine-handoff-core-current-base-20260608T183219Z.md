# Pandoc PDF Engine Handoff Current-Base XMP Dublin Core Provenance

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T183219Z`
Base: `5cc85a3f48316145610b582134be336e1d3519d4`

## Behavior

Extended the native `PdfEngineHandoff` fake-runner produced-PDF XMP summary to preserve bounded Dublin Core provenance fields from metadata packets:

- `dc:subject` bag entries as `subjects`
- `dc:rights` localized text as `rights`
- `dc:language` bag entries as `languages`
- `dc:relation` bag entries as `relations`
- `dc:source` scalar text as `source`

The fake runner now emits focused diagnostics for subject, language, relation, rights, and source provenance, so importer review queues can distinguish these from generic XMP packet presence.

## Source Truth And Non-Overlap

Source truth is the lane's existing PDF-output handoff contract and the accepted Pandoc static inventory for bounded native produced-PDF metadata inspection. This does not overlap earlier PDF engine slices for missing programs, sidecars/logs, xref/object streams, outlines, page boxes, document-info dates, PDF/A and PDF/UA identification, extension schemas, output intents, page metadata, tagging, annotations, forms, actions, optional content, encryption, headers, linearization, collections, or legal attestations.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF byte scanner, bounded XMP stream decoding, XML text extraction helpers, and WordPress PDF review-packet example. Pandoc, TeX/PDF engines, Typst, browser renderers, roff renderers, external XML/PDF validators, online services, live provider tests, and live-service provider tests remain out of scope.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` -> `1 test files, 887 assertions, 1 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` -> `1 test files, 898 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` -> `pdf engine handoff self-test ok`

Root harness not run - isolated micro-slice.
