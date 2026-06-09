# Charset Unicode Width Core Current Base - Big5 A3 Greek/Bopomofo

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T070732Z`
Base accepted HEAD: `030e94cf137586963da96dca64555cebe2ff01ee`

## Behavior

- Added bounded native Big5 A3-row mappings in `UnicodeText::decodeBytes()` for
  a focused Greek/Bopomofo review-packet cluster.
- Source truth is the local static Tcl table
  `/usr/share/tcl9.0/encoding/big5.enc`.
- New mapped pairs include `A3 44`, `A3 50`, `A3 5B`, `A3 5C`,
  `A3 73`, `A3 74`, `A3 75`, and `A3 7E`.
- The focused test verifies:
  - canonical `big5` sourceEncoding provenance through `MarkdownReader`;
  - decoded source handoff through `WordPressBlockWriter`;
  - narrow and East-Asian-wide display-width accounting for ambiguous Greek
    letters and wide Bopomofo letters;
  - an adjacent unmapped A3 pair still repairs to U+FFFD.
- The WordPress charset handoff example now includes a Big5 A3 audit row with
  narrow/wide display-width metadata.

## Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1562 assertions, 0 failures
```

Red-first after adding the focused test before decoder mappings:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1564 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1579 assertions, 0 failures
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
`MarkdownReader`, `WordPressBlockWriter`, focused PHP tests, the existing
WordPress charset handoff example, and local static charset tables only as
source truth.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, external charset converter,
TeX/PDF engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted Big5 base Chinese fixture coverage, Big5
two-codepoint pointer sequences, Big5 punctuation/quotes, Big5 kana/fullwidth
digit mappings, CP950 Euro/private extension pairs, EUC-TW plane-one mappings,
ISO-2022-CN GB2312, GB1988/GBK/GB12345/GB18030, EUC-JP JIS0212, KOI8, or
display-width-only slices. It is limited to bounded Big5 A3-row
Greek/Bopomofo byte decoding and WordPress charset audit output under
`lanes/pandoc/**`.

## Next

Pick a non-overlapping charset or width gap such as additional source-backed
Big5/CNS pairs, source-backed Big5-HKSCS mappings, charset sniffing edges, or
another display-width edge not already covered by current charset slices.
