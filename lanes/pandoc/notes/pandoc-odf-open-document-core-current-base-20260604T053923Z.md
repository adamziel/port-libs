# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260604T053923Z`

Base accepted HEAD: `59ad35343f0b979589ac3a508925c996eae4a547`

## Behavior Added

- Added `OpenDocumentReader` for bounded ODT ZIP packages.
- Validates `META-INF/manifest.xml`, root OpenDocument Text media type, optional
  `mimetype` consistency, safe manifest paths, and required `content.xml`.
- Parses `content.xml` `office:body/office:text` into the existing AST:
  headings, paragraphs, styled spans, links, line breaks, spaces, notes, lists,
  packaged images, and tables.
- Parses `styles.xml` and `content.xml` automatic styles for paragraph heading
  levels, text marks, and ODF list styles, including lower-alpha ordered lists
  with start values.
- Parses `meta.xml` Dublin Core/ODF metadata used by migration review packets.
- Added `wordpress-odt-handoff.php --self-test` to prove the imported ODT AST
  renders through the existing WordPress block writer without office tooling.

## Source Truth

- The slice owns the current `pandoc-odf-open-document-core-*` package contract:
  ODT manifest/content/styles/meta XML mapping under `lanes/pandoc/**`.
- The permitted local upstream cache search found no ODT/OpenDocument fixtures
  or reader test files in `/home/claude/port-libs/.upstream-cache/pandoc`, so
  this ports the ODF package/XML semantics directly and keeps the Haskell
  runner gate unchanged.
- This is intentionally not a full office suite. It does not implement ODF
  tracked changes, annotations, embedded object rendering, page layout, table
  of contents, bibliography fields, or full style cascade semantics.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenDocumentReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-odt-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentReaderTest.php`
  - Result: `1 test files, 90 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-odt-handoff.php --self-test`
  - Result: `odt handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `8 test files, 2928 assertions, 0 failures`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new external support component is needed. This reuses the accepted native
PHP `ZipPackage` support component and PHP DOM XML parsing. It does not invoke
Pandoc, Cabal, Word, LibreOffice, `zip`, `unzip`, external template engines,
TeX/PDF engines, online services, citeproc, BibTeX/Biber, bibliography
managers, or Haskell test binaries.

## Non-Overlap

This does not repeat accepted shared ZIP extra-field handling, OPC
content-types/relationships, DOCX body/style/numbering parsing, math/TeX,
CSL/citation, YAML metadata, doctemplate, Markdown reader/writer, HTML reader,
or WordPress Markdown handoff behavior. It only adds bounded ODT
manifest/content/styles/meta XML mapping plus a WordPress ODT smoke.

## Follow-Up

Keep ODT nested list continuation, broader style inheritance, section/table of
contents frames, annotations, draw object policy, media extraction naming,
tracked changes, and full upstream Pandoc runner dependency planning as
separate gates.
