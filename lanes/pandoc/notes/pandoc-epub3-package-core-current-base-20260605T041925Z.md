# Pandoc EPUB3 Package Core Current Base

Slice: `pandoc-epub3-package-core-current-base-20260605T041925Z`
Base accepted HEAD: `9f1a5acae6e7f10a53b3e432bfded7a636865d9e`

## Behavior Added

- Added bounded OPF metadata `<link>` parsing to `EpubReader`.
- Package metadata now exposes `metadata.links` and `metadata.linksByRel` for
  local, remote, and missing linked metadata resources.
- Local in-container linked metadata records are resolved relative to the OPF
  package part and report target part, byte length, CRC32, SHA-256, declared
  media type, properties, `hreflang`, `refines`, safe byte exposure, and
  diagnostics.
- Remote metadata links are retained as unfetched review records with
  `external-metadata-reference` diagnostics.
- Missing local metadata links remain explicit review diagnostics using
  `missing-metadata-reference`.
- Metadata-linked local records are excluded from undeclared package asset
  diagnostics so a valid OPF-linked ONIX/JSON-LD/review record is not treated
  as an accidental unmanifested import asset.
- Updated the WordPress EPUB3 handoff smoke to expose a local review-record
  link, a remote ONIX-style record link, and a missing creator voicing link.

## Source Truth

- W3C EPUB 3.3 defines OPF package metadata as allowing repeatable `link`
  children, and the `link` element associates resources such as metadata
  records with the EPUB publication:
  `https://www.w3.org/TR/epub-33/#sec-link-elem`.
- W3C EPUB 3.3 also shows linked metadata records via package-document
  `href`, `media-type`, `properties`, `refines`, and `rel` attributes:
  `https://www.w3.org/TR/epub-33/#sec-shared-attrs`.
- This is bounded native PHP package support. It does not interpret ONIX,
  JSON-LD, OPDS, MARC, audio voicing metadata, linked-record schemas, or remote
  resources.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 318 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed: 1 test file, 318 assertions, 1 failure. The new metadata-link test
  failed because `metadata.links` was absent.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 353 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed: `epub3 package handoff self-test ok`.
- `php -l lanes/pandoc/src/EpubReader.php`,
  `php -l lanes/pandoc/tests/EpubReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `613 -> 614`
- mapped native checks: `1,087 -> 1,088`
- EPUB3 package focused cases: `14 -> 15`
- EPUB3 package focused assertions: `318 -> 353`
- This slice itself adds `+1` EpubReader PASS case and `+35` focused
  EpubReader assertions over the accepted baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`ZipPackageEntry`, `OpcPackagePath`, `AstNode`, `WordPressBlockWriter`, and the
existing EPUB package-reference diagnostics. Full upstream Pandoc runner parity
remains blocked by the missing hydrated Pandoc checkout and Haskell Cabal
dependency closure already recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
metadata/DC/meta extraction, manifest/spine parsing, direct XHTML spine
handoff, nav/NCX, landmarks/page-list navigation, OPF guide/collections,
alternate renditions, OCF encryption/obfuscated-font preflight, SMIL
media-overlay parsing, remote nav/NCX/SMIL reference retention, OPF fallback
chain resolution, package asset export reporting, remote OPF manifest resource
reporting, or OPF binding-handler reporting.

The new surface is only OPF metadata link-record handoff and linked-resource
ownership in asset diagnostics.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, media players, handler runtimes, remote
fetches, roff, decryption helpers, font deobfuscators, online sanitizers, or
online services.

## Follow-Up

Keep richer XHTML-to-AST conversion, CSS cascade/resource policy, remote
resource fetching/security policy beyond unfetched diagnostics, richer media
extraction/export beyond bounded attachment reporting, linked-record schema
interpretation, handler execution policy beyond static OPF binding diagnostics,
multiple-rendition selection UX, and broader EPUBCheck-style validation as
separate bounded EPUB slices.
