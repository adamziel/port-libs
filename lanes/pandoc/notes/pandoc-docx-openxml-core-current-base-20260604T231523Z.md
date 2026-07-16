# pandoc-docx-openxml-core-current-base-20260604T231523Z

Base: `fd0f5327abfd3b58715219a1c13c4c8295941253`

Source truth:

- Existing lane DOCX/OpenXML contract: parse WordprocessingML from OPC ZIP
  packages into the shared AST and import report without shelling out to
  Pandoc, Word, LibreOffice, zip/unzip, or online services.
- OpenXML `w:sectPr` `w:headerReference` and `w:footerReference` targets are
  WordprocessingML parts (`w:hdr` / `w:ftr`) that can carry paragraphs,
  hyperlinks, and other reviewable content.
- The previous DOCX section-property slice only surfaced header/footer
  relationship metadata. This slice imports the referenced header/footer body
  parts themselves.
- The pinned upstream Pandoc checkout was not locally hydrated in this isolated
  worktree, so this is bounded native OpenXML support, not Haskell runner
  parity.

Implementation:

- `DocxReader` now reuses the body block walker for `w:hdr` and `w:ftr` parts
  referenced from section properties.
- Header/footer section references keep the existing relationship fields and
  add `exists`, parsed `blocks`, and plain `text` for internal package parts.
- Header/footer parts load their own OPC relationship part when present, so a
  hyperlink inside `word/header1.xml` resolves through
  `word/_rels/header1.xml.rels`.
- The WordPress DOCX handoff example now includes a section header hyperlink
  and footer text in its self-test.

Focused evidence:

- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 334 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `13 test files, 3,719 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`
- `php -l lanes/pandoc/src/DocxReader.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors

Status delta:

- `phpPass`: `389 -> 390`
- mapped native checks: `846 -> 847`
- DOCX/OpenXML focused cases: `31 -> 32`
- DOCX/OpenXML focused assertions: `313 -> 334`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, OPC relationship graph, DOCX XML parser, Markdown writer, and
  WordPress block writer paths.

Exclusions:

- Did not execute Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
  zip/unzip, external template engines, TeX/PDF engines, MathJax, KaTeX,
  Typst, browser renderers, roff, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep richer header/footer constructs such as images, tables, nested
  numbering, fields beyond hyperlinks, and first/even/default section
  selection policy as separate bounded DOCX/OpenXML slices.
