# pandoc-odf-package-sidecar-roles-current-base-20260611T173314Z

Slice: `plib-lqn1o`, ODF/ODT package ingestion provenance.
Required base: `2dc138953`.

## Change

`OdfReader` now labels RDF metadata sidecars and XML signature sidecars in
package provenance role inventories. The role inventory distinguishes
manifest-declared and undeclared sidecar parts while keeping RDF and signature
payloads out of document media byte handoff.

Focused coverage verifies declared RDF media-type parameter provenance,
undeclared RDF sidecars, declared and undeclared signature sidecars, document
manifest provenance parity, and the media handoff guard.

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed: 1 test file, 3886 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 64325 assertions, 0 failures.
