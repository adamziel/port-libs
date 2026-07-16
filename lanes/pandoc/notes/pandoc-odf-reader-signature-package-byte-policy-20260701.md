# ODF Reader Signature Package Byte Policy Provenance

## Summary

`OdfReader` now carries `signature-package-bytes-blocked` through ODT
`META-INF/*signatures.xml` sidecar review metadata, including undeclared
signature package entries. The package signature summary, undeclared-entry list,
package provenance inventory, and package identity policy counts now agree on
the same blocked-byte policy for known signature sidecars, while unrelated
undeclared package entries continue to use `undeclared-package-entry-no-bytes`.

## Accounting

- `odfSignaturePackageBytePolicyCases`: `1`
- `odfSignaturePackageBytePolicyAssertions`: `41`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderSignaturePackageBytePolicyTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderSignaturePackageBytePolicyTest.php`
  - `1 test files, 41 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 5081 assertions, 0 failures`

No Pandoc, office suites, `zip`/`unzip`, browser engines, external validators,
online services, or live provider tests were invoked.
