# pandoc-epub3-package-core-current-base-20260606T052406Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260606T052406Z`
- Accepted base: `acf12984b3f1531972a266d07322821b4a812a25`
- Upstream contract: bounded native EPUB3 package handoff for legacy NCX metadata records, without invoking Pandoc, Cabal, Haskell runners, zip/unzip, ZipArchive, EPUBCheck, browser renderers, online services, or live provider tests.

## Behavior

Legacy EPUB navigation control files carry package-review metadata outside the OPF spine and nav map. This slice extends `EpubReader::readNcxDocument()` so WordPress import review packets can inspect that provenance before accepting a legacy EPUB table of contents.

`EpubReader` now reports:

- NCX root `version` and `xml:lang`;
- NCX `head` meta records, grouped by `name`;
- direct `dtb:uid`, `dtb:depth`, `dtb:totalPageCount`, and `dtb:maxPageNumber` summary fields;
- bounded diagnostics for NCX head meta entries missing `name` or `content`;
- `docTitle`, `docTitleEntries`, `docAuthors`, and `docAuthorDetails` with id/class/language/direction provenance.

The WordPress EPUB package example self-test now verifies NCX title, author, uid, depth, and review-source head metadata in the import report.

## Verification Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1333 assertions, 0 failures
```

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL reports NCX head title and author metadata for package review
1 test files, 1335 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1356 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case, `+23` net focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, `EpubReader`, `OpcPackagePath`, DOM/libxml XML parsing, and the existing WordPress EPUB handoff example. It did not run Pandoc, Cabal solver/build/test commands, Haskell runners, Word, LibreOffice, zip/unzip, ZipArchive, EPUBCheck, browser renderers, JavaScript/media execution, online sanitizers, online services, or live provider tests.

The full upstream-runner blocker is unchanged: the lane still needs a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus Cabal project/package files and Haskell Tasty executable builds before bounded Haskell runner parity can be attempted.

## Non-Overlap

This patch does not repeat accepted OCF mimetype/container validation, OCF metadata/rights/signature sidecars, OPF metadata/manifest/spine parsing, OPF prefix vocabulary resolution, raw XHTML spine handoff, nav XHTML parsing, NCX navMap/pageList target resolution, navigation/spine reconciliation, guide/collection links, alternate renditions, spine and asset fallback chains, bindings, media overlays, trigger/switch review flags, remote-resource reconciliation, encryption/obfuscated font preflight, EPUB CFI fragments, or ZIP package integrity work. The new surface is only legacy NCX head/docTitle/docAuthor metadata handoff and diagnostics.

## Follow-Up

Keep fuller NCX `navList`/`navTarget` support, richer NCX metadata validation, XHTML-to-AST conversion, CSS cascade/media export policy, active media playback, EPUBCheck validation, remote-resource policy, and full Haskell/Pandoc runner comparison as separate bounded slices.
