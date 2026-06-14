# ODF/ODT Package Signature Review

This slice adds bounded native PHP package-review metadata for OpenDocument
signature sidecars in the rich `OdfReader` path. It exposes `packageSignatures`
at the top level, on the document attributes, in `metadata.odfPackageSignatures`,
and in the import report while keeping parsed XML signature metadata under the
existing `signatureMetadata` report.

The focused fixture covers declared, missing, encrypted, invalid-media-type, and
undeclared `META-INF/*signatures.xml` sidecars. Signature package parts are now
classified with `signaturePackagePart`, use `signature-package-bytes-blocked`
for declared non-encrypted sidecars, and stay out of document media handoff even
when a manifest declares an invalid media type such as `image/png`.

Verification before status update:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 4688 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfOdtShipReadinessStatusTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `5 test files, 6169 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 80299 assertions, 0 failures`

Counter deltas:

- `phpPass`: `3444 -> 3445`
- upstream mapped denominator: `3393 -> 3394`
- `mappedOdtReaderPackageSignatureProvenanceCases`: `1 -> 2`
- `odtReaderPackageSignatureProvenanceAssertions`: `4 -> 72`
- ODF/ODT readiness local mapped cases: `52 -> 53`
- ODF/ODT readiness focused assertions: `6101 -> 6169`

No Pandoc, office suite, TeX, browser, Node, zip/unzip, external validator,
online service, live provider, or live-service provider was used.
