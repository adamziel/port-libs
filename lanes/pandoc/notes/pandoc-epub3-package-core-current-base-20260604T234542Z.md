# pandoc-epub3-package-core-current-base-20260604T234542Z

Base: `57058b982e38efb74137da09319fa7203abc89a4`

Source truth:

- Existing lane package contract: use native PHP ZIP/package primitives and do
  not shell out to Pandoc, zip/unzip, browsers, office tools, decryption
  helpers, font deobfuscators, or online services.
- EPUB OCF packages may carry `META-INF/encryption.xml` using the OCF
  encryption root and XML Encryption `EncryptedData` entries whose
  `CipherReference URI` points at package resources. The IDPF embedding
  algorithm identifies obfuscated font resources that must be surfaced for
  review rather than exposed as ordinary readable asset bytes.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- Extended `EpubReader` to parse `META-INF/encryption.xml` through the existing
  safe XML loader.
- The reader now returns a top-level and import-report `encryption` section
  with encrypted resource parts, manifest ids, media types, algorithms,
  diagnostics, and `obfuscatedFonts` classification.
- Matching OPF manifest entries and non-XHTML asset reports now carry
  `encrypted`, `canExposeBytes`, and `encryption` fields so import review can
  distinguish readable package resources from encrypted or obfuscated bytes.
- Encrypted nav/NCX/XHTML package parts are not read as ordinary source markup;
  this slice verifies the bounded obfuscated-font case while preserving readable
  XHTML spine handoff.
- Updated `wordpress-epub3-package-handoff.php` so the local WordPress smoke
  exposes obfuscated-font review metadata alongside the existing TOC, landmark,
  page-list, asset, and XHTML block output.

Focused evidence:

- Red-first `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  before implementation:
  - Result: failed in the new OCF encryption preflight case because
    `result[encryption]` was missing.
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 102 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `16 test files, 4061 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`
- `php -l lanes/pandoc/src/EpubReader.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  - Result: no syntax errors
- `php -r "json_decode(file_get_contents('lanes/pandoc/lane-status.json'), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR); echo \"pandoc json ok\\n\";"`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors

Status delta:

- `phpPass`: `417 -> 418`
- mapped native checks: `882 -> 883`
- EPUB3 package focused cases: `5 -> 6`
- EPUB3 package focused assertions: `87 -> 102`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `XmlHtmlDom`, `AstNode`,
  and `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat the accepted EPUB3 OCF mimetype/container, OPF
  metadata/manifest/spine, TOC/NCX target, missing asset, raw XHTML spine
  handoff, landmarks, or page-list extraction. It adds only OCF encryption.xml
  resource preflight and IDPF obfuscated-font review flags.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, roff, decryption helpers, font
  deobfuscators, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep XHTML-to-AST conversion, EPUB media extraction/export policy,
  remote-resource policy, multiple rendition selection, SMIL media overlays,
  encrypted spine-resource blocking, and CSS cascade handling as separate
  bounded EPUB slices.
