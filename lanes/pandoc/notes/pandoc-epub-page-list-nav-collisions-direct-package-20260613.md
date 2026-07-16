# EPUB Page-List Nav Collision Direct Package Report

Date: 2026-06-13 UTC
Bead: plib-7qnea

## Scope

This slice adds bounded diagnostics to the direct PHP `EpubPackageReader`
surface for EPUB3 page-list review packets. Existing `epub.toc` entries keep
their label, href, path, fragment, type, and children shape. New
`navigationReport` and `pageListReport` metadata reports:

- page-list targets that also appear in `toc` or `landmarks`;
- repeated page-list fragment targets;
- page-list targets mapped to non-linear spine entries;
- page-list targets that move backward through the package spine;
- duplicate OPF manifest ids and duplicate spine idrefs.

The implementation stays inside the direct package reader/report surface. It
does not invoke Pandoc, EPUBCheck, zip/unzip commands, ZipArchive, browser
renderers, Node tooling, online services, live providers, or external
validators.

## Verification

```sh
php -l lanes/pandoc/src/EpubPackageReader.php
php -l lanes/pandoc/tests/EpubPackageReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused result: `EpubPackageReaderTest.php` passed `1` file, `241`
assertions, `0` failures.

Full lane result: `lanes/pandoc/tests` passed `46` files, `75692`
assertions, `0` failures.

## Accounting

- `phpPass` moves `3358 -> 3359`; `phpFail` remains `0`.
- Mapped upstream denominator moves `3318 -> 3319`.
- `mappedEpubPageListNavCollisionCases` is `1`.
- `epubPageListNavCollisionAssertions` is `28`.
