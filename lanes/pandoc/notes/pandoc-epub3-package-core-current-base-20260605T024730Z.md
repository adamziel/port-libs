# pandoc-epub3-package-core-current-base-20260605T024730Z

Base: `93ff2a1225d594c3864b3222b381965462c18bba`

Source truth:

- Existing lane package contract: use native PHP ZIP/package primitives and do
  not shell out to Pandoc, zip/unzip, browsers, media players, office tools, or
  online services.
- EPUB OPF manifests are the package authority for publication resources, and
  cover images can be identified by EPUB3 `properties="cover-image"` and by
  legacy OPF `<meta name="cover" content="...">` metadata. WordPress review
  queues need safe attachment candidates and visible diagnostics for
  non-structural package resources that are present but not declared in OPF.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- Extended `EpubReader` asset reporting while preserving the existing top-level
  `assets` list shape for non-XHTML manifest items.
- `importReport.assets` now includes the manifest asset list, cover image
  report, attachment candidate list/count, safe SHA-256 hashes for exposeable
  package bytes, and unmanifested non-structural ZIP resources.
- Manifest assets now carry `href`, `byteSha256`, `role`, `isCoverImage`,
  `coverImageSources`, `exportCandidate`, `attachmentCandidate`, and
  `attachmentRole` fields.
- Unmanifested package resources are reported with inferred media type,
  byte length, CRC32, SHA-256, attachment candidacy, and a
  `unmanifested-package-resource` diagnostic. EPUB structural parts such as
  `mimetype`, `META-INF/*`, and OPF package documents are excluded.
- Updated the WordPress EPUB handoff smoke so Data Liberation review packets
  expose cover attachment metadata and an undeclared image audit item.

Focused evidence:

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 245 assertions, 0 failures`
- Red-first after adding the asset-export test:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 247 assertions, 1 failures`
  - Failure: `importReport.assets.coverImage` was absent.
- Focused rerun after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 271 assertions, 0 failures`
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5944 assertions, 0 failures`
- PASS-line count:
  `rg -c '^PASS ' /tmp/pandoc-lane-tests-epub3-assets.log`
  - Result: `551`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`

Status delta:

- `phpPass`: `550 -> 551`
- mapped native checks: `1029 -> 1030`
- EPUB3 package focused cases: `11 -> 12`
- EPUB3 package focused assertions: `245 -> 271`
- This slice itself adds `+1` EpubReader PASS case and `+26` focused
  EpubReader assertions over the accepted baseline.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `AstNode`,
  `MarkdownWriter`, and `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat accepted EPUB3 OCF mimetype/container validation,
  metadata/manifest/spine parsing, direct XHTML spine handoff, nav/NCX,
  landmarks/page-list navigation, OPF guide/collections, alternate renditions,
  OCF encryption/obfuscated-font preflight, SMIL media-overlay parsing,
  remote-reference retention, or OPF fallback-chain resolution.
- This adds only bounded package asset-export reporting for cover images, safe
  manifest resource hashes, and unmanifested non-structural package resources.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, media players, roff, decryption helpers,
  font deobfuscators, remote media fetches, or online services.
- Root harness was not run for this isolated micro-slice.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep richer XHTML-to-AST conversion, broader EPUB media extraction/export
  beyond bounded cover/unmanifested reporting, OPF bindings/scripted handler
  audit, CSS cascade handling, and remote-resource policy beyond unfetched
  diagnostics as separate bounded EPUB slices.
