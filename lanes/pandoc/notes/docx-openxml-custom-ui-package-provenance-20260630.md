# DOCX/OpenXML Custom UI Package Provenance - 2026-06-30

Slice: `plib-kbghw`, DOCX OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now reports package-root Office custom UI relationships as
metadata-only DOCX package provenance:

- Custom UI, CustomUI2, and Quick Access Toolbar root relationships are
  summarized under `docx.customUiParts` and
  `docx.packageProvenance.customUiParts`.
- Each custom UI part records target suffixes, content type provenance, byte
  length, CRC32/SHA-256, root XML namespace/local-name diagnostics, and
  metadata-only byte exposure policy.
- Custom UI sidecar image relationships are preflighted with target existence,
  content type, suffix, digest, missing/external diagnostics, and package
  inventory roles for `custom-ui`, `custom-ui2`,
  `custom-ui-quick-access-toolbar`, and `custom-ui-image`.

Source-truth scope used for this bounded slice:

- Microsoft MS-CUSTOMUI customUI relationship/root package contract:
  https://learn.microsoft.com/en-us/openspecs/office_standards/ms-customui/
- Microsoft MS-CUSTOMUI2 CustomUI2 relationship/root package contract:
  https://learn.microsoft.com/en-us/openspecs/office_standards/ms-customui2/

The importer does not execute custom UI callbacks, fetch external custom UI
targets, run office automation, expose custom UI XML/image bytes as document
media, or validate the full Ribbon schema. This remains inert package-review
metadata for importer handoff.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 file, 10073 assertions, 0 failures.

Direct-format parity accounting:

- Adds one focused DOCX/OpenXML package ingestion PHP PASS case.
- No direct-format denominator or cross-format status rows were changed.
- `phpFail` remains zero for the focused DOCX OpenXML test file.

No Pandoc binary, Word, LibreOffice, office suite, zip/unzip command, browser
renderer, Node tooling, external validator, online service, live provider test,
or external Ribbon/custom UI runtime was invoked.
