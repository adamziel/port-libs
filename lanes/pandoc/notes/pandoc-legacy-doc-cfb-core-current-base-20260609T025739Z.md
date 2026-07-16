# Legacy DOC CFB Core Current Base: Duplicate Root Entry Guard

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T025739Z`
Base: `f3cb4f0219cafa35ccd839e4b1e650317d63e7bb`

## Behavior

Native `CompoundFileBinary` now rejects any nonzero directory entry whose
object type is `Root Entry` storage. MS-CFB reserves the Root Entry storage
object for directory ID 0, so a second active Root Entry-shaped storage is
malformed even if it would later fail directory-tree reachability checks.

The guard runs during directory object-field validation, before `LegacyDocReader`
looks up `WordDocument`, summary-information streams, embedded object metadata,
macro-project streams, or WordPress import-review blocks.

## Focused Evidence

Red-first check before the source guard:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1976 assertions, 1 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1977 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok

php -l lanes/pandoc/src/CompoundFileBinary.php
No syntax errors detected in lanes/pandoc/src/CompoundFileBinary.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
```

Lane JSON was updated to mapped denominator `2603`.
`legacyDocCfbCoreCases` and `mappedLegacyDocCfbCoreCases` are now `8`.
`legacyDocCfbCoreAssertions` is now `65`.
`phpPass` remains `2189` because this adds one focused assertion inside the
existing legacy DOC/CFB TestRunner case rather than a new PHP PASS case.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
CFB parser, legacy DOC reader fixtures, focused `LegacyDocReaderTest.php`
coverage, and the WordPress legacy DOC handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap And Follow-Up

This is additive to existing legacy DOC/CFB coverage for CFB header, FAT,
MiniFAT, DIFAT, directory sibling-tree, stream/storage field, root name/color,
root timestamp, unallocated directory entry, FIB, SttbFnm, Pms, property-set,
macro, embedded-object, and WordPress handoff guards. It does not repeat EPUB,
ODF, DOCX, PDF, YAML, CSL/BibTeX, archive, math, charset, or XML/HTML slices.

Good follow-up legacy DOC/CFB slices: additional CFB allocation invariants,
richer header/footer/textbox subdocument routing, or bounded Word binary
style/list handoff.
