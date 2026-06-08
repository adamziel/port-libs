# pandoc-charset-unicode-width-core-current-base-20260608T172812Z

## Scope

Implemented native GB18030 four-byte range-pointer decoding in `UnicodeText`.
The slice replaces the old tiny four-byte whitelist with the WHATWG GB18030
range table, keeps the special PUA pointer override, and fails closed for the
specified pointer gap and pointers beyond Unicode.

Source truth:

- https://encoding.spec.whatwg.org/#gb18030-decoder
- https://encoding.spec.whatwg.org/index-gb18030-ranges.txt

## Evidence

Red-first probe before the implementation showed valid range pointers such as
`81308130`, `8130A438`, `8130D332`, `82358F33`, `84318236`, and `90308130`
decoding as U+FFFD outside the previous whitelist.

Focused verification after the patch:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 896 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - `charset unicode handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted BOM, UTF-16, UTF-32, single-byte legacy,
Shift_JIS, EUC-JP, ISO-2022-JP, Big5, GBK, HZ-GB-2312, EUC-KR, normalization,
line-ending, emoji, Indic, Myanmar, Khmer, Tibetan, format-control, or
display-breakpoint slices. It only expands GB18030 four-byte range-pointer
coverage used by MarkdownReader and WordPress charset audit handoff.

## Dependency Closure

No new support component is needed. The patch reuses native PHP
`UnicodeText`, `MarkdownReader`, display-width accounting, and
`WordPressBlockWriter`. Pandoc, Cabal/Haskell runners, browser charset
decoders, external charset converters, online services, live provider tests,
and live-service provider tests remain out of scope.

