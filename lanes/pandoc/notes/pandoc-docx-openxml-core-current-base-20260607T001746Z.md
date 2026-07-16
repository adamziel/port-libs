# Pandoc DOCX OpenXML Core Current Base - Package Properties

Slice: `pandoc-docx-openxml-core-current-base-20260607T001746Z`
Base accepted HEAD: `13afa6bbcfe66cce46d4907c863b6703a36c5f2e`

## Behavior

Implemented one bounded DOCX/OpenXML support-library cluster: package-level
document properties beyond core properties.

- `DocxReader` now discovers `docProps/app.xml` through the package
  `extended-properties` OPC relationship and exposes bounded scalar/vector
  metadata such as company, page/word counts, hyperlink base, heading pairs,
  and titles of parts.
- `DocxReader` now discovers `docProps/custom.xml` through the package
  `custom-properties` OPC relationship and exposes typed `vt:*` custom
  property values, first-value `byName` lookup, duplicate-name reporting, and
  a flattened `metadata.customProperties` handoff for WordPress review.
- `importReport.properties` mirrors the extended/custom property reports so
  downstream import review can audit part paths, content types, and
  relationship summaries.

No package path guessing was added; both property parts are resolved through
native OPC relationship discovery.

## Verification

Baseline before the patch:

- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 1822 assertions, 0 failures`

Final focused checks:

- `php -l lanes/pandoc/src/DocxReader.php`
  - no syntax errors
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - no syntax errors
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 1881 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`
- `git diff --check -- lanes/pandoc`
  - passed with no output

Assertion delta: `+59` focused assertions. Lane pass delta: `+1` PHP PASS
case. Manifest mapped denominator delta: `+1`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`ZipPackage`, `OpcRelationshipGraph`, `OpcRelationships`, `XmlHtmlDom`, and
`DocxReader` paths.

No Pandoc, Word, LibreOffice, zip/unzip, external office tools, online
services, Cabal, or Haskell runners were executed.

## Non-Overlap

This does not repeat the recently accepted DOCX body, settings document
variables, embedded object/package, tracked revision, deleted field/math,
move-range, direct hyperlink, section geometry, media, glossary, style,
numbering, table, comment, OMML, altChunk, or textbox slices. It is scoped only
to package-level extended/custom document properties.

## Follow-Up

Potential DOCX follow-up should stay bounded to non-overlapping document
property edge diagnostics, relationship-target policy, or writer handoff
metadata, still without invoking external office tooling.
