# Shared ZIP Selected Entry Duplicate Handoff

Implemented one bounded shared ZIP package handoff primitive in native PHP.

`ZipPackage::entryHandoffPreflight()` now detects duplicate selected-entry
requests when they normalize to the same ZIP entry name and the same expected
handoff kind. Duplicate requests are reported with request indexes, original
requested spellings, roles, expected kinds, required/optional counts, and
blocked duplicate entry summaries before DOCX, EPUB, or ODT readers consume
the requested package bytes.

The duplicate key includes the expected kind, so existing cross-kind review
checks such as asking for `word/media/` once as a directory and once as a file
continue to surface their existing directory/file diagnostics instead of being
collapsed into the duplicate-request policy.

## Scope

This is a shared ZIP selected-entry handoff preflight only. It does not change
ZIP central-directory parsing, decompression, OPC relationship resolution,
DOCX body parsing, EPUB/ODF package readers, archive extraction policy, or
writer output. It does not invoke Pandoc, office suites, zip/unzip, browser
renderers, external validators, online services, or live provider tests.

## Metric

- `lane-status.json` `phpPass`: `2974 -> 2975`
- `phpFail`: `0`
- Mapped direct-format parity denominator: unchanged; this is shared package
  handoff coverage, not a new reader/writer format registration.

## Verification

```bash
php -l lanes/pandoc/src/ZipPackage.php
php -l lanes/pandoc/tests/ZipPackageTest.php
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused result:

```text
1 test files, 2970 assertions, 0 failures
```

Full lane result:

```text
44 test files, 60233 assertions, 0 failures
```
