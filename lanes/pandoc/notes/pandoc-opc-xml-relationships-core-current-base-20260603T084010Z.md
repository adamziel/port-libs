# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260603T084010Z`

Base accepted HEAD: `72f5cb84857abafdc63cdb83c5e14ce84d9bf3fb`

## Behavior Added

- Added `OpcRelationships::fromPackage()` to load the correct root or
  part-local `.rels` package part through the existing native `ZipPackage`
  reader, then parse it with the accepted namespace-aware relationship XML
  parser.
- Added `OpcRelationships::packageHasRelationshipsForSource()` so DOCX/OPC
  preflight can distinguish an optional missing part-local relationships part
  from malformed relationship XML.
- Updated the WordPress DOCX OPC preflight smoke to build a tiny in-memory
  DOCX-like ZIP package, load `[Content_Types].xml`, `_rels/.rels`, and
  `word/_rels/document.xml.rels` from package entries, and resolve document,
  styles, media, core-properties, and reviewer hyperlink targets without
  invoking Pandoc, Word, LibreOffice, zip/unzip, Haskell binaries, or services.

## Source Truth

- Existing accepted manifest evidence records upstream Pandoc Docx reader
  behavior from `src/Text/Pandoc/Readers/Docx/Parse.hs`: read `_rels/.rels`,
  find the `officeDocument` relationship by type, then use that target as the
  document XML part.
- This slice closes the local support-library gap between the accepted ZIP
  central-directory reader and accepted OPC relationship XML parser so the
  relationship graph is loaded from actual package parts instead of standalone
  XML strings.

## Verification

- `php -l lanes/pandoc/src/OpcRelationships.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 99 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `4 test files, 2487 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, and `OpcRelationships` primitives and adds the
small package-loading bridge required for richer DOCX/OpenXML conversion. The
next activation gate is parsing a minimal `word/document.xml` and
`docProps/core.xml` into the existing Pandoc AST/WordPress handoff path.

## Non-Overlap

This patch does not edit dashboard/progress files, does not shell out to any
document converter or archive tool, and does not touch Markdown writer,
doctemplate, YAML metadata, CSL, EPUB/ODT, PDF, or upstream-runner audit
surfaces. It is additive on top of the accepted OPC XML parsing and ZIP package
reader slices.
