# DOCX custom XML orphan package items

Date: 2026-06-30
Hook: plib-mwq2m
Molecule: plib-wisp-l694v

## Slice

DocxOpenXmlReader now discovers customXml package item parts even when no document-level customXml relationship points at them, provided the package part has a same-source `.rels` with a customXmlProps relationship. The reader exposes those items through `customXmlParts.items`, `customXmlParts.byPartName`, orphan counters, package inventory roles, and content-control storeItemID binding handoff.

The new path keeps custom XML payloads metadata-only. The orphan package part is not routed through media exposure, and external tools such as Pandoc, Office, TeX, browsers, unzip, Node, or validators were not invoked.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed with 1 file, 15,151 assertions, and 0 failures.

## Accounting

- `lane-status.json` increments focused PHP pass coverage for this DOCX/OpenXML package-ingestion slice.
- `UPSTREAM_TEST_MANIFEST.json` adds one mapped DOCX custom XML orphan package item case and raises the mapped focused denominator to 2,461 on the retargeted integration branch.
