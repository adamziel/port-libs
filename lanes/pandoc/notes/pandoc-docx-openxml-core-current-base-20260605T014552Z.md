# pandoc-docx-openxml-core-current-base-20260605T014552Z

Base: `b828ac3b472ad91b3570084ccb5b89f5b3613216`

Source truth:

- Existing lane DOCX/OpenXML contract: parse WordprocessingML from OPC ZIP
  packages into the shared AST and WordPress handoff without shelling out to
  Pandoc, Word, LibreOffice, zip/unzip, browser renderers, or online services.
- WordprocessingML move tracking uses `w:moveFrom` for the old source location
  and `w:moveTo` for the accepted destination. This is the same redline family
  as `w:del` and `w:ins`, so the bounded PHP reader should suppress moved-from
  source text, render moved-to destination text, and keep reviewer metadata in
  the import report.
- The pinned upstream Pandoc checkout was not locally hydrated in this
  isolated worktree, so this remains bounded native OpenXML support, not
  Haskell runner parity.

Implementation:

- `DocxReader` now maps inline `w:moveTo` content to a `span` with
  `.docx-move-to`, `data-docx-change="move-to"`, and the existing tracked
  change id/author/date attributes.
- Inline `w:moveFrom` content is suppressed from the rendered AST, Markdown,
  and WordPress block output, matching the existing deleted-text policy.
- The DOCX import report now records `move-from` and `move-to` revision items,
  with `move-to` counted as accepted inserted content and `move-from` counted
  as suppressed deleted content.
- The WordPress DOCX body handoff example now carries moved-from and moved-to
  review text and asserts that only the moved-to text renders.

Focused evidence:

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 461 assertions, 0 failures`.
- Red-first after adding the move-tracking test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  failed with `1 test files, 463 assertions, 1 failures` because the moved
  destination was dropped with the moved-from source.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 490 assertions, 0 failures`.
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  passed with `19 test files, 5362 assertions, 0 failures` and `508` PASS
  lines.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  reported `docx body handoff self-test ok`.
- Syntax:
  `php -l lanes/pandoc/src/DocxReader.php`,
  `php -l lanes/pandoc/tests/DocxReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php` all reported
  no syntax errors.

Status delta:

- `phpPass`: `509 -> 510`.
- Mapped native checks: `984 -> 985`.
- DOCX/OpenXML mapped cases: `31 -> 32`.
- DOCX/OpenXML focused assertions: `313 -> 342`.
- Focused `DocxReaderTest.php` assertions: `461 -> 490`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, OPC relationship graph, `DocxReader`, `MarkdownWriter`, and
  `WordPressBlockWriter` paths.

Exclusions:

- Did not execute Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX,
  Biber, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF
  engines, MathJax, KaTeX, Typst, browser renderers, roff, or online services.
- Did not implement cross-paragraph move range stitching, overlapping move
  ranges, Word revision balloons, author-specific accept/reject policy,
  charts/diagrams, nested numbering, or richer media extraction/export policy.
- Root harness not run for this isolated micro-slice.

Next:

- Keep cross-paragraph revision ranges, richer revision accept/reject policy,
  charts/diagrams, nested numbering, and richer media extraction/export policy
  as separate bounded DOCX/OpenXML slices.
