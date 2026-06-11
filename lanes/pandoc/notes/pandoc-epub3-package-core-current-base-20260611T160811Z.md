# pandoc-epub3-package-core-current-base-20260611T160811Z

Slice: OPF contributor metadata handoff on accepted base `bcd3e5344`.

## Behavior

Compact EPUB package ingestion already parsed Dublin Core contributors through
the same native metadata refinement path as creators, including MARC role
refinements, `file-as`, `display-seq`, language, direction, and alternate-script
metadata. The WordPress reviewer summary did not expose the normalized
contributor detail arrays, so package review queues could see contributors only
through the raw metadata payload.

This slice wires `contributorDetails` and `contributorsByRole` into
`summary()['wordpressImport']['metadataDetails']`, preserving editor and
translator provenance for bounded EPUB import review without fetching external
records or invoking conversion tools.

## Focused Evidence

- `php -l lanes/pandoc/src/EpubPackage.php` passed.
- `php -l lanes/pandoc/tests/EpubPackageTest.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` passed with
  `1 test files, 1183 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
  passed with `epub3 package preflight self-test ok`.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed with `epub3 package handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests` passed after rebase with
  `44 test files, 63799 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`,
`EpubPackage`, DOM/libxml XML parsing, existing OPF metadata refinement parsing,
and in-memory package test fixtures.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip,
ZipArchive, EPUBCheck, browser renderer, JavaScript/media execution, external
validator, online service, live provider test, or live-service provider test was
executed.

## Non-Overlap

This patch does not repeat accepted OCF mimetype/container/rootfile validation,
OPF package/root/spine metadata, identifier/language/date/source/bibliographic
metadata, accessibility metadata, package/container links, collections, media
overlays, guide references, navigation diagnostics, resource properties,
fallback chains, encryption, obfuscated fonts, CSS resource policy, XHTML AST
handoff, or NCX/XHTML navigation parsing.

The new surface is only WordPress reviewer handoff visibility for already
normalized OPF contributor metadata and role grouping.
