# pandoc-epub3-package-core-current-base-20260605T035109Z

Base: `538c88716b104335d6dc0713aa79af39ad7bf148`

Source truth:

- Existing lane package contract: native PHP ZIP/package primitives only; do
  not shell out to Pandoc, zip/unzip, browsers, media players, handler
  runtimes, remote fetchers, or online services.
- W3C EPUB 3.3 defines publication resources as manifest-listed resources and
  explicitly allows remote resources outside the EPUB container:
  `https://www.w3.org/TR/epub-33/`.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- `EpubReader` now treats absolute or authority-form OPF manifest `href`
  values as remote publication resources instead of invalid package paths.
- Remote manifest resources are retained in `manifest.items`,
  `manifest.externalItems`, and asset reports with an
  `external-manifest-resource` diagnostic.
- Remote resources are excluded from `manifest.missingItems`, are never read
  from the ZIP package, and do not expose byte hashes or attachment candidates.
- Manifest-by-part and encryption lookups now skip remote resources with no
  package part, avoiding accidental empty-string part keys.
- The WordPress EPUB3 handoff smoke now covers a remote OPF audio resource and
  verifies it stays unfetched and separate from missing ZIP package assets.

Focused evidence:

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 295 assertions, 0 failures`
- Focused rerun after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 318 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`
- Syntax checks:
  `php -l lanes/pandoc/src/EpubReader.php`
  `php -l lanes/pandoc/tests/EpubReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  - Result: all reported no syntax errors.
- Full focused lane directory attempt:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6632 assertions, 1 failures`
  - Residual failure: unrelated
    `lanes/pandoc/tests/MarkdownReaderTest.php` structured HTML table-footer
    assertion expects a `<tfoot>` row without cell ids, while the current
    output preserves `id="total-population"` and `id="total-area"`.

Status delta:

- `phpPass`: `595 -> 596` by focused EPUB3 PASS-case delta.
- mapped native checks: `1069 -> 1070`
- EPUB3 package focused cases reconciled to `14`.
- EPUB3 package focused assertions: `295 -> 318`.
- This slice adds `+1` EpubReader PASS case and `+23` focused EpubReader
  assertions over the accepted baseline.

Dependency closure:

- No new support component is needed. This reuses existing native `ZipPackage`,
  `ZipPackageEntry`, `OpcPackagePath`, `AstNode`, `MarkdownWriter`, and
  `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat accepted EPUB3 OCF mimetype/container validation,
  metadata/manifest/spine parsing, direct XHTML spine handoff, nav/NCX,
  landmarks/page-list navigation, OPF guide/collections, alternate renditions,
  OCF encryption/obfuscated-font preflight, SMIL media-overlay parsing,
  remote nav/NCX/SMIL reference retention, OPF fallback-chain resolution,
  package asset export reporting, or OPF binding-handler reporting.
- This adds only bounded OPF manifest remote-resource reporting and static
  diagnostics for remote publication resources.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, media players, handler runtimes, remote
  fetches, roff, decryption helpers, font deobfuscators, online sanitizers, or
  online services.
- Root harness was not run for this isolated micro-slice.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep richer XHTML-to-AST conversion, CSS cascade/resource policy,
  remote-resource fetching/security policy beyond unfetched diagnostics,
  richer media extraction/export beyond bounded attachment reporting, handler
  execution policy beyond static OPF binding diagnostics, multiple-rendition
  selection UX, and broader EPUBCheck-style validation as separate bounded
  EPUB slices.
