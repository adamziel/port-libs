# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260604T175404Z`
Base: `cc27ba2cc75dbbe5844095d9a9dbc89c429430e6`

## Summary

- Added bounded native DOCX tracked-change handling in `DocxReader`.
- Accepted Word insertions (`w:ins`) now become Pandoc-like `span` inline nodes
  with `docx-insertion` class plus `data-docx-change`,
  `data-docx-change-id`, `data-docx-author`, and `data-docx-date` attributes
  when present.
- Deleted Word content (`w:del`) remains suppressed from Markdown and
  WordPress block output, while `importReport.revisions` records deletion and
  insertion id/author/date/text metadata for reviewer audit.
- Updated the WordPress DOCX body smoke so a migration packet carries one
  accepted insertion and one suppressed deletion through the native import
  report without rendering deleted text.

## Source Truth

- DOCX tracked revisions are encoded in WordprocessingML body content as
  `w:ins` and `w:del` runs/containers under the standard
  `http://schemas.openxmlformats.org/wordprocessingml/2006/main` namespace.
- Pandoc's DOCX reader treats accepted inserted content as document content and
  does not render deleted text as normal body text. This slice ports that
  bounded contract into native PHP and adds an import-report audit trail for
  WordPress/Data Liberation review.
- This is not full tracked-change parity. Move ranges, formatting revisions,
  comment-range highlighting, field-code interpretation, and redline rendering
  policy remain separate bounded DOCX slices.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 212 assertions, 0 failures`.
  - Focused DOCX coverage moved from 9 PASS cases / 183 assertions to
    10 PASS cases / 212 assertions.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `11 test files, 3,329 assertions, 0 failures`.
- `php -l lanes/pandoc/src/DocxReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Status Delta

- Adds `+1` focused PHP PASS line and `+29` focused DOCX assertions.
- Updates Pandoc lane status from `361` to `362` PHP pass / `0` fail.
- Updates mapped native Pandoc checks from `818` to `819`.
- Updates DOCX/OpenXML manifest counters from `26` cases / `183` assertions to
  `27` cases / `212` assertions.

## Non-Overlap

This patch does not repeat accepted ZIP/OPC package parsing, ZIP NTFS timestamp
preflight, ZIP Unix symlink rejection, OPC relationship preflight/closure, DOCX
body/core properties, DOCX styles/numbering, DOCX table spans, DOCX
comments/endnotes, DOCX media import reports, or DOCX OMML math handoff. It
only adds bounded tracked-change insertion preservation and suppressed-deletion
reporting to the already accepted DOCX body reader.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP ZIP,
OPC, DOCX XML, AST span, Markdown writer, and WordPress block writer support.
No Pandoc, Cabal build, Haskell test binary, Word, LibreOffice, zip/unzip,
external template engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser
renderer, roff, or online service was executed.

## Next

Keep DOCX nested numbering, comment range highlighting, field-code
interpretation, charts/diagrams, richer media extraction/export policy,
formatting revision ranges, move ranges, and full tracked-change redline
rendering policy as separate bounded slices.
