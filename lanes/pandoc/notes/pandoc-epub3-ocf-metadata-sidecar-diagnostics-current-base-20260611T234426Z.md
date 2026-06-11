# EPUB3 OCF metadata sidecar diagnostics

Slice: `plib-c3gxm`, Pandoc EPUB3 package ingestion core blocker.

Current base after rebase: `e9d25106ae` (`pandoc: summarize html outline metadata`).

## Change

`EpubPackage` now treats optional `META-INF/metadata.xml` as bounded package-review metadata. Wrong-root, malformed XML, and unsupported-compression sidecars no longer abort OPF package ingestion. Instead, the package exposes a `containerMetadata` report and mirrors document-level metadata diagnostics through summary and WordPress import review fields while preserving the existing `containerLinks()` shape for valid metadata link records.

## Focused Coverage

Added `reports OCF metadata sidecar diagnostics without aborting package ingestion` in `lanes/pandoc/tests/EpubPackageTest.php`.

The case adds 34 focused assertions covering:

- wrong-root `metadata.xml` diagnostics with OPF/nav ingestion preserved
- malformed XML diagnostics without package construction failure
- unsupported ZIP compression diagnostics without exposing metadata bytes
- WordPress review propagation for `containerMetadata` and container-link diagnostics

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1620 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67323 assertions, 0 failures

No Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
