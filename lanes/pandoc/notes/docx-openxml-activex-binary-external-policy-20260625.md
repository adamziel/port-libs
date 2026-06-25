# DOCX/OpenXML ActiveX Binary External Policy - 2026-06-25

Slice: `plib-2fp3t`, DOCX OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now exposes external target policy metadata for nested
`activeXControlBinary` relationships:

- `externalTargetKind`, `externalTargetScheme`, `externalTargetAllowed`, and
  `externalTargetIssues` are preserved on each ActiveX binary relationship row.
- ActiveX binary summaries now roll up allowed and unsafe external targets,
  unsafe target lists, and external target issue codes.
- Package summary metadata reports ActiveX binary external, allowed external,
  unsafe external, and external target issue code counts.

This remains metadata-only package provenance. ActiveX control and binary bytes
stay blocked from document media handoff, and external targets are not fetched
or dereferenced.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 file, 9,489 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` was run after rebase and still
  fails outside this slice: 276 files, 105,739 assertions, 10,829 failures.

No Pandoc binary, Word, LibreOffice, office suite, zip/unzip command, browser
renderer, Node tooling, external validator, online service, live provider test,
or external ActiveX runtime was invoked.
