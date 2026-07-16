# EPUB Manifest Dependency Encrypted Policy

Slice: `pandoc-epub-manifest-dependency-encrypted-policy`

This bounded native PHP EPUB3 package-ingestion slice extends compact OPF
manifest dependency review packets for fallback, fallback-style, and
media-overlay edges whose targets are encrypted, obfuscated fonts, or external
manifest resources while preserving existing source-side ZIP provenance.

`EpubPackage` manifest dependency inventory now exposes:

- distinct `obfuscated-font-manifest-dependency-target` diagnostics;
- encrypted and obfuscated-font dependency byte buckets;
- encrypted target part names, obfuscated font target part names, and external
  dependency target ids in the compact package report case.

The change stays under `lanes/pandoc` and does not invoke Pandoc, EPUBCheck,
zip/unzip, ZipArchive, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 3950 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 181 files, 165537 assertions, 0 failures

Accounting:

- `phpPass`: 15331 -> 15332
- `phpFail`: 0
- mapped upstream cases: 15002 -> 15003
- `mappedEpubManifestDependencyInventoryCases`: 3 -> 4
- `epubManifestDependencyInventoryAssertions`: 143 -> 179
