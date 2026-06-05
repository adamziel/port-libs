# pandoc-epub3-package-core-current-base-20260605T014653Z

Base: `1a86b009041f206dcbfd3ee76c6da99bd9edeeb9`

Source truth:

- Existing lane package contract: use native PHP ZIP/package primitives and do
  not shell out to Pandoc, zip/unzip, browsers, media players, office tools, or
  online services.
- EPUB OCF `container.xml` can list more than one OPF rootfile. WordPress
  review packets need to know which OPF was selected for conversion and which
  alternate renditions exist, especially when alternate OPFs carry
  `rendition:*` layout metadata.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- Extended `EpubReader` OCF container parsing so each rootfile keeps its index
  and selected state.
- Added a top-level `renditions` report, mirrored into `importReport` and the
  document attrs. The selected OPF remains the only conversion source.
- Added lightweight alternate-OPF summaries with title/identifier/language/
  creator/modified metadata, package version, manifest/spine counts, and
  `rendition:*` properties such as `layout`, `orientation`, `spread`, and
  `viewport`.
- Alternate OPF parse/missing failures are retained as diagnostics instead of
  blocking the selected spine conversion.
- Updated the WordPress EPUB3 package handoff smoke so its self-test covers a
  primary reflowable OPF plus an alternate fixed-layout review OPF.

Focused evidence:

- Baseline `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  before edits:
  - Result: `1 test files, 186 assertions, 0 failures`
- Red-first `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  after adding the multiple-rendition expectation:
  - Result: failed on missing rootfile `selected` / `renditions` output:
    `1 test files, 189 assertions, 1 failures`
- Focused rerun `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  after implementation:
  - Result: `1 test files, 219 assertions, 0 failures`
- Broader lane rerun `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5411 assertions, 0 failures`
- Example smoke
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`

Status delta:

- `phpPass`: `512 -> 513`
- mapped native checks: `987 -> 988`
- EPUB3 package focused cases: `9 -> 10`
- EPUB3 package focused assertions: `186 -> 219`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `AstNode`, and
  `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat accepted EPUB3 OCF mimetype validation, selected OPF
  metadata/manifest/spine parsing, internal nav/NCX target parsing, typed
  landmarks/page-list navigation, missing asset reporting, raw XHTML spine
  handoff, OCF encryption/obfuscated-font preflight, SMIL local/remote media
  overlay handling, OPF guide, OPF collections, or remote-reference policy.
  It adds only multi-rootfile rendition reporting and alternate OPF summary
  metadata.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, media players, roff, decryption helpers,
  font deobfuscators, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep XHTML-to-AST conversion, EPUB media extraction/export policy, encrypted
  spine-resource blocking, CSS cascade handling, and richer malformed alternate
  rendition policy as separate bounded EPUB slices.
