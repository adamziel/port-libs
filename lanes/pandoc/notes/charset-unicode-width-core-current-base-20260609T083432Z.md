# Charset Unicode Width Core Current Base - EUC-TW CNS Row Pairs

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T083432Z`
Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

- Added a bounded native EUC-TW/CNS11643 plane-one row-pair mapping cluster in
  `UnicodeText`:
  - `A2 A1..A3` -> `U+5322`, `U+5304`, `U+5303`;
  - `A3 A1..A3` -> `U+4F64`, `U+51E8`, `U+4F67`.
- Threaded the decoded text through `MarkdownReader::readBytes()` source
  encoding metadata, display-width accounting, `WordPressBlockWriter`, and the
  existing WordPress charset audit example.
- Preserved the existing unsupported extended-plane fallback for `8E A2 ...`
  sequences; this slice only adds source-backed plane-one row pairs.

## Source Truth

The bounded byte-to-codepoint source truth is the local static Tcl encoding
inventory:

- `/usr/share/tcl9.0/encoding/cns11643.enc`

No external charset converter was invoked.

## Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1626 assertions, 0 failures
```

Red-first after adding the focused test before decoder mappings:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1628 assertions, 1 failures
FAIL decodes bounded euc tw cns row pairs into wordpress blocks
Expected repairs: 0
Actual repairs: 6
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1636 assertions, 0 failures
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
`MarkdownReader`, `WordPressBlockWriter`, the focused PHP test runner, and the
local static Tcl CNS11643 table as source truth.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, tar, external template engine, external converter,
external charset converter, TeX/PDF engine, browser renderer, online service,
live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted UTF repair, UTF-16/UTF-32 BOM handling,
Windows/ISO/Mac/DOS single-byte decoders, Shift_JIS/Windows-31J, EUC-JP,
ISO-2022-JP, Big5 base/punctuation/kana/A3/CP950, EUC-TW initial plane-one
`A1` row fixture, GB1988, GB12345/GBK/GB18030 Chinese phrase coverage,
GB2312 A1/A2/A3/A4/A5/A6/A9 symbol rows, GB18030 four-byte range coverage,
EUC-KR/Windows-949, ISO-2022-KR, ISO-2022-CN, HZ-GB-2312, Unicode
normalization, emoji/Indic/Thai/Lao grapheme handling, East Asian ambiguous
width policy, separator wrapping, or default-ignorable/prepended control width
slices.

This patch is limited to a bounded EUC-TW CNS11643 row A2/A3 mapping cluster
and its WordPress charset audit output under `lanes/pandoc/**`.

## Next

Pick another non-overlapping charset/Unicode gap such as additional
source-backed CNS/EUC-TW pairs, GB18030 range gaps, charset sniffing edges, or
another display-width edge not already covered by current charset slices.
