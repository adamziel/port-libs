# pandoc-epub3-package-core-current-base-20260605T011736Z

Base: `c6112ce2e1611534e43d39ec57fc44e1f843be3a`

Source truth:

- Existing lane package contract: use native PHP ZIP/package primitives and do
  not shell out to Pandoc, zip/unzip, browsers, media players, office tools, or
  online services.
- EPUB navigation documents, legacy NCX files, and SMIL media overlays can
  carry package-internal references plus remote review/source references. The
  remote references must stay visible to a WordPress import review queue without
  being fetched or misclassified as missing package parts.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- Extended `EpubReader` navigation parsing so XHTML nav `href` values are
  classified through the package-reference policy. Internal targets keep their
  resolved package part/existence state, while absolute/protocol-relative
  targets are retained as `external-nav-reference` diagnostics without fetching.
- Extended legacy NCX `content src` parsing with the same external-reference
  policy and `external-ncx-reference` diagnostics.
- Extended SMIL media-overlay textref/text/audio reference handling so remote
  text and audio targets are retained with `external-media-overlay-reference`
  diagnostics. External audio remains unavailable as package bytes.
- Updated the WordPress EPUB3 package handoff example so its self-test covers
  remote nav, NCX, and media-overlay audio references alongside the accepted
  OCF/OPF/spine/nav/guide/encryption/media-overlay/XHTML handoff.

Focused evidence:

- Baseline `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 162 assertions, 0 failures`
- Red-first `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  after adding the remote-reference test:
  - Result: failed because an external nav `href` still reached the internal
    OPC resolver: `1 test files, 162 assertions, 1 failures`
- Focused rerun `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 186 assertions, 0 failures`
- Full focused lane `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5095 assertions, 0 failures`
- Example smoke `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`

Status delta:

- `phpPass`: `493 -> 494`
- mapped native checks: `967 -> 968`
- EPUB3 package focused cases: current source records `9` cases and `186`
  focused assertions; this slice contributes `+1` case and `+24` assertions
  over the accepted `EpubReaderTest.php` baseline.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `XmlHtmlDom`, `AstNode`,
  and `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat accepted EPUB3 OCF mimetype/container, OPF metadata/
  manifest/spine, TOC/NCX internal target parsing, typed landmarks/page-list
  navigation, missing asset reporting, raw XHTML spine handoff, OCF encryption/
  obfuscated-font preflight, SMIL local media-overlay extraction, OPF guide, or
  OPF collection metadata. It adds only unfetched remote-reference handling for
  nav, NCX, and SMIL review links.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, media players, roff, decryption helpers,
  font deobfuscators, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep XHTML-to-AST conversion, EPUB media extraction/export policy, multiple
  rendition selection, encrypted spine-resource blocking, and CSS cascade
  handling as separate bounded EPUB slices.
