# pandoc-epub3-package-core-current-base-20260605T001701Z

Base: `49efe5e00f4494b7b30a09eccd6924405b30abd9`

Source truth:

- Existing lane package contract: use native PHP ZIP/package primitives and do
  not shell out to Pandoc, zip/unzip, browsers, media players, office tools, or
  online services.
- EPUB3 OPF manifest items may reference a SMIL media-overlay item with
  `media-overlay="..."`; the SMIL document carries package-relative `text`
  and `audio` targets plus clip timing that a WordPress import review queue can
  expose without decoding or playing media.
- The pinned upstream Pandoc checkout is not locally hydrated in this isolated
  worktree, so this is bounded native EPUB3 package support, not Haskell runner
  parity.

Implementation:

- Extended `EpubReader` with bounded EPUB3 SMIL media-overlay parsing for
  OPF-referenced `application/smil+xml` manifest items.
- The reader now returns top-level and import-report `mediaOverlays` keyed by
  overlay manifest id, preserving referenced spine item ids, overlay part
  metadata, package-relative `epub:textref`, `par` ids/types, resolved text
  targets, resolved audio targets, audio byte metadata, clip begin/end values,
  and diagnostics.
- Spine entries, XHTML assets, and generated raw-HTML AST nodes now retain the
  source `mediaOverlay` id so downstream WordPress review paths can relate raw
  chapter markup to the audio timing report.
- Updated `wordpress-epub3-package-handoff.php` so the local smoke exposes and
  self-tests media-overlay audio/page-marker review metadata alongside the
  existing OPF, nav/NCX, encryption, asset, and raw XHTML handoff.

Focused evidence:

- Red-first `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  before implementation:
  - Result: failed in the new SMIL media-overlay case because
    `result[mediaOverlays]` was missing.
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 121 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4,530 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`
- `php -l lanes/pandoc/src/EpubReader.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  - Result: no syntax errors

Status delta:

- `phpPass`: `454 -> 455`
- mapped native checks: `922 -> 923`
- EPUB3 package focused cases: `6 -> 7`
- EPUB3 package focused assertions: `102 -> 121`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `XmlHtmlDom`, `AstNode`,
  and `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat the accepted EPUB3 OCF mimetype/container, OPF
  metadata/manifest/spine, TOC/NCX target, missing asset, raw XHTML spine
  handoff, landmarks/page-list extraction, or OCF encryption/obfuscated-font
  preflight. It adds only bounded SMIL media-overlay text/audio timing
  extraction for OPF-referenced spine items.

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
