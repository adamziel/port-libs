# Pandoc EPUB3 Package Core Current Base: Manifest Byte Provenance

Slice: `pandoc-epub3-package-core-current-base-20260609T024535Z`
Base: `8777c8beb8e8ee92e06182f517954c8c26435cdc`

## Behavior

Native `EpubReader` now records SHA-256 byte provenance for package-local OPF
manifest resources alongside existing byte length and CRC32 metadata. The
native import report exposes `manifest.byteProvenance` with item indexes and
hashed, encrypted, missing, and external resource summaries.

Remote OPF manifest resources remain external/unfetched and hash-free. Missing
local resources remain diagnostic and hash-free. OCF encrypted or obfuscated
resources clear their package hash after encryption policy is attached and stay
`canExposeBytes=false`.

## Focused Evidence

No lane rework note existed for this session before implementation.

Red-first focused check after adding the new assertion case and before the
behavior patch:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 3439 assertions, 1 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 3463 assertions, 0 failures

php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok

php -l lanes/pandoc/src/EpubReader.php
No syntax errors detected in lanes/pandoc/src/EpubReader.php

php -l lanes/pandoc/tests/EpubReaderTest.php
No syntax errors detected in lanes/pandoc/tests/EpubReaderTest.php

php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-epub3-package-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
```

Focused delta: +1 PHP TestRunner PASS case and +25 focused assertions.
`phpPass` is now `2181`. The mapped denominator is now `2594`;
`epub3PackageCoreCases` and `mappedEpub3PackageCoreCases` are now `7`, and
`epub3PackageCoreAssertions` is now `137`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, `EpubReader`, OCF encryption policy, import-report structures,
focused EPUB package fixtures, and WordPress EPUB3 handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external template engine, TeX/PDF engine, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap And Follow-Up

This does not repeat OCF container/rootfile discovery, OPF metadata/vendor/
collection parsing, manifest/spine parsing, nav/NCX handling, XHTML resource
scans, CSS resource reports, remote-resource declarations, cover/asset reports,
encryption policy exposure, CFI/media fragments, SMIL overlays, or ZIP
integrity checks. The new surface is only OPF manifest byte hash provenance and
hash suppression policy for encrypted, remote, or missing resources.

Good follow-up EPUB3 slices: XHTML-to-AST conversion, CSS cascade/export
policy, EPUBCheck-style static validation, richer encrypted-resource decisions,
or full upstream runner dependency planning.
