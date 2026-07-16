# Charset Unicode Width Core Current Base - GB2312 Enclosed Symbols

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T073359Z`
Base accepted HEAD: `4d33e428da4780248f05e2619ed97a382cb59fe0`

## Behavior

- Added bounded native GB2312 A2/A9 symbol-row mappings to `UnicodeText` for:
  - full-stop list digits `A2 B1..B3`;
  - parenthesized digits `A2 C5..C7`;
  - circled digits `A2 D9..DA`;
  - parenthesized CJK numerals `A2 E5..E6`;
  - Roman numerals `A2 F1..F2`;
  - box drawing `A9 A4..A9`.
- Reused the selected pairs through GBK, GB18030, GB12345, ISO-2022-CN, and
  HZ-GB-2312 paths via the existing bounded pair tables.
- Threaded the decoded text through `MarkdownReader` sourceEncoding metadata,
  display-width accounting, `WordPressBlockWriter`, and the WordPress charset
  handoff audit table.

## Source Truth

The bounded source truth is the local static Tcl encoding inventory:

- `/usr/share/tcl9.0/encoding/euc-cn.enc`
- `/usr/share/tcl9.0/encoding/gb12345.enc`

No external charset converter was invoked.

## Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1595 assertions, 0 failures
```

Red-first after adding the focused test before decoder mappings:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1597 assertions, 1 failures
FAIL decodes gb2312 enclosed number and box drawing symbols into wordpress blocks
Expected repairs: 0
Actual repairs: 18
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1612 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok
```

Syntax, JSON, and diff checks:

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
`MarkdownReader`, `WordPressBlockWriter`, the focused PHP test runner, and
local static Tcl charset tables as source truth.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, tar, external template engine, external converter,
external charset converter, TeX/PDF engine, browser renderer, online service,
live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted UTF repair, UTF-16/UTF-32 BOM handling,
Windows/ISO/Mac/DOS single-byte decoders, Shift_JIS/Windows-31J, EUC-JP,
ISO-2022-JP, Big5 base/punctuation/kana/A3/CP950, EUC-TW plane one,
GB1988, GB12345/GBK/GB18030 Chinese phrase coverage, GB2312 A1/A3/A4/A5/A6
symbol coverage, GB18030 four-byte range coverage, EUC-KR/Windows-949,
ISO-2022-KR, ISO-2022-CN base GB2312 shift-state coverage, HZ-GB-2312 base
text, Unicode normalization, emoji/Indic/Thai/Lao grapheme handling, East
Asian ambiguous-width policy, separator wrapping, or default-ignorable/
prepended control width slices.

This patch is limited to a bounded GB2312 enclosed-number and box-drawing
mapping cluster and its WordPress charset audit output under
`lanes/pandoc/**`.

## Next

Pick a non-overlapping charset/Unicode gap such as additional source-backed
CNS/EUC-TW pairs where a local source table is available, GB18030 range gaps,
charset sniffing edges, or another display-width edge not already covered by
current charset slices.
