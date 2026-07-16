# pandoc-epub3-package-core-current-base-20260605T021610Z

Base: `0df7f83fa6571259635166e594b06a5096c92f71`

Source truth:

- Existing lane package contract: use native PHP ZIP/package primitives and do
  not shell out to Pandoc, zip/unzip, browsers, office tools, or online
  services.
- EPUB package metadata can declare manifest `fallback` chains for resources
  whose media type is not directly supported. This slice keeps the conversion
  bounded to XHTML fallback handlers for spine items, which is the path the
  existing EPUB reader can hand to WordPress as raw HTML review blocks.
- Source references checked during this slice:
  - W3C EPUB 3.3 package document:
    `https://www.w3.org/TR/epub-33/`
  - IDPF EPUB Publications 3.0.1 package document for historical OPF
    fallback-chain and foreign-resource semantics:
    `https://idpf.org/epub/301/spec/epub-publications-20140626.html`
- No hydrated local Pandoc checkout or Cabal package/project files are
  available in this isolated worktree, so this remains bounded native EPUB3
  package support, not upstream Haskell runner parity.

Implementation:

- Extended `EpubReader` spine parsing to resolve OPF manifest fallback chains
  from a foreign spine resource to an effective XHTML manifest item.
- The spine report now keeps the original spine `idref`, target, media type,
  encryption/exposure flags, and adds effective `contentId`, `contentPart`,
  `contentMediaType`, `contentIsFallback`, `fallbackChain`, and
  `fallbackDiagnostics` fields.
- `documentNode()` now emits XHTML fallback handlers as `raw_html` AST blocks
  with `source=epub3-spine-fallback`, the fallback part, original
  `spinePart`/`spineMediaType`, `fallbackOf`, `contentId`, and the chain used
  to resolve it.
- Updated the WordPress EPUB handoff smoke so Data Liberation review packets
  include a scripted/foreign slideshow spine resource plus a reviewable XHTML
  fallback block.

Focused evidence:

- Baseline before test addition:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 219 assertions, 0 failures`
- Red-first after adding the fallback-chain test:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 224 assertions, 1 failures`
  - Failure: foreign spine item fallback did not expose `contentId`; current
    reader dropped the fallback XHTML from the AST.
- Focused rerun after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 245 assertions, 0 failures`
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5657 assertions, 0 failures`
- PASS-line count:
  `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `530`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/EpubReader.php` -> no syntax errors
  - `php -l lanes/pandoc/tests/EpubReaderTest.php` -> no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php` -> no syntax errors

Status delta:

- `phpPass`: `528 -> 530` after reconciling the current focused lane PASS-line
  count.
- mapped native checks: `1006 -> 1007`
- EPUB3 package focused cases: current-base manifest reconciliation
  `4 -> 11`
- EPUB3 package focused assertions: current-base manifest reconciliation
  `62 -> 245`
- This slice itself adds `+1` EpubReader PASS case and `+26` focused
  EpubReader assertions over the accepted baseline.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, `AstNode`,
  `MarkdownWriter`, and `WordPressBlockWriter` paths.

Non-overlap:

- This does not repeat accepted EPUB3 OCF mimetype/container validation,
  metadata/manifest/spine parsing, direct XHTML spine handoff, nav/NCX,
  landmarks/page-list navigation, OPF guide/collections, alternate
  renditions, OCF encryption/obfuscated-font preflight, SMIL media-overlay
  parsing, or remote-reference retention.
- This adds only OPF manifest fallback-chain resolution for foreign spine
  resources that can hand off to an XHTML fallback handler.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, roff, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep richer XHTML-to-AST conversion, EPUB media extraction/export policy,
  OPF bindings/scripted handler audit, CSS cascade handling, remote-resource
  policy beyond unfetched diagnostics, and broader malformed fallback-chain
  corpus coverage as separate bounded EPUB slices.
