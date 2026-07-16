# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260603T213630Z`

Base accepted HEAD: `ee5bab2f1ee2c0907fe52d29b7278104c9b95fba`

## Behavior Added

- Added `DocxReader`, a bounded native PHP DOCX/OpenXML reader built on the
  accepted `ZipPackage` and OPC relationship graph primitives.
- The reader resolves the package-level `officeDocument` relationship, loads
  `word/document.xml`, and maps a minimal WordprocessingML body into the
  existing Pandoc-like AST:
  - `w:p` paragraphs and `Heading1` through `Heading6` styles.
  - `w:r` text, tabs, line breaks, and basic run styles for bold, italic,
    underline, strikeout, small caps, superscript, and subscript.
  - External and internal `w:hyperlink` targets through document
    relationships.
  - `w:footnoteReference` through the document `footnotes` relationship.
  - Simple DrawingML image relationships with media part, alt/title, and byte
    metadata.
  - Simple `w:tbl` rows and cells as AST table rows/cells.
- The reader also loads `docProps/core.xml` fields such as title, creator,
  description, created, modified, lastModifiedBy, and revision.
- Added `wordpress-docx-body-handoff.php` as a WordPress-relevant local smoke
  that converts an in-memory DOCX package into WordPress blocks.

## Source Truth

- Upstream Pandoc `readDocx` unpacks the DOCX container and passes the archive
  through `archiveToDocxWithWarnings` before converting the parsed document to
  Pandoc AST. The pinned upstream source was inspected from GitHub raw because
  the local `.upstream-cache/pandoc` checkout was absent in this worker.
- Upstream `Text.Pandoc.Readers.Docx.Parse` resolves `_rels/.rels` to the
  `officeDocument` target, loads `word/document.xml`, walks `w:body`, and
  turns `w:t`, `w:br`, `w:tab`, hyperlinks, footnote references, drawings, and
  tables into body/run/table structures.
- The `docProps/core.xml` reader is the bounded OpenXML properties half of this
  slice's contract, so package metadata can be handed to WordPress import
  preflight before richer Pandoc-style metadata reconciliation is added.
- This slice ports that contract in a bounded PHP form without attempting full
  Word style inheritance, numbering, comments, endnotes, OMML, charts,
  diagrams, or field-code support.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 57 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `6 test files, 2750 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new external support component is needed. This reuses the accepted native
PHP ZIP package reader/writer, OPC content-types parser, OPC relationships
parser, and OPC relationship graph. It does not invoke Pandoc, Cabal, Word,
LibreOffice, `zip`, `unzip`, TeX/PDF engines, external template engines,
online conversion services, or Haskell test binaries.

## Non-Overlap

This patch does not repeat accepted ZIP central-directory metadata,
local-header validation, OPC content types, OPC relationship graph preflight,
doctemplate, YAML, Citation/CSL, Markdown reader/writer, HTML reader, or
WordPress Markdown handoff surfaces. It is the first bounded DOCX body/core
metadata reader layer on top of those accepted support primitives.

## Follow-Up

Keep DOCX styles/numbering/list semantics, richer table grid span handling,
comments/endnotes, media extraction policy, OMML/math, charts/diagrams, and
field-code interpretation as separate slices.
