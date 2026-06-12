# DOCX missing-source relationship target summary slice

Slice: `plib-5jd43`

## Summary

`DocxOpenXmlReader` package provenance now expands
`summary.relationshipsFromMissingSources` from a sidecar/source/count marker into
a review-ready target summary for orphan `.rels` sidecars. Each missing-source
row carries relationship ids, internal/external/existing/missing target counts,
target parts, external targets, missing content-type targets, target suffixes,
and the same per-relationship content-type/query/fragment/existence metadata
used elsewhere in package review summaries.

This is metadata-only DOCX/OpenXML package ingestion. It does not dereference
external targets, expose orphan targets as document media, or invoke Pandoc,
office suites, zip/unzip, browser engines, external validators, online services,
live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 test file, 1443 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 69218 assertions, 0 failures
