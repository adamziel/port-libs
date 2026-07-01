# DOCX ZIP manifest directory root provenance

Date: 2026-07-01
Slice: `plib-ztkzf`

`DocxOpenXmlReader::readZipPackage()` now projects ZIP package manifest
directory-root provenance from `ZipPackage::packageManifestPreflight()` into
DOCX/OpenXML package ingestion metadata:

- `packageProvenance.zipPackage` exposes manifest directory-root counts, root
  lists, and root summaries;
- each ZIP entry projection carries its manifest `directoryRoot`;
- loaded DOCX part inventory rows carry `zipDirectoryRoot`;
- `packageProvenance.summary` exposes the manifest directory-root count, roots,
  and summaries for review handoff.

This stays metadata-only: DOCX package bytes remain blocked by the existing
`docx-zip-entry-metadata-only` policy, and no Pandoc, office suite, TeX/browser
engine, `zip`/`unzip`, Jupyter, Node, live service, or external validator was
invoked.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 10,836 assertions, 0 failures

Direct-format parity remains active in lane status.
