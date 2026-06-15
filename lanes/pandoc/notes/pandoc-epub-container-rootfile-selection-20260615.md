# Pandoc EPUB Container Rootfile Selection

Slice: `plib-rdnco`, EPUB3 package ingestion core blocker.

`EpubPackage` now exposes a compact `containerRootfileSelection` review packet
in `summary()` and `summary().wordpressImport`. The packet classifies selected,
alternate, missing, non-OPF, suffixed, and media-type-parameterized container
rootfiles, preserving rendition metadata, ZIP byte provenance, byte-exposure
policy, and rootfile/rendition diagnostics without invoking Pandoc, EPUBCheck,
`zip`/`unzip`, `ZipArchive`, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

The focused fixture covers four OCF rootfile declarations: selected OPF,
alternate OPF with query/fragment suffix and media-type parameter, missing OPF,
and an existing non-OPF rootfile. WordPress import summaries now expose the full
selection report, item list, buckets, and diagnostics.

Status delta:

- `phpPass`: `3723 -> 3724`
- `phpFail`: `0`
- Upstream mapped cases: `3742 -> 3743`
- `mappedEpubContainerRootfileSelectionCases = 1`
- `epubContainerRootfileSelectionAssertions = 41`

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 3248 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 88314 assertions, 0 failures
