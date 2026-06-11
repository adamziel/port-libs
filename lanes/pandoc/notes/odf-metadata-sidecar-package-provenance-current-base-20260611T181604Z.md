# ODF metadata sidecar package provenance current-base slice

Bead: `plib-ehqy5`
Base: `origin/main` `c0cfa42e2e2760118645505c1ef871e550f08553`
Scope: `lanes/pandoc`

This slice maps one bounded ODF/ODT package-ingestion blocker for RDF and XML
signature metadata sidecar ZIP provenance. `OdfReader` now classifies package
parts such as `manifest.rdf`, `Object 1/manifest.rdf`,
`META-INF/documentsignatures.xml`, and undeclared signature sidecars with
`metadata-sidecar`, `odf-rdf-metadata`, and `odf-signature-metadata` roles.
Package provenance also reports declared/undeclared metadata sidecar counts
while preserving parsed RDF and signature review metadata.

Regression:
- Added `classifies ODT RDF and signature package sidecars in ZIP provenance`.
- Red check before implementation failed on missing `metadataSidecarCount`.

Verification:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 3957 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65115 assertions, 0 failures`

Accounting:
- `phpPass`: `3093 -> 3094`
- `mappedOdfMetadataSidecarPackageProvenanceCases`: `1`
- `odfMetadataSidecarPackageProvenanceAssertions`: `24`
- Mapped denominator: `3203`

No Pandoc, office suites, TeX/PDF engines, browser renderers, `zip`/`unzip`,
external validators, online services, live provider tests, or live-service
provider tests were run.
