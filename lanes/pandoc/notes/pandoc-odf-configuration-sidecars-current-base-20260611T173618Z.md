# ODF/ODT Configurations2 Sidecar Package Provenance

Slice: `plib-g35ih`, ODF/ODT OpenDocument package ingestion.

Current base: `origin/main` `2cea4fa785b868a6fa27c96e3ade52a6d7295957`.

## Scope

This slice adds a bounded native PHP package-ingestion review surface for
OpenDocument `Configurations2/*` package members. These sidecars can carry UI
or application configuration XML that should be visible to reviewer queues as
package provenance, but should not be promoted to document media byte handoff.

`OpenDocumentPackage::summarize()` now reports:

- `configurationSidecars`
- `configurationSidecarCount`
- `missingConfigurationSidecarCount`
- `undeclaredConfigurationSidecarCount`
- `encryptedConfigurationSidecarCount`

Each sidecar item preserves declared vs undeclared status, manifest media type,
resolved package path, URI suffix provenance, stored byte length, compressed
byte length, compression method/name, CRC, declared-size mismatch status,
diagnostics, and source byte-exposure policy. Declared existing sidecars are
reported as `odf-configuration-sidecar-metadata-only`, so their package metadata
is inspectable without treating their XML as importable media bytes.

`packageInventory.parts[*].roles` also tags `Configurations2/*` members with
`odf-configuration-sidecar` while retaining `manifest-declared` or
`undeclared-package-entry` roles.

## Focused Coverage

Added `reports compact ODT Configurations2 sidecars as metadata only` to
`lanes/pandoc/tests/OpenDocumentPackageTest.php`.

The fixture covers:

- a manifest-declared existing `Configurations2/toolbar/statusbar.xml`
- a manifest-declared missing `Configurations2/missing.xml`
- an undeclared package entry `Configurations2/accelerator/current.xml`
- media handoff staying limited to the content image
- package inventory role provenance for declared and undeclared sidecars

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 356 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64420 assertions, 0 failures

No Pandoc binary, office suite, zip/unzip command, browser renderer, external
validator, online service, live provider test, or live-service provider test was
used.

## Accounting

- `phpPass`: `3082 -> 3083`
- mapped denominator: `3202 -> 3203`
- `mappedOdfConfigurationSidecarCases`: `1`
- `odfConfigurationSidecarAssertions`: `32`

## Boundaries

This does not parse or execute configuration sidecar XML, merge settings into
`settings.xml`, validate office application configuration semantics, or expose
sidecar payload bytes. It also avoids the accepted ODF manifest media-type
parameter, manifest URI suffix, package thumbnail, XML signature, macro/script,
directory, media exposure, and ZIP compression provenance slices.
