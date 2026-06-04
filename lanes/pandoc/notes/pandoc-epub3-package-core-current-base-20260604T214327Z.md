# pandoc-epub3-package-core-current-base-20260604T214327Z

Base: `b71cd6fb809e4ef9d0d33ad21e4e09f9abb6baec`

Source truth:

- Existing lane package contract: use native PHP ZIP/package primitives and do
  not shell out to Pandoc, zip/unzip, browsers, office tools, or online
  services.
- EPUB3 package handoff starts with OCF package structure: the `mimetype` entry
  is first and stored, `META-INF/container.xml` points to an OPF rootfile, OPF
  records metadata/manifest/spine state, `properties="nav"` identifies the
  EPUB3 navigation document, and legacy `spine toc="..."` can reference NCX.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- Added `EpubReader`.
- The reader validates EPUB `mimetype` placement/compression/extra-field
  constraints, loads `META-INF/container.xml`, resolves the OPF rootfile, and
  parses OPF title/creator/language/identifier/modified/cover metadata.
- Manifest entries are resolved relative to the OPF part, spine itemrefs are
  checked against package entries, EPUB3 nav XHTML and legacy NCX targets are
  resolved recursively, and missing non-spine assets are reported without
  dropping valid XHTML spine content.
- XHTML spine items are handed to the shared AST as `raw_html` nodes so the
  existing Markdown and WordPress block writers can preserve source content
  for review.
- Added `wordpress-epub3-package-handoff.php` as a local smoke for Data
  Liberation EPUB review packets.

Focused evidence:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 62 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`
- `php -l lanes/pandoc/src/EpubReader.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  - Result: no syntax errors

Status delta:

- `phpPass`: `378 -> 382`
- mapped native checks: `835 -> 839`
- EPUB3 package focused cases: `0 -> 4`
- EPUB3 package focused assertions: `0 -> 62`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `AstNode`,
  `MarkdownWriter`, and `WordPressBlockWriter` paths.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, roff, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep XHTML-to-AST conversion, EPUB media extraction/export policy,
  remote-resource policy, multiple rendition selection, landmarks/page-list
  navigation, SMIL media overlays, encrypted/obfuscated font preflight, and CSS
  cascade handling as separate bounded EPUB slices.
