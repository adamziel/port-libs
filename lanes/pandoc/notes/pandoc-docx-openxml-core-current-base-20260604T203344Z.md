# pandoc-docx-openxml-core-current-base-20260604T203344Z

Base: `35c72aa962dd0961a1659b734a4b80c91d092fff`

Source truth:

- Existing lane DOCX/OpenXML contract: parse WordprocessingML from OPC ZIP
  packages into the shared AST and import report without shelling out to
  Pandoc, Word, LibreOffice, zip/unzip, or online services.
- OpenXML `w:sectPr` carries section-level page geometry and layout metadata:
  `w:pgSz`, `w:pgMar`, `w:cols`, `w:headerReference`, and
  `w:footerReference`.
- The pinned upstream Pandoc checkout was not locally hydrated in this isolated
  worktree, so this is bounded native OpenXML support, not Haskell runner
  parity.

Implementation:

- `DocxReader` now collects paragraph-level and body-level `w:sectPr` nodes
  into `document->attr('sectionProperties')`.
- Section metadata includes page width/height/orientation, margins, column
  count/equal-width/spacing, and header/footer relationship ids resolved
  through the existing OPC relationship graph.
- `readPackage()` exposes the same data through `importReport['sections']` for
  WordPress import review tooling.
- The WordPress DOCX handoff example now includes a landscape two-column
  section with default header/footer relationship targets and self-test
  assertions.

Focused evidence:

- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 313 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `11 test files, 3,500 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`
- `php -l lanes/pandoc/src/DocxReader.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors

Status delta:

- `phpPass`: `369 -> 370`
- mapped native checks: `826 -> 827`
- DOCX/OpenXML focused cases: `30 -> 31`
- DOCX/OpenXML focused assertions: `271 -> 313`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, OPC relationship graph, DOCX XML parser, Markdown writer, and
  WordPress block writer paths.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  zip/unzip, external template engines, TeX/PDF engines, MathJax, KaTeX,
  Typst, browser renderers, roff, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep actual header/footer body import, richer DOCX page-style handling,
  nested numbering, non-hyperlink field-code interpretation, charts/diagrams,
  and media export policy as separate bounded DOCX/OpenXML slices.
