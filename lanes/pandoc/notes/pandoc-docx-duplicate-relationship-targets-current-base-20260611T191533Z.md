# Pandoc DOCX Duplicate Relationship Targets

- Hook: `plib-mstem`
- Base: `30462ed7c9a480ede4248fb13aa18982f7bbd5bc`
- Scope: DOCX/OpenXML package ingestion provenance only.

This slice adds package provenance for duplicate internal relationship target groups inside a single `.rels` sidecar. `DocxOpenXmlReader` now reports the duplicate target group count, the relationship parts containing duplicate targets, and deterministic duplicate target records with source part, relationships part, target part, content-type provenance, relationship ids, relationship types, and raw target strings.

Focused fixture coverage creates two additional document image relationships pointing at the existing `word/media/review.png` part, including one query/fragment target, and verifies the duplicate summary plus existing relationship-type inventory behavior.

Verification on this base:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> 1 file, 881 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 65561 assertions, 0 failures

No Pandoc binary, Word/LibreOffice, office suites, zip/unzip commands, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
