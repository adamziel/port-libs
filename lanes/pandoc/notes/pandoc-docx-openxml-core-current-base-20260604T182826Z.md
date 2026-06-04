# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260604T182826Z`
Base: `312d941a4f6e6613cd5196ba4e0ba079c1d538ed`

## Summary

- Added bounded native DOCX comment-range handling in `DocxReader`.
- `w:commentRangeStart` through matching `w:commentRangeEnd` now becomes an
  inline `span` with `docx-comment-range` class and
  `data-docx-comment-id`.
- When the matching `w:comment` exists, the range span also carries reviewer
  metadata as `data-docx-comment-author`,
  `data-docx-comment-initials`, and `data-docx-comment-date`.
- Existing `w:commentReference` handling is preserved, so the highlighted body
  text remains distinct from the imported comment note.
- Updated the WordPress DOCX body smoke so reviewer-highlighted Word comments
  survive the native DOCX to WordPress block handoff.

## Source Truth

- WordprocessingML encodes comment anchors with `w:commentRangeStart`,
  `w:commentRangeEnd`, and `w:commentReference`, while comment bodies and
  reviewer metadata live in `word/comments.xml`.
- Pandoc's DOCX reader preserves comment references as note-like content and
  imports the surrounding Word document content rather than dropping the
  annotated range. This slice ports the bounded PHP contract: keep the comment
  range visible as AST span metadata while still importing the comment note.
- This is not full comment parity. Cross-paragraph range stitching, nested
  overlapping comment ranges, comment balloons, and redline rendering policy
  remain separate gates.

## Verification

- Baseline before the patch:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 212 assertions, 0 failures`.
- Focused after the patch:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 230 assertions, 0 failures`.
  - Delta: +1 focused PASS line and +18 assertions.
- Focused lane suite:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `11 test files, 3357 assertions, 0 failures`.
- Syntax checks:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Adds `+1` focused PHP PASS line and `+18` focused DOCX assertions.
- Updates Pandoc lane status from `363` to `364` PHP pass / `0` fail.
- Updates mapped native Pandoc checks from `820` to `821`.
- Updates DOCX/OpenXML manifest counters from `27` cases / `212` assertions to
  `28` cases / `230` assertions.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP ZIP,
OPC relationship graph, DOCX comments part loader, AST span, Markdown writer,
and WordPress block writer support. No Pandoc, Cabal build, Haskell test
binary, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, MathJax, KaTeX, Typst, browser renderer, roff, or online service was
executed.

## Non-Overlap

This patch does not repeat accepted ZIP/OPC package parsing, relationship
preflight/closure, DOCX body/core properties, DOCX styles/numbering, DOCX
table spans, DOCX comments/endnotes note import, DOCX media import reports,
DOCX OMML math handoff, or DOCX tracked-change insertion/deletion reporting.
It only adds bounded body-range highlighting for comments already referenced by
the accepted comments part loader.

## Next

Keep DOCX nested numbering, field-code interpretation, charts/diagrams, richer
media extraction/export policy, cross-paragraph comment range stitching,
formatting revision ranges, move ranges, and full tracked-change redline
rendering policy as separate bounded slices.
