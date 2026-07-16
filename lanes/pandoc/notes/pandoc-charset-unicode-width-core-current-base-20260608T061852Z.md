# Pandoc Charset Unicode Width Current-Base Slice

Session: `port-dev-pandoc-charset-unicode-20260608T061852Z`
Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260608T061852Z`
Base accepted HEAD: `2d05ed88b1dffcb6c3d210fc88a0ebf93fb3fb5a`

## Behavior

Implemented the bounded WHATWG Big5 two-codepoint pointer exceptions for byte
pairs `0x8862`, `0x8864`, `0x88A3`, and `0x88A5`. These pairs now decode as
decomposed Latin E/e plus macron/caron combining marks, keep zero repair
counts, remain intact as grapheme clusters, and hand off as one display column
per cluster for Markdown/WordPress table alignment.

This is intentionally smaller than a full Big5 index import. It covers the
known multi-codepoint exceptions that the existing native Big5 pair table could
not represent without introducing an external charset converter or a broad
generated mapping.

## Red-First Evidence

Before the implementation, the accepted base repaired all four pairs:

```text
8862 "�" repairs=1 width=1
8864 "�" repairs=1 width=1
88a3 "�" repairs=1 width=1
88a5 "�" repairs=1 width=1
```

## Verification

```text
php -l lanes/pandoc/src/UnicodeText.php
No syntax errors detected in lanes/pandoc/src/UnicodeText.php

php -l lanes/pandoc/tests/UnicodeTextTest.php
No syntax errors detected in lanes/pandoc/tests/UnicodeTextTest.php

php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-charset-unicode-handoff.php

php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 800 assertions, 0 failures

php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Status Delta

Mapped one additional charset/Unicode support case.

- `lane-status.json` `phpPass`: `1548` -> `1549`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1969` -> `1970`
- `charsetUnicodeWidthCoreCases`: `9` -> `10`
- `mappedCharsetUnicodeWidthCoreCases`: `9` -> `10`
- `charsetUnicodeWidthCoreAssertions`: `65` -> `70`
- Focused `UnicodeTextTest.php`: `795` -> `800` assertions

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`UnicodeText` byte decoder/display-width helper, `MarkdownReader`,
`WordPressBlockWriter`, `UnicodeTextTest`, and the lane-local WordPress charset
handoff example.

No Pandoc, Cabal/Haskell runner, external charset converter, browser renderer,
online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat existing charset coverage for BOM/UTF-16/UTF-32,
Windows/ISO single-byte families, Shift_JIS, EUC-JP, ISO-2022-JP, basic Big5
pairs, GBK, GB18030 four-byte examples, EUC-KR, HZ-GB-2312, Unicode width
policies, Indic/Myanmar/Khmer conjuncts, emoji sequences, or prepended format
controls.

Follow-up candidates remain broader GB18030 range decoding, additional
Big5-HKSCS index coverage beyond these four pointer exceptions, HTML/XML
charset sniffing edge cases, and Unicode line-break or grapheme-cluster
boundaries.
