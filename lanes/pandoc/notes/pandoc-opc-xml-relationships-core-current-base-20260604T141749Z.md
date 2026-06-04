# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260604T141749Z`

Base accepted HEAD: `c214d80048a00d1bba83ecf86611d1b0b77dd12e`

## Behavior Added

- Added native validation for OPC relationship `Id` values in
  `OpcRelationship`.
- Relationship ids now accept the bounded XML NCName-style shape used by DOCX
  `r:id` references: ASCII letters or underscore first, then ASCII letters,
  digits, dot, underscore, or hyphen.
- Relationship XML parsing and direct relationship construction reject ids with
  a leading digit, whitespace, colon namespace syntax, slash path syntax, or a
  leading hyphen before target resolution or graph preflight.

## Source Truth

- Existing accepted Pandoc lane evidence records DOCX/OpenXML relationship
  handling from the package root through the `officeDocument` relationship and
  part-local relationship sets.
- OPC relationship ids are the stable lookup keys consumed by WordprocessingML
  `r:id` attributes. This slice keeps that support-library contract bounded:
  ids must be XML identifier-like values, while target resolution, content-type
  lookup, graph preflight, and reachable closure behavior remain unchanged.

## Verification

- `php -l lanes/pandoc/src/OpcRelationship.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 194 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `10 test files, 3150 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Focused OPC assertions moved from the prior accepted `188` to `194`, adding 6
assertions and 1 new PASS case. Pandoc lane status now records `343` PHP pass /
`0` fail and `779` mapped checks. Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationship`, and
`OpcRelationshipGraph` primitives. It does not invoke Pandoc, Word,
LibreOffice, zip/unzip, external template engines, TeX/PDF engines, Haskell
test binaries, bibliography tools, or online services.

## Non-Overlap

This patch is additive on top of accepted ZIP/OPC package primitives,
content-type parsing, relationship target resolution, target integrity
preflight, and reachable relationship closure traversal. It does not edit root
dashboard/progress files and does not touch Markdown/HTML reader/writer,
doctemplate, YAML metadata, CSL, DOCX body parsing, ODT, PDF, math, legacy
DOC/CFB, archive compression, or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep DocxReader import-report wiring for reachable OPC closure diagnostics,
media extraction policy, DOCX nested numbering, ZIP64/symlink/NTFS extra-field
decisions, richer ODT embedded-object and page-style policy, CSL style
XML/locales, BibTeX/BibLaTeX parsing, TeX accents/matrices/alignment, and the
Pandoc Haskell runner dependency plan as separate bounded slices.
