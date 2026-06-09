# Charset Unicode Width Core Current Base - Big5 Punctuation

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T062840Z`
Base accepted HEAD: `d9055a06d30a55d79eba71a2d656134139a1a3c6`

## Behavior

- Added bounded native Big5 punctuation and quote byte mappings in
  `UnicodeText::decodeBytes()`.
- Source truth is the local static Tcl table
  `/usr/share/tcl9.0/encoding/big5.enc`.
- New mapped pairs include:
  - `A1 40` ideographic space, `A1 42` ideographic comma, `A1 44`
    fullwidth full stop, `A1 46..49` fullwidth semicolon/colon/question/
    exclamation;
  - `A1 75..76` corner quotes and `A1 A5..A8` curly single/double quotes;
  - `A1 45` bullet, `A1 B0` reference mark, `A1 B1` section sign, and
    `A1 B2` ditto mark.
- Preserved the existing CP950 `A1 45` override to U+2027, so Windows-950
  still differs from base Big5 where the local `cp950.enc` table differs.
- Threaded the decoded source through `MarkdownReader`, `UnicodeText`
  display-width accounting, and `WordPressBlockWriter` in the existing
  WordPress charset handoff example.

## Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1536 assertions, 0 failures
```

Red-first probes after adding the focused test before final expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1539 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1550 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok
```

Syntax and metadata checks:

```text
php -l lanes/pandoc/src/UnicodeText.php
No syntax errors detected in lanes/pandoc/src/UnicodeText.php

php -l lanes/pandoc/tests/UnicodeTextTest.php
No syntax errors detected in lanes/pandoc/tests/UnicodeTextTest.php

php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-charset-unicode-handoff.php

php -r 'json_decode lane-status and manifest'
lanes/pandoc/lane-status.json json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json json ok

git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, the focused PHP test runner, the
existing WordPress charset handoff example, and local static charset tables
only as source truth.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, external charset converter,
TeX/PDF engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted Big5 base Chinese fixture coverage, Big5
two-codepoint pointer sequences, Big5 kana/fullwidth digit mappings, CP950
Euro/private extension pairs, EUC-TW plane-one mappings, ISO-2022-CN GB2312,
GB1988/GBK/GB12345/GB18030, EUC-JP JIS0212, KOI8, or display-width-only
slices. It is limited to bounded Big5 punctuation/quote byte decoding and
WordPress charset audit output under `lanes/pandoc/**`.

## Next

Pick a non-overlapping charset or width gap such as additional source-backed
Big5/CNS pairs, source-backed Big5-HKSCS mappings, or another display-width
edge not already covered by current charset slices.
