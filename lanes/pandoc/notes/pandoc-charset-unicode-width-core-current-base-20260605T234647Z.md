# Pandoc Charset/Unicode Width Core - GB18030 Four-Byte Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T234647Z`
Base: `efecd2a4fb0c5195ad7f93389be8d6723c15a8cd`

## Change

- Split true `gb18030` labels from the existing bounded `gbk`/`gb2312` alias
  path so import reports can preserve canonical GB18030 source metadata.
- Added a bounded native GB18030 decoder path that reuses the accepted GBK
  two-byte table and decodes selected four-byte review-packet sequences:
  U+0100, U+1F600, and U+20000.
- Added the GB18030 euro pair and kept malformed or unsupported four-byte
  sequences explicit as U+FFFD repairs without swallowing following ASCII.
- Extended the WordPress charset handoff smoke with a `GB18030 source` audit
  row carrying decoded text, canonical source encoding, and display width.

## Source Truth

The bounded source truth is the GB18030 decoder shape: ASCII bytes pass
through, GBK-compatible two-byte pairs remain available, and four-byte
sequences use the `lead digit lead digit` byte form. This slice keeps the
mapping table intentionally small for selected reviewer bytes and does not
ingest the full generated GB18030 range/index tables.

No current-base pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, or live provider test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 483 assertions, 0 failures`

After adding the GB18030 test and before the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 484 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'gb18030')` returned canonical
    `gbk` instead of `gb18030`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 496 assertions, 0 failures`
  - Delta: +1 focused PASS case / +13 assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`UnicodeText`, `MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter`
support path. Full generated GB18030 range tables, Windows-949 extension
pairs, KOI8/ISO-8859-5 Cyrillic families, declared HTML/XML charset sniffing,
terminal-profile-specific emoji width variants, broader Unicode property-table
refreshes, and full upstream Pandoc Haskell runner parity remain separate
bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/15, MacRoman, Shift_JIS/Windows-31J,
EUC-JP, ISO-2022-JP, Big5, bounded GBK/GB2312 two-byte decoding, EUC-KR,
HZ-GB-2312, Unicode normalization, emoji presentation and tag/ZWJ clusters,
supplementary East Asian wide ranges, ambiguous-width policy, Unicode
separator wrapping, default-ignorable controls, prepended format-control
zero-width accounting, Indic virama clusters, Myanmar/Khmer conjuncts,
Markdown/HTML reader behavior, table geometry, DOCX/ODF/EPUB/PDF,
syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or
upstream-runner dependency audit slices.
