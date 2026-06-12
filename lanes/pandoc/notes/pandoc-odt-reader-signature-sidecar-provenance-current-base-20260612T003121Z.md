# ODT reader signature sidecar package provenance

Bead: `plib-dc7io`

Base: `origin/main` `8881a8911c`

This slice keeps ODF XML signature sidecars classified as package-review metadata in the `OdfReader` path:

- `META-INF/*signatures.xml` ZIP entries now receive the `package-signature` package provenance role.
- Signature sidecars are excluded from document media handoff even when a malformed manifest declares an image media type.
- Declared and undeclared signature sidecars stay visible in package provenance without exposing them as content media.

Verification:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 4078 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 67864 assertions, 0 failures`

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
