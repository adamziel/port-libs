# pandoc-odt-signature-package-role-provenance-current-base-20260612T004746Z

## Summary

Implemented one bounded ODF/ODT OpenDocument package ingestion slice in native PHP.

- `OdfReader` package provenance now classifies `META-INF/*signatures.xml` ZIP entries as `package-signature` package roles.
- Declared signature sidecars keep `manifest-declared` role and manifest media-type provenance.
- Undeclared signature sidecars keep both `package-signature` and `undeclared-package-entry` roles.
- Signature sidecars remain out of document media byte handoff.

This aligns the full ODF reader package inventory with the compact `OpenDocumentPackage` role-bucket surface without validating signatures, verifying digests, loading certificates, or making trust decisions.

## Verification

Syntax:

```text
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/OdfReaderTest.php
```

Focused:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 4100 assertions, 0 failures
```

Full:

```text
php tools/run-tests.php lanes/pandoc/tests
44 test files, 68011 assertions, 0 failures
```

No Pandoc, office suites, TeX/PDF engines, browser renderers, zip/unzip command, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Accounting

- `phpPass`: `3154 -> 3155`
- `phpFail`: `0`
- `mappedOdtPackageSignatureRoleCases`: `1`
- `odtPackageSignatureRoleAssertions`: `16`

## Non-Overlap

This does not repeat signature XML parsing, signature reference target diagnostics, compact package signature summaries, thumbnail package metadata, manifest suffix provenance, or settings inventory roles. It only closes the full `OdfReader` package inventory role classification gap for signature sidecars.
