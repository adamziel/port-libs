# pandoc-epub3-package-core-current-base-20260604T231523Z

Base: `fd0f5327abfd3b58715219a1c13c4c8295941253`

Source truth:

- Existing lane package contract: use native PHP ZIP/package primitives and do
  not shell out to Pandoc, zip/unzip, browsers, office tools, or online
  services.
- EPUB3 navigation documents may carry multiple `nav` sections identified by
  `epub:type`, including `toc`, `landmarks`, and `page-list`. Landmark and
  page-list links are package-relative review targets and may also carry their
  own `epub:type` tokens.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- Extended `EpubReader` navigation parsing to preserve all typed EPUB3 nav
  sections while keeping the accepted `nav.items` TOC output for existing
  callers.
- Added direct `nav.landmarks` and `nav.pageList` lists with resolved package
  targets, item titles, first item type, full item type tokens, and nested
  child lists.
- Updated the WordPress EPUB handoff smoke so review packets expose landmark
  and page-list targets alongside TOC/NCX targets and raw XHTML spine blocks.

Focused evidence:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 87 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `13 test files, 3,723 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`
- `php -l lanes/pandoc/src/EpubReader.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  - Result: no syntax errors

Status delta:

- `phpPass`: `389 -> 390`
- mapped native checks: `846 -> 848`
- EPUB3 package focused cases: `4 -> 5`
- EPUB3 package focused assertions: `62 -> 87`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `AstNode`,
  `MarkdownWriter`, and `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat the accepted EPUB3 OCF mimetype/container, OPF
  metadata/manifest/spine, TOC/NCX target, missing asset, or raw XHTML spine
  handoff. It adds only typed EPUB3 `landmarks` and `page-list` navigation
  extraction on top of that package reader.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, roff, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep XHTML-to-AST conversion, EPUB media extraction/export policy,
  remote-resource policy, multiple rendition selection, SMIL media overlays,
  encrypted/obfuscated font preflight, and CSS cascade handling as separate
  bounded EPUB slices.
