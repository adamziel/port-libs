# pandoc-epub3-package-core-current-base-20260605T004718Z

Base: `0fe8739ce5356d5a3078fe470f44492bd5ad212c`

Source truth:

- Existing lane package contract: use native PHP ZIP/package primitives and do
  not shell out to Pandoc, zip/unzip, browsers, media players, office tools, or
  online services.
- EPUB OPF package metadata can include legacy `guide` references and EPUB3
  `collection` groups. Those are package-local review/navigation hints rather
  than spine content; missing or external optional targets should be reported
  without dropping valid XHTML spine handoff.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- Extended `EpubReader` with OPF `guide` parsing. Guide references now preserve
  type, title, href, resolved target/part, package existence, ZIP byte metadata,
  matched manifest id/media type, encryption exposure flags, and diagnostics.
- Added nested OPF `collection` parsing with collection id/role/language/dir,
  collection metadata via the existing OPF metadata reader, internal link
  resolution, manifest matching, and recursive child collections.
- Added optional-package-reference diagnostics for missing guide targets and
  external collection links. External collection links are retained as review
  metadata and are not fetched.
- Exposed `guide` and `collections` at the top level, in `importReport`, and on
  the generated document AST attributes.
- Updated `wordpress-epub3-package-handoff.php` so the local smoke self-tests
  OPF guide references and collection metadata alongside the accepted OPF,
  nav/NCX, encryption, media-overlay, asset, and raw XHTML handoff.

Focused evidence:

- Baseline `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 121 assertions, 0 failures`
- Red-first `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  after adding the OPF guide/collection test:
  - Result: failed in the new guide case because `result[guide]` was missing:
    `1 test files, 122 assertions, 1 failures`
- Focused rerun `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 162 assertions, 0 failures`
- Full focused lane `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4813 assertions, 0 failures`
- Example smoke `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/EpubReader.php`
  - `php -l lanes/pandoc/tests/EpubReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  - Result: no syntax errors
- JSON status/manifest parse:
  - `php -r "json_decode(file_get_contents('lanes/pandoc/lane-status.json'), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR); echo 'pandoc json ok'.PHP_EOL;"`
  - Result: `pandoc json ok`
- Whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors

Status delta:

- `phpPass`: `477 -> 478`
- mapped native checks: `950 -> 951`
- EPUB3 package focused cases: current source catch-up now records `8` cases
  and `162` assertions; this slice contributes `+1` case and `+41` assertions
  over the accepted `EpubReaderTest.php` baseline.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `XmlHtmlDom`, `AstNode`,
  `MarkdownWriter`, and `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat the accepted EPUB3 OCF mimetype/container, OPF
  metadata/manifest/spine, TOC/NCX target, typed landmarks/page-list
  navigation, missing asset, raw XHTML spine handoff, OCF encryption/
  obfuscated-font preflight, or SMIL media-overlay extraction. It adds only OPF
  guide and collection review metadata.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, media players, roff, decryption helpers,
  font deobfuscators, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep XHTML-to-AST conversion, EPUB media extraction/export policy,
  remote-resource policy, multiple rendition selection, encrypted
  spine-resource blocking, and CSS cascade handling as separate bounded EPUB
  slices.
