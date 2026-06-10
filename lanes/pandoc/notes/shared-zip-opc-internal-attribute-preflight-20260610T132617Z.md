# Shared ZIP/OPC Internal Attribute Preflight

Implemented one bounded shared ZIP package primitive in native PHP.

`ZipPackage::internalAttributePolicyPreflight()` now scans central-directory
internal file attribute fields before package construction. The raw summary
reports text-attribute bits, unknown internal bits, source central-directory
indexes, offsets, local-header offsets, and per-entry policy issues without
trusting local header names or exposing payload bytes.

`ZipPackage::rawStrictImportPreflight()` now embeds that summary as
`internalAttributes` and carries `internal-file-attributes` diagnostics even
when package instantiation is blocked by a later local-header spoof. This keeps
DOCX/EPUB/ODF OPC containers from losing central-directory provenance just
because another ZIP consistency check rejects construction first.

## Scope

This is shared ZIP/OPC package preflight only. It does not change DOCX, EPUB,
ODF, PDF/Typst, relationship graph, decompression, writer, or archive extraction
behavior. It does not invoke Pandoc, office suites, TeX/PDF engines, browsers,
zip/unzip, external validators, online services, or live provider tests.

## Metric

- `lane-status.json` `phpPass`: `2972 -> 2973`
- `phpFail`: `0`

## Verification

```bash
php -l lanes/pandoc/src/ZipPackage.php
php -l lanes/pandoc/tests/ZipPackageTest.php
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused result:

```text
1 test files, 2935 assertions, 0 failures
```

Post-rebase full lane result:

```text
44 test files, 60190 assertions, 0 failures
```
