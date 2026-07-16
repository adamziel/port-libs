# pandoc-docx-openxml-core-current-base-20260604T234542Z

Base: `57058b982e38efb74137da09319fa7203abc89a4`

Source truth:

- Existing lane DOCX/OpenXML contract: parse WordprocessingML from OPC ZIP
  packages into the shared AST and import report without shelling out to
  Pandoc, Word, LibreOffice, zip/unzip, browser renderers, or online services.
- OpenXML `w:altChunk` references an alternative-format import relationship
  (`aFChunk`) whose target part may contain reviewable HTML. This bounded slice
  imports only internal `text/html` and `application/xhtml+xml` chunk parts.
- Missing chunks, external chunk targets, and unsupported formats such as RTF
  remain explicit review diagnostics instead of being fetched, converted, or
  silently rendered.
- The pinned upstream Pandoc checkout was not locally hydrated in this isolated
  worktree, so this is bounded native OpenXML support, not Haskell runner
  parity.

Implementation:

- `DocxReader` now recognizes body-level `w:altChunk` nodes while walking block
  containers.
- Internal HTML/XHTML `aFChunk` targets resolve through the document OPC
  relationships, are parsed by the existing safe `XmlHtmlDom` HTML fragment
  loader, and become `raw_html` AST blocks with relationship/content-type
  provenance.
- `readPackage()` now exposes `importReport['alternativeFormats']` with count,
  imported, missing, external, unsupported, byte, text, and issue fields.
- The WordPress DOCX handoff example now carries a reviewer HTML chunk and
  self-tests both raw HTML output and the alternative-format import report.

Focused evidence:

- Red-first `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  after adding the new fixture failed before implementation:
  `Expected: 3`, `Actual: 2`, with `1 test files, 335 assertions, 1 failures`.
- After implementation, `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 368 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `16 test files, 4,080 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests | rg '^PASS ' | wc -l`
  reported `410` runner PASS lines.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  reported `docx body handoff self-test ok`.
- `php -l lanes/pandoc/src/DocxReader.php`
  reported no syntax errors.
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  reported no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  reported no syntax errors.

Status delta:

- `phpPass`: `417 -> 418` by one focused DOCX/OpenXML PHP PASS case in the
  lane status convention.
- Mapped native checks: `882 -> 883`.
- DOCX/OpenXML focused cases: `31 -> 32`.
- DOCX/OpenXML focused assertions: `334 -> 368` for `DocxReaderTest.php`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, OPC content-types/relationship graph, `DocxReader` XML parser,
  `XmlHtmlDom` safe HTML fragment parser/serializer, Markdown writer, and
  WordPress block writer paths.

Exclusions:

- Did not execute Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX,
  Biber, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF
  engines, MathJax, KaTeX, Typst, browser renderers, roff, or online services.
- Did not fetch external altChunk targets or convert RTF/Office chunks.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep full-document HTML altChunk body extraction, plain-text altChunk
  mapping, external altChunk fetch policy, RTF altChunk conversion policy,
  charts/diagrams, nested numbering, and richer header/footer selection policy
  as separate bounded DOCX/OpenXML slices.
