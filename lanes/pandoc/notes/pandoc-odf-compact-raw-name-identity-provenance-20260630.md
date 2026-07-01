# Pandoc ODF Compact Raw-Name Identity Provenance

Hook: `plib-rc5ev`

## Scope

Compact ODT package identity carries ZIP raw entry-name provenance that is
already available in `packageInventory`: raw name hex, name encoding,
decoded-name match status, legacy encoding flags, Unicode path extra-field
flags, and the aggregate raw-name provenance counters.

This keeps metadata-only package identity sensitive to packages that resolve to
the same decoded ODF part path but arrive through different ZIP name encodings,
such as a CP437 raw name versus an equivalent UTF-8 raw name. Package bytes stay
governed by the existing ODF byte-exposure policies.

## Direct Parity Accounting

- ODF/ODT package-ingestion surface extended within the existing compact
  package identity path.
- No direct-format denominator or format-token accounting changed.
- Focused compact package coverage remains the validation gate for this slice.

## Replacement Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
  - `2 test files, 2521 assertions, 0 failures`

No Pandoc, office suites, TeX/browser engines, zip/unzip, ZipArchive, Jupyter,
Node tooling, external validators, online services, or live provider tests were
invoked.
