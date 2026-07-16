# ODF Reader Font Package Byte Policy Provenance

## Summary

`OdfReader` now carries `font-package-bytes-blocked` through ODT `Fonts/`
sidecar review metadata, including undeclared font package entries. The font
package summary, undeclared-entry list, package provenance inventory, and
package identity policy counts now agree on the same blocked-byte policy for
known font sidecars, while unrelated undeclared package entries continue to use
`undeclared-package-entry-no-bytes`.

## Accounting

- `odfFontPackageBytePolicyCases`: `1`
- `odfFontPackageBytePolicyAssertions`: `42`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderFontPackageBytePolicyTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderFontPackageBytePolicyTest.php`
  - `1 test files, 42 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 5079 assertions, 0 failures`

No Pandoc, office suites, `zip`/`unzip`, browser engines, external validators,
online services, or live provider tests were invoked.
