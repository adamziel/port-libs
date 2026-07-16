# Pandoc EPUB3 Package Core Current Base

Slice: `pandoc-epub3-package-core-current-base-20260605T055220Z`
Base accepted HEAD: `a3c7b0ea53255554fb26d1258202c75989406af6`

## Behavior Added

- Added bounded OPF spine reading-order reporting to `EpubReader`.
- The package handoff now preserves the spine `page-progression-direction`
  value, defaults absent/invalid values to `default`, and reports invalid
  direction diagnostics without dropping the rest of the EPUB package.
- Spine `itemref` `properties` now expose page-spread placement metadata for
  `page-spread-left`, `page-spread-right`, `spread-none`, and the matching
  `rendition:` aliases.
- Duplicate alias declarations for the same placement are accepted, while
  conflicting placements are reported as review diagnostics.
- `spineProperties` is exposed at the package top level, under
  `importReport.spine.properties`, and on the document AST. Individual raw
  XHTML handoff blocks now carry `pageProgressionDirection`, `pageSpread`,
  `pageSpreadProperties`, and `spineItemProperties`.
- Updated the WordPress EPUB3 handoff smoke so right-to-left source packets
  and spread placement hints remain visible to review queues without rendering
  or running EPUBCheck.

## Source Truth

- W3C EPUB 3.3 defines the OPF `spine` as the ordered default reading order,
  with optional `page-progression-direction` values `ltr`, `rtl`, and
  `default`: `https://w3c.github.io/epub-specs/epub33/core/`.
- W3C EPUB 3.3 also defines `rendition:page-spread-left` and
  `rendition:page-spread-right` as aliases of the unprefixed page-spread
  properties, and defines `rendition:page-spread-center` as the `spread-none`
  alias for centering a spine item. It allows equivalent prefixed/unprefixed
  aliases while disallowing conflicting page-spread placements on a single
  itemref:
  `https://w3c.github.io/epub-specs/epub33/core/`.
- W3C EPUB Reading Systems 3.3 says missing `page-progression-direction`
  defaults to `default` and unknown itemref properties are ignored:
  `https://w3c.github.io/epub-specs/epub33/rs/`.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 400 assertions, 0 failures.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 441 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed: `epub3 package handoff self-test ok`.
- `php -l lanes/pandoc/src/EpubReader.php`,
  `php -l lanes/pandoc/tests/EpubReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  passed with no syntax errors.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `666 -> 668`.
- mapped native checks: `1146 -> 1148`.
- EPUB3 package focused cases: `18 -> 20`.
- EPUB3 package focused assertions: `400 -> 441`.
- This slice adds `+2` EpubReader PASS cases and `+41` focused EpubReader
  assertions over the accepted baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcPackagePath`, `AstNode`, `WordPressBlockWriter`, and the existing OPF
spine parsing paths. Full upstream Pandoc runner parity remains blocked by the
missing hydrated Pandoc checkout and Haskell Cabal dependency closure already
recorded in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation,
metadata/DC/meta extraction, OPF metadata links/refinements, manifest/spine
basic parsing, direct XHTML spine handoff, nav/NCX, landmarks/page-list
navigation, OPF guide/collections, alternate renditions, OCF
encryption/obfuscated-font preflight, SMIL media-overlay parsing, remote
nav/NCX/SMIL reference retention, OPF fallback chain resolution, package asset
export reporting, remote OPF manifest resource reporting, OPF
binding-handler reporting, or OPF manifest resource-property reporting.

The new surface is only the package-level handoff of OPF spine reading-order
and page-spread itemref metadata.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, EPUBCheck, media players, handler runtimes,
remote fetches, roff, decryption helpers, font deobfuscators, online
sanitizers, or online services.

## Follow-Up

Keep richer XHTML-to-AST conversion, CSS cascade/resource policy, media
extraction/export policy, remote-resource security policy,
multiple-rendition selection UX, EPUBCheck-style validation, and deeper
reading-system layout behavior as separate bounded EPUB slices.
