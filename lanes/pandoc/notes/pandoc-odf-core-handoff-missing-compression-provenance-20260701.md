# ODF Core Handoff Missing and Compression Provenance

Slice: `pandoc-odf-core-handoff-missing-compression-provenance-20260701`
Issue: `plib-3bs7a`

## Scope

- Added focused rich `OdfReader` coverage for `corePackageHandoff` rows when
  optional core XML package entries are absent and not manifest-declared.
- The fixture also carries manifest-declared missing media, unsupported
  compression media, and script sidecars through package review metadata.
- Script sidecars remain blocked under `script-package-bytes-blocked`, and
  unsupported package bytes remain blocked under
  `unsupported-compression-bytes-blocked`.

## Verification

- `php -l lanes/pandoc/tests/OdfReaderCorePackageHandoffPreflightTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderCorePackageHandoffPreflightTest.php`
  - 1 test file, 161 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderCorePackageHandoffPreflightTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 3 test files, 7,995 assertions, 0 failures
- `git diff --check`
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

No Pandoc executable, Cabal/Haskell command, office suite, PHP `ZipArchive`,
`zip`/`unzip`, browser engine, TeX/PDF engine, external validator, online
service, or live provider test was executed.

## Accounting

- Direct-format parity: package-ingestion metadata-only coverage; no upstream
  format denominator change.
- Existing manifest counters do not define a dedicated ODF core handoff bucket,
  so this slice is accounted for by this note and focused PHP evidence.
