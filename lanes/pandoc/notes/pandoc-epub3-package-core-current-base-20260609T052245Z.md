# pandoc-epub3-package-core-current-base-20260609T052245Z

Base accepted HEAD: `aeac7627505caef0c7f45b74c533b70ec36e1807`

## Behavior

Implemented bounded native PHP compact EPUB3 package preflight support for OPF
remote-link policy summaries in `EpubPackage`.

`EpubPackage::remoteResourcePolicy()` and `summary()['remoteResourcePolicy']`
now aggregate direct OPF metadata links and nested OPF collection links into
passive review buckets:

- `local-package` for package records that resolve to existing ZIP parts;
- `remote-no-fetch` for absolute-URI links that remain unfetched;
- `missing-package` for local package references that resolve but are absent;
- `unresolved` for malformed or missing href inputs.

The summary preserves source type, source index, collection path/id/role,
rel/property/media metadata, manifest linkage, targets, per-link diagnostics,
policy counts, target lists, and WordPress import aliases:
`remoteResourcePolicy`, `remoteResourceExternalTargets`, and
`remoteResourcePolicyDiagnostics`.

This is intentionally compact-package preflight only. It does not scan XHTML or
CSS and does not change the richer `EpubReader` remote-resource reconciliation.

## Evidence

Baseline focused verification before adding the new test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 180 assertions, 0 failures`

Red-first focused verification after adding the test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: failed because `remoteResourcePolicy` was absent; run reached
  `1 test files, 181 assertions, 1 failures`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 208 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`

Focused delta: +1 PHP TestRunner PASS case and +28 focused assertions in
`EpubPackageTest.php`. `lane-status.json` moves `phpPass` from `2368` to
`2369`; `UPSTREAM_TEST_MANIFEST.json` moves mapped EPUB/Pandoc support from
`2762` to `2763`, and compact EPUB3 package assertions from `112` to `140`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`EpubPackage`, `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, DOM/libxml
NONET XML parsing, the focused PHP test runner, and the existing WordPress
EPUB3 package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, EPUBCheck, external converter, browser renderer, online service,
live provider test, or live-service provider test was executed.

Full upstream Pandoc runner parity remains a separate upstream-runner
dependency task requiring hydrated pinned upstream sources and Haskell test
executables.

## Non-Overlap

No active `port-pandoc-*.needs-lane-rework.md` rework note existed before
editing.

This does not repeat accepted EPUB OCF mimetype/container/rootfile validation,
compact OPF metadata refinements, compact OPF metadata link records, compact
OPF guide/nav-section preflight, compact media-type bindings, compact OPF
collections, rich `EpubReader` metadata link reports, vendor metadata,
accessibility metadata, manifest property vocabulary, nav/NCX/page-list/audio,
XHTML/CSS resource scans, `EpubReader` remote-resource reconciliation, media
overlays, OCF sidecars, encryption, asset fallback, or embedded
media/object/frame slices. It is restricted to a compact `EpubPackage`
package/collection link policy summary for WordPress preflight.

## Follow-Up

Good non-overlapping EPUB3 package follow-ups are package-link vocabulary
validation, compact media-overlay summary parity, encrypted-resource review
policy, XHTML-to-AST conversion boundaries, or EPUBCheck-style validation
diagnostics.
