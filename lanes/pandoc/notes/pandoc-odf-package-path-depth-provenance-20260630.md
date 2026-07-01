# ODF Package Path Depth Provenance

Slice: `plib-ovetm`
Date: 2026-06-30 UTC

## Scope

Native PHP ODF/ODT package ingestion now records metadata-only package path
depth provenance across compact and rich readers by mirroring the compact
`OpenDocumentPackage` inventory shape in rich `OdfReader` package provenance.

- `OdfReader` package provenance entries now carry path segments, segment
  counts, directory, directory depth, and base name.
- Rich package provenance now summarizes `partPathDepths`,
  `maxPartPathSegmentCount`, `maxPartDirectoryDepth`, `deepestPartNames`, and
  `deepestParts`, matching compact package inventory semantics.
- Rich ODF package identity payloads include the same path-depth facts so
  review identity hashes cover package inventory layout changes.

No package bytes are exposed beyond existing byte-exposure policy decisions, and
no Pandoc binary, office suite, TeX/browser engine, zip/unzip tool, Node
tooling, external validator, online service, or live provider was invoked.

## Accounting

- `phpPass`: `469 -> 470`
- `phpFail`: `0`
- Upstream mapped denominator: `2317 -> 2318`
- New mapped counter: `mappedOdfPackagePathDepthProvenanceCases: 1`
- Focused assertions: `42`

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderPackagePathDepthProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackagePathDepthProvenanceTest.php`
  - `1 test files, 42 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfReaderPackagePathDepthProvenanceTest.php`
  - `5 test files, 2081 assertions, 0 failures`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
