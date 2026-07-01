# pandoc-docx-openxml-embedded-font-external-policy-20260630

Slice: `plib-0wcqk`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now carries metadata-only external-target policy for
embedded fonts declared from `word/fontTable.xml` relationships. Embedded font
records expose target kind, scheme, allowed/unsafe status, and external target
issue codes, and the font-table/package summaries roll those into allowed,
unsafe, kind, scheme, and issue-code counts.

The slice keeps embedded font bytes blocked under the existing
`embedded-font-bytes-blocked` policy. It does not fetch remote fonts, decode
obfuscated font payloads, invoke Office/Pandoc/zip tooling, or change document
text/media rendering.

Focused validation before handoff:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 1 file, 10,009 assertions, and 0 failures.

Direct-format parity remains active in `lane-status.json`; broad Pandoc lane
failures remain tracked as baseline backlog outside this DOCX package slice.
