# EPUB Direct Manifest Spine Report

Date: 2026-06-13 UTC
Bead: plib-b8ozb

## Scope

This slice closes one bounded native PHP EPUB3 direct package-reader gap in
`EpubPackageReader`: OPF manifest entries now preserve review targets for
query/fragment suffixes, external hrefs, and missing package parts, and spine
itemrefs now expose readability/skipped-entry diagnostics without aborting the
whole direct package import on unreadable external or missing linear XHTML
entries.

The implementation stays inside direct package-reader metadata and XHTML handoff.
It does not modify shared ZIP/OPC internals or invoke Pandoc, EPUBCheck,
zip/unzip, ZipArchive, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

## Ship Call

| Surface | Evidence | Verdict |
| --- | --- | --- |
| EPUB3 package reader | 60 local passing EPUB evidence cases over 9 static upstream EPUB denominator cases. | Partial, not shippable. |
| New slice | One focused `EpubPackageReaderTest` case with 38 assertions for direct manifest suffix diagnostics and spine skipped-entry reporting. | Covered. |
| Pandoc lane totals | 3,318 PHP passes / 0 failures and 3,277 mapped upstream cases over the 2,276-row static Pandoc denominator after rebasing onto current main `2b00b60b67`. | Green, still not global ship-ready. |
| Remaining gaps | Broader direct EPUB package reader structural/content parity and upstream Pandoc runner parity remain incomplete. External validators were not used. | Keep open. |

## Verification

Commands used:

```sh
php -l lanes/pandoc/src/EpubPackageReader.php
php -l lanes/pandoc/tests/EpubPackageReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused result: `EpubPackageReaderTest.php` passed 1 file, 187 assertions, 0
failures.

Full lane result: `lanes/pandoc/tests` passed 45 files, 74472 assertions, 0
failures after rebasing onto current main `2b00b60b67`.
