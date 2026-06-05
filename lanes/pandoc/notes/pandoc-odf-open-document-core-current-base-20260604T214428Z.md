# pandoc-odf-open-document-core-current-base-20260604T214428Z

## Scope

Implemented a bounded native PHP ODT/OpenDocument package reader under
`lanes/pandoc/src/OdtReader.php`.

The slice maps ZIP-backed `.odt` package behavior into the existing Pandoc-like
AST and WordPress block writer without invoking Pandoc, Word, LibreOffice,
zip/unzip, Haskell test binaries, online services, or template/rendering
engines.

Covered behavior:

- ODT `mimetype` validation and required `content.xml` preflight.
- `META-INF/manifest.xml` file-entry parsing with media type, size, and
  encrypted-entry reporting.
- `meta.xml` and content-level `office:meta` metadata extraction.
- `content.xml` `office:body/office:text` block parsing.
- `styles.xml` and content automatic style parsing with inherited text
  emphasis/strong/underline/strikeout/superscript/subscript and paragraph
  alignment metadata.
- `text:h`, `text:p`, `text:a`, `text:s`, `text:tab`, `text:line-break`,
  bookmarks, notes, annotations, sections, ordered/bullet lists, list restarts,
  tables with row/column spans and repeated cells, frame images, and text boxes.
- Import reports for embedded and missing frame-image package media.

## Source Truth

The pinned upstream Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` is still not hydrated in
`/home/claude/port-libs/.upstream-cache/pandoc` or this isolated worktree, so
the full Haskell ODT runner was not available. This slice uses the accepted
Pandoc lane manifest as source truth for the active ODF/OpenDocument support
row and ports the OpenDocument package/content contract directly in native PHP.

No current pandoc rework note existed under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Verification

- `php -l lanes/pandoc/src/OdtReader.php`
  - `No syntax errors detected in lanes/pandoc/src/OdtReader.php`
- `php -l lanes/pandoc/tests/OdtReaderTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/OdtReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-odt-open-document-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-odt-open-document-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 81 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odt-open-document-handoff.php --self-test`
  - `ODT OpenDocument handoff self-test passed`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `378 -> 384`
- mapped native checks: `835 -> 841`
- ODF/OpenDocument mapped cases: `10 -> 16`
- ODF/OpenDocument focused assertions: `217 -> 298`

## Non-Overlap

This does not touch archive-compression LZ4 behavior, DOCX/OpenXML parsing,
OPC relationship graph behavior, YAML, CSL, doctemplates, math/TeX, table
geometry internals, legacy DOC/CFB, or PDF handoff planning. It reuses the
accepted `ZipPackage`, `AstNode`, `TableGeometry`, and `WordPressBlockWriter`
support surfaces.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded ZIP
package reader, AST nodes, table geometry layout, and WordPress block writer.

Remaining follow-up for ODT should stay bounded: richer embedded-object policy,
page styles/master pages, formula objects, tracked changes, deeper list
continuation semantics, and export-side ODT writing should be separate slices.
