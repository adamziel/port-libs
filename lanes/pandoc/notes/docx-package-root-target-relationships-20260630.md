# DOCX Package Root Target Relationships

Slice: `plib-52hnt`, DOCX OpenXML package ingestion.

`DocxOpenXmlReader` now preserves metadata-only provenance for relationship
targets declared by non-standard package-root resource sidecars. The
`packageRootRelationshipResources` report carries nested target relationship
records plus existing, missing, allowed-external, unsafe-external, content-type,
and issue-code rollups, and mirrors those counts into `packageProvenance.summary`.

Package-root resource payload bytes and nested target bytes remain blocked under
metadata-only review policies. Direct-format parity accounting remains active in
`lane-status.json`; this slice only closes the bounded DOCX/OpenXML package
ingestion surface and does not invoke external validators.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 10011 assertions, 0 failures
