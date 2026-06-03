# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260603T090450Z`

Base accepted HEAD: `a934fd3337210e4ce0a15739eef0bd11ba3529ba`

## Behavior Added

- Added `OpcRelationshipGraph` as a bounded native PHP OPC package graph helper.
- The graph loads `[Content_Types].xml` and every root or part-local `.rels`
  entry from an existing `ZipPackage`, keyed by source part name.
- Added typed target lookup for relationship types such as the DOCX
  `officeDocument` relationship.
- Added resolved target summaries that include relationship id, type, resolved
  target, external flag, and internal target content type.
- Added guards for packages missing `[Content_Types].xml`, malformed
  relationship part names such as `word/_rels/.rels`, and unsafe relationship
  targets that are only exposed once callers resolve or summarize the graph.
- Updated the WordPress DOCX OPC preflight example to consume the graph helper
  instead of manually stitching package and document relationship sets.

## Source Truth

- Existing accepted Pandoc lane evidence records upstream Pandoc DOCX reader
  behavior from `src/Text/Pandoc/Readers/Docx/Parse.hs`: read `_rels/.rels`,
  locate the `officeDocument` relationship by type, and use the target as the
  document XML part.
- This slice keeps the behavior inside native PHP support-library primitives:
  no DOCX body XML parsing, style parsing, external document converter, or
  Haskell upstream runner is invoked.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 128 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `5 test files, 2600 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No external support component is needed. This slice reuses the accepted native
PHP `ZipPackage`, `OpcContentTypes`, and `OpcRelationships` primitives and adds
the smallest package-graph helper needed for richer DOCX/OpenXML conversion
preflight. It does not invoke Pandoc, Word, LibreOffice, zip/unzip, TeX/PDF
engines, template engines, Haskell test binaries, or online services.

## Non-Overlap

This patch is additive on top of accepted ZIP package read/write and OPC XML
parsing/loading. It does not edit dashboard/progress files and does not touch
Markdown writer, HTML reader, doctemplate, YAML metadata, CSL, EPUB/ODT, PDF,
or upstream-runner dependency-audit surfaces.

## Follow-Up

The next package-layer gate remains parsing a minimal `word/document.xml` and
`docProps/core.xml` into the existing Pandoc AST/WordPress handoff path. Style
XML, numbering XML, media extraction, CSL/BibTeX import, and broader upstream
runner dependency planning should stay separate bounded slices.
