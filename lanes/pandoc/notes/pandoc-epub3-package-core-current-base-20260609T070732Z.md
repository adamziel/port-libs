# pandoc-epub3-package-core-current-base-20260609T070732Z

Base accepted HEAD: `030e94cf137586963da96dca64555cebe2ff01ee`

## Behavior

Implemented bounded native EPUB3 package handoff for OPF metadata link `title`
provenance in the full `EpubReader`.

The reader already preserved OPF metadata link rel/property tokens, href target
policy, language, direction, `hreflang`, vocabulary reports, linked-resource
attachments, and byte hashes. This slice keeps the optional `title` attribute
visible through:

- raw OPF metadata link entries;
- resolved `metadata.links`;
- `linksByRel` and `linksByRefinedId`;
- linked resources attached to package, manifest, and spine review subjects;
- metadata link target-policy report items;
- document metadata and raw spine block linked-resource attributes;
- the WordPress EPUB3 package handoff smoke output.

No link target resolution, byte exposure policy, remote-fetch policy, or
navigation behavior changed.

## Evidence

Baseline focused verification before the new test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3680 assertions, 0 failures`

Red-first focused verification after adding the title provenance test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: failed because `metadata.links[0].title` was absent; run reached
  `1 test files, 3681 assertions, 1 failures`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3693 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`

Syntax and JSON checks:

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- Result: no syntax errors
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode((string) file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " ok\n"; }'`
- Result: both JSON files decoded successfully
- `git diff --check -- lanes/pandoc`
- Result: passed

Focused delta: +1 PHP TestRunner PASS case and +13 focused assertions in
`EpubReaderTest.php`. `lane-status.json` moves `phpPass` from `2470` to
`2471`; `UPSTREAM_TEST_MANIFEST.json` moves mapped support from `2851` to
`2852`, `mappedEpub3PackageCoreCases` from `6` to `7`, and
`epub3PackageCoreAssertions` from `112` to `125`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses native PHP
`EpubReader`, `ZipPackage`, `OpcPackagePath` package reference resolution,
DOM/libxml NONET XML parsing, `AstNode` metadata handoff, focused lane tests,
and the existing WordPress EPUB3 package handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, EPUBCheck, external template engine, external converter, TeX/PDF
engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

Full upstream Pandoc runner parity remains a separate upstream-runner
dependency task requiring hydrated pinned upstream sources and Haskell test
executables.

## Non-Overlap

No active `port-pandoc-*.needs-lane-rework.md` rework note existed before
editing.

This does not repeat accepted EPUB OCF container/rootfile handling, OPF
metadata vocabulary, identifier/source/bibliographic refinements, metadata link
target policy, metadata link vocabulary diagnostics, metadata link refines
attachments, guide/collection links, nav/NCX/page-list/navList reports, CFI and
media-fragment navigation, manifest properties, XHTML/CSS resource scans,
remote-resource reconciliation, sidecar metadata, encryption exposure policy,
fallback/bindings, media overlays, compact `EpubPackage` preflight, or ZIP
integrity work. It is restricted to the `title` provenance field on already
parsed OPF metadata links and the downstream review packets that reuse those
link arrays.

## Follow-Up

Good non-overlapping EPUB3 package follow-ups are fuller static validation
diagnostics, XHTML-to-AST conversion boundaries, additional package-link policy
summaries, or CSS export/cascade policy that does not repeat current resource
reference scanning.
