# ZIP package manifest directory roots

Bead: `plib-psotm`
Date: 2026-06-30 UTC
Area: Pandoc shared ZIP/OPC package primitives

## Behavior

`ZipPackage::packageManifestPreflight()` exposes whole-package top-level
directory-root provenance for importer handoff:

- each manifest entry includes `directoryRoot` (`/`, `_rels/`, `word/`,
  `META-INF/`, and similar roots);
- the manifest includes ordered `directoryRoots` for callers that only need
  the root list;
- `directoryRootSummaries` carries entry counts, file/directory counts, byte
  totals, data-descriptor totals, local-record totals, and entry names.

The current manifest hash payload already includes `directoryRootSummaries`.
The `directoryRoots` list is derived from those summaries and remains reviewer
metadata for handoff callers that do not need to inspect each summary row.

No Pandoc binary, office suite, TeX tool, zip/unzip, ZipArchive, browser
renderer, Jupyter, Node, external validator, online service, live provider test,
or live-service provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
