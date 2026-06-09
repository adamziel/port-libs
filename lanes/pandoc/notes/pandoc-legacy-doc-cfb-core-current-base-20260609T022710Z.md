# Legacy DOC CFB Core Current Base: OLE Property-Set Directory Guards

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T022710Z`
Base: `ad0b29726a9f952ccc81c677e4a1cb6fc0f76215`

## Behavior

Native `LegacyDocReader` now validates OLE property-set section directories
before exposing `SummaryInformation` or `DocumentSummaryInformation` metadata.
It rejects:

- duplicate property identifiers;
- property directory sizes that point beyond the section;
- property value offsets that point inside the property directory;
- misaligned or out-of-section property value offsets.

This prevents malformed legacy Word CFB packets from silently overwriting
metadata keys or using directory bytes as typed property values.

## Focused Evidence

Baseline before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1929 assertions, 0 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1933 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok

php -l lanes/pandoc/src/LegacyDocReader.php
No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
```

Lane JSON was updated to mapped denominator `2574`.
`legacyDocCfbCoreCases` and `mappedLegacyDocCfbCoreCases` are now `8`.
`legacyDocCfbCoreAssertions` is now `68`.
`phpPass` is now `2149` because this adds one named focused TestRunner PASS
case.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the existing
CFB reader, legacy DOC property-set parser, focused fixture builders, and
WordPress legacy DOC handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap And Follow-Up

This is additive to existing legacy DOC/CFB coverage for FIB text ranges,
encryption preflight, DOP metadata, associated string tables, OLE scalar/vector
metadata, embedded object metadata, macros, fields, and CFB directory/FAT
guards. It does not repeat EPUB, ODF, DOCX, PDF, YAML, CSL/BibTeX, archive, or
XML/HTML support-library slices.

Good follow-up legacy DOC/CFB slices: property dictionary/vector edge cases,
Word table/list expansion, or additional FIB/CLX corruption guards.
