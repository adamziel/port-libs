# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260604T095000Z`

Base accepted HEAD: `baadbbe49cad643694d03f64b0d4bda64bf50c5e`

## Behavior Added

- Extended `DocxReader` to load optional DOCX `word/endnotes.xml` and
  `word/comments.xml` parts through document-level OPC relationships.
- Resolved `w:endnoteReference` and `w:commentReference` run children into
  existing Pandoc-like `note` AST nodes, sharing the accepted Markdown and
  WordPress endnote rendering path.
- Preserved comment `w:author`, `w:initials`, and `w:date` metadata on the
  note node for reviewer/audit tooling.
- Reused the existing paragraph/table parsing inside note parts so bounded
  endnote content can include paragraphs and simple tables.
- Updated the WordPress DOCX body handoff smoke to carry footnote, endnote,
  and reviewer comment content into the WordPress endnotes block.

## Source Truth

- This slice builds on the accepted DOCX/OpenXML reader source-truth record:
  DOCX is an OPC package whose main document part points to related
  WordprocessingML parts through `word/_rels/document.xml.rels`.
- The bounded relationship types ported here are the standard WordprocessingML
  `endnotes` and `comments` relationships, and the body references are
  `w:endnoteReference` and `w:commentReference`.
- This is intentionally not a full Word annotations implementation. It does
  not render comment range highlighting, tracked-change authoring, nested
  numbering, field codes, OMML, charts, diagrams, or media extraction policy.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Before: `1 test files, 108 assertions, 0 failures`, 6 PASS lines.
  - After: `1 test files, 135 assertions, 0 failures`, 7 PASS lines.
  - Delta: +1 focused PASS line, +27 assertions.
- `php -l lanes/pandoc/src/DocxReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, OPC relationship graph, DOCX paragraph/run/table parser, and
existing Markdown/WordPress note writers. It does not invoke Pandoc, Cabal,
Word, LibreOffice, `zip`, `unzip`, external template engines, TeX/PDF engines,
online conversion services, or Haskell test binaries.

## Non-Overlap

This patch does not repeat accepted ZIP central-directory metadata,
local-header validation, OPC content types, OPC relationship graph target
integrity preflight, doctemplate, YAML, Citation/CSL, Markdown reader/writer,
HTML reader, WordPress Markdown handoff, DOCX body/core-property parsing,
DOCX style/numbering handoff, DOCX table span handoff, ODT handoff,
Math/TeX conversion, or PDF engine handoff planning. It extends only the DOCX
reader's bounded annotations/endnotes package-part handling.

## Follow-Up

Keep DOCX nested numbering, comment range highlighting, tracked changes,
field-code interpretation, OMML/math, charts/diagrams, media extraction
policy, and higher-level OPC preflight diagnostics as separate gates.
