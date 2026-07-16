# Charset Unicode Width Core Current Base - GB1988

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T042553Z`
Base: `11fc57ec36d6cc974a7a65f55020cfb9f1af6d59`

## Behavior

Added native GB1988 decoding in `UnicodeText` for `gb1988`,
`gb_1988-80`, and `csISO57GB1988` labels. Source truth was the local Tcl
table at `/usr/share/tcl9.0/encoding/gb1988.enc`: byte `0x24` maps to
U+00A5, byte `0x7E` maps to U+203E, and `0xA1..0xDF` maps to
U+FF61..U+FF9F. Other upper bytes remain undefined and are repaired as
U+FFFD.

The focused test now verifies byte decoding, undefined-byte repair counts,
MarkdownReader `sourceEncoding` provenance, WordPress block output, and
display-width audit behavior for the decoded reviewer text.

## Red-First Evidence

Baseline focused command:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1387 assertions, 0 failures
```

Red probes before implementation showed GB1988 input fell through to UTF-8 or
UTF-8 repair, leaving `$~` unchanged and repairing halfwidth bytes instead of
mapping them through the GB1988 table.

## Final Evidence

```text
php -l lanes/pandoc/src/UnicodeText.php
No syntax errors detected in lanes/pandoc/src/UnicodeText.php

php -l lanes/pandoc/tests/UnicodeTextTest.php
No syntax errors detected in lanes/pandoc/tests/UnicodeTextTest.php

php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-charset-unicode-handoff.php

php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1400 assertions, 0 failures

php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok

php -r 'json_decode lane-status and manifest'
lanes/pandoc/lane-status.json: No error
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json: No error

git diff --check -- lanes/pandoc
passed with no output
```

`lane-status.json` moved `phpPass` from `2298` to `2299`.
`UPSTREAM_TEST_MANIFEST.json` moved mapped denominator from `2698` to `2699`,
`mappedCharsetUnicodeWidthCoreCases` from `9` to `10`, and
`charsetUnicodeWidthCoreAssertions` from `65` to `78`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, the focused PHP test runner, and the
lane-local charset handoff smoke example. No Pandoc, Cabal/Haskell runner,
Word, LibreOffice, zip/unzip, TeX/PDF engine, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat existing GBK, GB12345, GB18030, Big5, ISO-2022-KR,
KOI8, UTF-16 malformed guard, or Unicode display-width slices.

## Next

Consider a bounded JIS0212/EUC-JP plane-2 mapping case, or another
display-width edge not already covered by the charset core tests.
