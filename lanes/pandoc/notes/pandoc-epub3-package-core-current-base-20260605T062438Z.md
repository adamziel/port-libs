# Pandoc EPUB3 Package Core Current Base

Slice: `pandoc-epub3-package-core-current-base-20260605T062438Z`
Base accepted HEAD: `54d248e648f22ef3797e5b0638b5df06fc726604`

## Behavior Added

- Added bounded EPUB accessibility metadata handoff to `EpubReader`.
- OPF package metadata now exposes a normalized `accessibility` report at the
  package top level, under `metadata.accessibility`, under
  `importReport.accessibility`, and on the document AST.
- The report summarizes Schema.org/EPUB accessibility fields from OPF `meta`
  records: `accessMode`, `accessModeSufficient`, `accessibilityFeature`,
  `accessibilityHazard`, `accessibilityControl`, `accessibilityAPI`,
  `accessibilitySummary`, `a11y:certifiedBy`,
  `a11y:certifierCredential`, `a11y:certifierReport`, and
  `dcterms:conformsTo`.
- Both EPUB 3 `property="schema:..."` records and legacy
  `name="schema:..." content="..."` records are preserved with source
  metadata, raw property/name, language, scheme, id, and refines fields.
- Linked accessibility records are identified from OPF metadata `link`
  records with accessibility rel/properties, resolved relative to the package
  part, and carry byte length plus SHA-256 when package-local bytes are
  available. Remote records remain unfetched diagnostics through the existing
  metadata-link path.
- Updated the WordPress EPUB3 package smoke so accessibility review fields are
  visible to import queues without Pandoc, EPUBCheck, zip/unzip, browser
  renderers, online services, or remote fetches.

## Source Truth

- W3C EPUB Accessibility 1.2 requires package-document Schema.org
  accessibility metadata that exposes accessible properties:
  `https://www.w3.org/TR/epub-a11y-12/`.
- The W3C package metadata authoring guide documents OPF `meta property`
  accessibility fields such as `accessMode`, `accessibilityFeature`, and
  `accessibilityHazard`, and describes linked metadata records as a separate
  source of accessibility metadata:
  `https://w3c.github.io/publ-a11y/package-metadata-authoring-guide/`.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 441 assertions, 0 failures.

Red-first after adding the accessibility expectations:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed: 1 test file, 442 assertions, 1 failure because
  `accessibility` was absent from the package result.

Focused verification after implementation:

- `php -l lanes/pandoc/src/EpubReader.php`
  passed with no syntax errors.
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
  passed with no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed: 1 test file, 467 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed: `epub3 package handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane json ok\n";'`
  passed: `lane json ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 20 test files, 8057 assertions, 0 failures.
- `git diff --check -- lanes/pandoc`
  passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `685 -> 686`.
- mapped native checks: `1165 -> 1166`.
- EPUB3 package focused cases: `20 -> 21`.
- EPUB3 package focused assertions: `441 -> 467`.
- This slice adds `+1` EpubReader PASS case and `+26` focused EpubReader
  assertions over the accepted baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcPackagePath`, `AstNode`, and the existing OPF metadata/link resolution
paths. Full upstream Pandoc runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell Cabal dependency closure already recorded
in lane status.

## Non-Overlap

This does not repeat accepted EPUB3 OCF mimetype/container validation, OPF
metadata/DC/meta raw extraction, metadata refinements, metadata link byte
resolution, OPF manifest/spine parsing, direct XHTML spine handoff, nav/NCX,
landmarks/page-list navigation, OPF guide/collections, alternate renditions,
spine page-progression/page-spread metadata, OCF encryption/obfuscated-font
preflight, SMIL media-overlay parsing, remote nav/NCX/SMIL reference
retention, OPF fallback chain resolution, package asset export reporting,
remote OPF manifest resource reporting, OPF binding-handler reporting, or OPF
manifest resource-property reporting.

The new surface is only normalized EPUB accessibility metadata and linked
accessibility-record handoff.

## Exclusions

Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
KaTeX, Typst, browser renderers, EPUBCheck, media players, handler runtimes,
remote fetches, roff, decryption helpers, font deobfuscators, online
sanitizers, or online services.

## Follow-Up

Keep richer accessibility vocabulary validation, XHTML-to-AST conversion,
CSS cascade/resource policy, media extraction/export policy, remote-resource
security policy, multiple-rendition selection UX, EPUBCheck-style validation,
and deeper reading-system layout behavior as separate bounded EPUB slices.
