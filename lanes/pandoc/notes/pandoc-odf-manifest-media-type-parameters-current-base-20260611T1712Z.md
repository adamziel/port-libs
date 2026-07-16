# ODF Manifest Media-Type Parameter Provenance

Bead: `plib-mck5g`
Date: 2026-06-11 UTC
Base target: `origin/main 47254ffb1e58ad026d2b9f824be8e1533c7c082d`

## Scope

This slice adds bounded native PHP ODF/ODT package ingestion provenance for
parameterized `manifest:media-type` values. `OdfReader` now preserves each raw
media type plus its parsed base type, parameter list, parameter map, and
parameter count on:

- manifest file-entry records;
- declared media records;
- package thumbnail review metadata;
- ZIP package provenance inventory rows;
- manifest media-type summary buckets.

The summary buckets group parameterized values by base media type while
retaining the raw media-type strings and parameter names for reviewer handoff.
Raw `mediaType` values remain unchanged.

## Evidence

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 3871 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64310 assertions, 0 failures

## Accounting

- `phpPass`: 3080 -> 3081
- `mappedOdfManifestMediaTypeParameterCases`: 1
- `odfManifestMediaTypeParameterAssertions`: 31
- Mapped denominator: 3200 -> 3201

## Boundaries

No Pandoc, office suite, `zip`/`unzip`, browser renderer, external validator,
online service, live provider test, or live-service provider test was invoked.

This does not repeat accepted ODF manifest URI suffix, media byte exposure,
directory, thumbnail, RDF sidecar, XML signature, script package, compression,
or declared-size mismatch slices. It only adds MIME parameter provenance for
ODF manifest media-type review.
