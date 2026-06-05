# pandoc-epub3-package-core-current-base-20260605T031749Z

Base: `621a8b76d68a905b8db1f97bafe75a9fff0af16c`

Source truth:

- Existing lane package contract: native PHP ZIP/package primitives only; do
  not shell out to Pandoc, zip/unzip, browsers, media players, handler
  runtimes, or online services.
- EPUB3 OPF `bindings` declare media-type handlers for custom/scripted
  resources. WordPress review packets need that handler provenance when a
  non-XHTML spine item is handed off through an XHTML fallback, but this lane
  must not execute the handler.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- Added bounded OPF `<bindings><mediaType .../></bindings>` parsing to
  `EpubReader`.
- Binding items now report custom media type, handler manifest id, resolved
  handler href/target/part/media type/properties, byte length/CRC when present,
  exposure/encryption state, and diagnostics.
- Missing handler manifest ids remain review diagnostics instead of conversion
  blockers.
- Matching binding metadata is attached to spine items and the resulting
  fallback raw HTML AST block so WordPress import review can audit scripted
  handler provenance without executing it.
- Updated the WordPress EPUB3 handoff smoke to cover a valid slideshow handler
  binding and a missing widget handler diagnostic.

Focused evidence:

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 271 assertions, 0 failures`
- Red-first after adding OPF binding expectations:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 272 assertions, 1 failures`
  - Failure: `result["bindings"]` was absent.
- Focused rerun after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 295 assertions, 0 failures`
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6327 assertions, 0 failures`
- PASS-line count from captured full lane run:
  `rg -c '^PASS ' /tmp/pandoc-lane-tests-epub3-bindings.log`
  - Result: `572`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`
- Syntax checks:
  `php -l lanes/pandoc/src/EpubReader.php`
  `php -l lanes/pandoc/tests/EpubReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  - Result: all reported no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`

Status delta:

- `phpPass`: `574 -> 575` by focused EPUB3 PASS-case delta.
- mapped native checks: `1050 -> 1051`
- EPUB3 package focused cases: `12 -> 13`
- EPUB3 package focused assertions: `271 -> 295`
- This slice adds `+1` EpubReader PASS case and `+24` focused EpubReader
  assertions over the accepted baseline.

Dependency closure:

- No new support component is needed. This reuses existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `AstNode`,
  `MarkdownWriter`, and `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat accepted EPUB3 OCF mimetype/container validation,
  metadata/manifest/spine parsing, direct XHTML spine handoff, nav/NCX,
  landmarks/page-list navigation, OPF guide/collections, alternate renditions,
  OCF encryption/obfuscated-font preflight, SMIL media-overlay parsing,
  remote-reference retention, OPF fallback-chain resolution, or asset export
  reporting.
- This adds only bounded OPF binding-handler reporting and static diagnostics
  for custom/scripted media-type handlers.

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

- Keep XHTML-to-AST conversion, CSS cascade/resource policy, richer media
  extraction/export beyond bounded attachment reporting, package-level
  remote-resource policy, handler execution policy beyond static OPF binding
  diagnostics, multiple-rendition selection UX, and broader EPUBCheck-style
  validation as separate bounded EPUB slices.
