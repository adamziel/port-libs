# EPUB3 Package Resource Properties Handoff

Slice: `pandoc-epub3-package-core-current-base-20260609T083432Z`
Lane: `pandoc`
Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

Implemented one bounded EPUB3 package-core behavior in native PHP: compact OPF
manifest resource-property review summaries in `EpubPackage`.

`EpubPackage::resourceProperties()` now reports:

- core EPUB manifest property counts for `nav`, `cover-image`, `mathml`, `svg`,
  `remote-resources`, `scripted`, and `switch`;
- items by manifest id and by property;
- review-required items and review flags for MathML/SVG/remote/scripted/switch
  resources;
- OPF package-prefix vocabulary resolution for manifest item property tokens,
  including reserved `rendition:` and declared package prefixes;
- unknown prefixed manifest-property diagnostics for reviewer handoff.

The report is also exposed through `EpubPackage::summary()` and
`summary()['wordpressImport']`, and the existing WordPress EPUB package preflight
example now asserts the new review summary.

## Source Truth And Non-Overlap

This follows the existing lane-local EPUB reader contract for OPF manifest
resource properties and keeps it in the compact package preflight API. It does
not repeat accepted OCF container validation, OPF metadata/refinements, spine
rendition values, nav/NCX parsing, guide/collection links, metadata links,
remote link policy, media overlays, fallback chains, XHTML content scanning,
vendor metadata, encryption/OCF sidecars, or ZIP package primitives.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive,
external converter, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php
FAIL summarizes OPF manifest resource properties for compact package preflight
Call to undefined method PortLibs\Pandoc\EpubPackage::resourceProperties()
1 test files, 367 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php
1 test files, 403 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test
epub3 package preflight self-test ok
```

## Status Delta

- Adds 1 focused PHP PASS case.
- Focused EPUB package assertions move `367 -> 403` in `EpubPackageTest.php`.
- `lane-status.json` `phpPass` moves `2530 -> 2531`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves `2898 -> 2899`.
- `epub3PackageCoreCases` and `mappedEpub3PackageCoreCases` move `6 -> 7`.
- `epub3PackageCoreAssertions` moves `112 -> 148`.

## Dependency Closure

No new native support component is needed. This slice reuses `EpubPackage`,
`ZipPackage`, `OpcPackagePath`, existing OPF prefix handling, DOM/libxml parsing,
and the existing WordPress EPUB package preflight example.

Follow-up EPUB work should stay non-overlapping: CSS cascade preflight,
XHTML-to-AST conversion, media export policy, encrypted resource policy, or
reading-system capability review.
