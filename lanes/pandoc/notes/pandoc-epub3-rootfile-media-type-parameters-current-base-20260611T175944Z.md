# pandoc-epub3-rootfile-media-type-parameters-current-base-20260611T175944Z

Slice: `plib-w1z9n`, EPUB3 package ingestion core blocker.

Base: `origin/main` `30462ed7c`.

## Scope

OCF `container.xml` rootfile records already carried package path and ZIP entry
provenance, while OPF manifest records already preserved media-type parameter
details. This slice brings that same bounded media-type provenance to rootfile
records and rootfile validation summaries.

## Change

`EpubPackage` now selects OPF rootfiles by media-type base, so a container
rootfile such as `application/oebps-package+xml; charset=UTF-8` still resolves
to the OPF package document.

Rootfile records and validation handoff now preserve:

- raw and normalized media type;
- media-type base;
- parsed parameter list/map and parameter-name summaries;
- rootfile-specific media-type diagnostics;
- WordPress import aliases for rootfile media-type parameter handoff.

The change does not run or depend on Pandoc, EPUBCheck, zip/unzip, browser
engines, or external validators.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1 test files, 1443 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65571 assertions, 0 failures`
