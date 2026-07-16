# DOCX/OpenXML Core Current-Base Paragraph Policy Slice

Micro-slice: `pandoc-docx-openxml-core-current-base-20260609T001549Z`

Accepted base: `2db0e80f0d313cd1b86adb66fbde40c6e33a2164`

## Behavior

Native `DocxReader` paragraph property parsing now preserves bounded WordprocessingML paragraph policy flags as reviewer metadata spans:

- `w:keepLines`
- `w:widowControl`
- `w:contextualSpacing`
- `w:mirrorIndents`
- `w:suppressLineNumbers`
- `w:suppressAutoHyphens`
- `w:snapToGrid`

Present on/off elements emit `docx-paragraph-policy` plus a policy-specific class, with `-off` suffixes and `data-docx-*="false"` attributes for explicit false values. This keeps the metadata visible in AST, Markdown, and WordPress block handoff without shelling out to office tooling.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes existed before this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3380 assertions, 0 failures`.
- Red-first: same command failed with `1 test files, 3304 assertions, 2 failures` before implementation/count correction because paragraph policy metadata was not emitted and one unrelated count expectation had been disturbed during fixture expansion.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3392 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed.
- Syntax and diff verification are recorded in the final handoff response.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage` fixtures, `DocxReader` WordprocessingML property parsing, `MarkdownWriter`, `WordPressBlockWriter`, and focused DOCX tests. The local Pandoc upstream checkout was not hydrated for this lane, and no Pandoc, Word, LibreOffice, zip/unzip, Cabal/Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat existing DOCX paragraph alignment, spacing, indent, tabs, border, frame/drop-cap, bidi/text-direction, tracked-formatting-change, content-control, media, notes/comments, table, field, OMML, or embedded object/package handoff coverage. It owns only paragraph policy on/off flags in `w:pPr`.

## Follow-Up

A next DOCX/OpenXML slice could cover style-based default paragraph policy inheritance, numbering restart metadata, or additional DrawingML/VML object provenance.
