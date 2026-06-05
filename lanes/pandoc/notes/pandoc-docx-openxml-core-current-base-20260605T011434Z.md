# pandoc-docx-openxml-core-current-base-20260605T011434Z

Base: `36037135286fcdbc8bac174ffee0996de01721a0`

Source truth:

- Existing lane DOCX/OpenXML contract: parse WordprocessingML from OPC ZIP
  packages into the shared AST and WordPress handoff without shelling out to
  Pandoc, Word, LibreOffice, zip/unzip, browser renderers, or online services.
- OpenXML `w:altChunk` references an alternative-format import relationship.
  The accepted DOCX slice already handles HTML/XHTML chunks and reports
  missing, external, and unsupported chunks.
- This slice ports the bounded text/plain branch of the same alternative-format
  contract: embedded plain-text chunks are package parts, not external fetches
  or conversion jobs, so they can be decoded and surfaced directly.
- The pinned upstream Pandoc checkout was not locally hydrated in this
  isolated worktree, so this remains bounded native OpenXML support, not
  Haskell runner parity.

Implementation:

- `DocxReader` now recognizes `text/plain` altChunk content types, including
  `charset` parameters.
- Plain-text chunk bytes are decoded through the existing `UnicodeText`
  helper, preserving BOM, repair count, and line-ending normalization metadata
  in the DOCX import report.
- Decoded text is mapped to normal paragraph AST nodes. Blank lines split
  paragraphs and single line breaks become `linebreak` inline nodes, so
  WordPress handoff keeps source line boundaries without raw HTML.
- The existing HTML/XHTML altChunk behavior, unsupported-content reporting,
  external fetch exclusion, and missing-part reporting remain unchanged.
- The WordPress DOCX body handoff example now includes both an HTML altChunk
  and a plain-text altChunk.

Focused evidence:

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 433 assertions, 0 failures`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 461 assertions, 0 failures`.
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  passed with `pass_lines=489` and
  `19 test files, 5071 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  reported `docx body handoff self-test ok`.

Status delta:

- `phpPass`: unchanged at `491`, because this extends an existing focused DOCX
  PASS case rather than adding a new PASS line.
- Mapped native checks: `965 -> 966`.
- DOCX/OpenXML mapped cases: `31 -> 32`.
- DOCX/OpenXML focused assertions: `433 -> 461` for `DocxReaderTest.php`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, OPC relationship graph, `DocxReader`, `UnicodeText`,
  Markdown writer, and WordPress block writer paths.

Exclusions:

- Did not execute Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX,
  Biber, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF
  engines, MathJax, KaTeX, Typst, browser renderers, roff, or online services.
- Did not implement full-document HTML altChunk body extraction, external
  altChunk fetch policy, RTF altChunk conversion, charts/diagrams, nested
  numbering, or richer header/footer selection policy.
- Root harness not run for this isolated micro-slice.

Next:

- Keep full-document HTML altChunk body extraction, external altChunk fetch
  policy, RTF altChunk conversion, charts/diagrams, nested numbering, and
  richer header/footer selection policy as separate bounded DOCX/OpenXML
  slices.
