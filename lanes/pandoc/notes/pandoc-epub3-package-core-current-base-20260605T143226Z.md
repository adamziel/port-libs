# pandoc-epub3-package-core-current-base-20260605T143226Z

Base: `381798fcad9b34f8ddd3161bb0f61bf77da880ad`

Source truth:

- EPUB package readers need to preserve EPUB CFI URI fragments such as
  `#epubcfi(...)` as addressable intra-publication targets, not flatten them
  into opaque id fragments or drop them during WordPress review handoff.
- This is bounded native PHP EPUB3 package support. It does not attempt full
  EPUB CFI grammar validation or reading-system layout resolution.

Implementation:

- Added additive fragment metadata to `EpubReader` target reports:
  `fragment`, `fragmentKind`, and bounded `epubCfi` details.
- The fields now flow through OPF manifest/metadata links, guide references,
  collection links, EPUB3 nav and NCX targets, page-list/page-break reports,
  SMIL media-overlay text references, XHTML content-reference scans, navigation
  coverage summaries, and raw HTML AST block attributes.
- Navigation and page-break reports now include compact CFI summary counts so a
  WordPress import review queue can find CFI-based targets without scanning
  every link.
- Updated the WordPress EPUB3 package handoff smoke to include a nested CFI TOC
  entry and assert the CFI path remains visible in document navigation metadata.

Focused evidence:

- Red-first `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  before implementation:
  - Result: failed in the new CFI case because `fragment` / `fragmentKind`
    metadata was absent; the failing focused run reported
    `1 test files, 901 assertions, 1 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 936 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`
- `php -l lanes/pandoc/src/EpubReader.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  - Result: no syntax errors
- `php -r "json_decode(file_get_contents('lanes/pandoc/lane-status.json'), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR); echo \"pandoc json ok\n\";"`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors

Status delta:

- `phpPass`: `947 -> 948`
- mapped native checks: `1402 -> 1403`
- EPUB3 package focused cases: `4 -> 5`
- EPUB3 package focused assertions: `62 -> 99`
- Focused `EpubReaderTest.php`: `899 -> 936` assertions, adding 1 PASS case /
  37 assertions.

Dependency closure:

- No new support component is needed. This reuses native `EpubReader`,
  `ZipPackage`, `OpcPackagePath`, `AstNode`, and `WordPressBlockWriter`
  behavior.
- Full upstream Pandoc runner parity remains gated on hydrating the pinned
  Pandoc checkout and Cabal test executables described in lane status.

Non-overlap:

- This does not repeat accepted EPUB OCF mimetype/container, OPF
  metadata/manifest/spine, nav/NCX id targets, missing asset diagnostics,
  raw XHTML spine handoff, guide/collection metadata, multiple renditions,
  SMIL timing, OCF encryption, OCF rights/signatures, remote-resource
  reconciliation, page-spread, or page-list extraction. It adds only bounded
  `epubcfi(...)` fragment classification and propagation.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, ZipArchive, EPUB validators, external template engines,
  TeX/PDF engines, MathJax, KaTeX, Typst, browser renderers, roff, decryption
  helpers, font deobfuscators, or online services.
- Root harness not run - isolated micro-slice.

Next:

- Keep full EPUB CFI parser parity, XHTML-to-AST conversion, EPUB media
  extraction/export policy, CSS cascade handling, remote-resource policy,
  rendition selection, and reading-system layout behavior as separate bounded
  EPUB slices.
