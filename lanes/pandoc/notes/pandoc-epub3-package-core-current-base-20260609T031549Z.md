# pandoc-epub3-package-core-current-base-20260609T031549Z

Slice: EPUB3 OPF guide and XHTML nav-section package handoff on accepted base
`66a83c16d67307dc6e017f1d9b83d8212b549eaa`.

## Behavior

The lightweight `EpubPackage` preflight now preserves package-level review
metadata that previously required the heavier `EpubReader` path:

- OPF `<guide><reference>` entries with type, title, original href, resolved
  target, package part, external flag, and package existence.
- XHTML navigation document sections for `toc`, `landmarks`, and `page-list`
  while keeping `navigation()` as the primary TOC-compatible surface.
- `summary()` and `wordpressImport` handoff fields for guide references,
  landmark targets, and page-list targets.

Remote guide hrefs remain metadata-only and are not fetched.

## Focused Evidence

- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  passed with `1 test files, 49 assertions, 0 failures`.
- Red-first after adding focused assertions:
  `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  failed because `PortLibs\Pandoc\EpubPackage::guideReferences()` was absent.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  passed with `1 test files, 62 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
  passed with `epub3 package preflight self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/EpubPackage.php`,
  `php -l lanes/pandoc/tests/EpubPackageTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-epub3-package-preflight.php`
  passed.
- JSON status validation:
  `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded successfully.

Focused assertion delta: `+13`.
Lane `phpPass` moves from `2212` to `2213`.
Manifest mapped denominator moves from `2622` to `2623`; EPUB3 package-core
cases move from `6` to `7`; EPUB3 package-core assertions move from `112` to
`125`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`EpubPackage`, `ZipPackage`, `OpcPackagePath`, DOM/libxml XML parsing, existing
package href resolution, and the WordPress EPUB3 package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, EPUBCheck, browser renderer, media player, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted EPUB OCF mimetype/container/rootfile validation,
OPF metadata/manifest/spine parsing, vendor metadata, raw XHTML spine handoff,
primary nav/NCX TOC target parsing, NCX supplemental navList aggregation,
full `EpubReader` guide/collection reports, fallback chains, bindings, media
overlays, CFI/media fragments, OCF sidecars, encryption, obfuscated-font
preflight, remote-resource reconciliation, or ZIP package integrity work.

The covered gap is specifically the lightweight package preflight surface for
OPF guide references and all XHTML nav sections.

## Follow-Up

A later EPUB3 package slice can cover package-level `xml:lang`/`dir`
inheritance, hidden navigation section policy, richer OPF collection/reference
diagnostics, CSS cascade/export policy, EPUBCheck-compatible validation, or
full Haskell/Pandoc runner comparison as separate bounded work.
