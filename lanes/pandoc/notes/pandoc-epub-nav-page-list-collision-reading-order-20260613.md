# pandoc-epub-nav-page-list-collision-reading-order-20260613

This slice combines the compact `EpubPackageReader` normalized navigation
collision report with the page-list reading-order report. Collision diagnostics
that involve page-list entries now carry page-list target summaries, including
page-list item indexes, exact package targets, duplicate page-target state,
duplicate spine-target state, spine indexes, and reading-order spine indexes.

The added metadata stays inside `epub.navReport` diagnostics. The existing
`epub.toc` entry shape remains `label`, `href`, `path`, `fragment`, `type`, and
`children`.

Coverage adds a focused native fixture for:

- page-list target reading-order indexes inside normalized collision groups.
- duplicate page-list target and duplicate spine target ordering.
- cross-section toc/landmarks/page-list collision summaries with page-list
  reading-order metadata attached.
- external and unsafe target precedence over fragment diagnostics.
- unchanged `epub.toc` page-list entry shape.

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, Node,
online service, live provider test, or external validator is invoked.

Verification after rebase onto `origin/main`:

```sh
php -l lanes/pandoc/src/EpubPackageReader.php
php -l lanes/pandoc/tests/EpubPackageReaderTest.php
jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json
git diff --check origin/main..HEAD
php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused `EpubPackageReaderTest.php` passed 1 file, 380 assertions, 0
failures. The full `lanes/pandoc/tests` suite passed 46 files, 75,831
assertions, 0 failures.
