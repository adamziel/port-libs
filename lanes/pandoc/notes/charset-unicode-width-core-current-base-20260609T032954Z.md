# Pandoc Charset/Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T032954Z`
Accepted base: `507b06f9840603abbb77bf4b360c0377f959830e`

## Behavior

This slice adds bounded ISO-2022-KR byte decoding to the native PHP charset
handoff path. `UnicodeText::decodeBytes()` now recognizes ISO-2022-KR labels,
keeps the `ESC $ ) C` designation escape in ASCII state, switches to KS X
1001 bytes on SO, returns to ASCII on SI, and maps Korean pairs through the
existing bounded EUC-KR pair table. Malformed escapes, unmapped pairs, missing
trails before SI, and final shifted state repair to U+FFFD.

The focused coverage proves the decoded text, sourceEncoding provenance,
display width, WordPress paragraph output, and WordPress charset audit row for
an ISO-2022-KR review packet.

## Red-First Evidence

Command before implementation after adding the focused test:

```sh
php -l lanes/pandoc/src/UnicodeText.php
php -l lanes/pandoc/tests/UnicodeTextTest.php
php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php
```

Result: no syntax errors detected in all changed PHP files.

```sh
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
```

Result:

```text
1 test files, 1327 assertions, 1 failures
```

Failure: ISO-2022-KR source bytes fell back to `utf-8` instead of preserving
`iso-2022-kr` provenance.

## Final Verification

```sh
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
```

Result:

```text
1 test files, 1343 assertions, 0 failures
```

```sh
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
```

Result:

```text
charset unicode handoff self-test ok
```

```sh
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/pandoc
```

Result: JSON validation and lane diff whitespace check passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `UnicodeText`,
the existing bounded EUC-KR pair map, `MarkdownReader` sourceEncoding
provenance, display-width helpers, `WordPressBlockWriter`, focused PHP tests,
and the WordPress charset handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, TeX/PDF engine,
MathJax, KaTeX, Word, LibreOffice, zip/unzip, browser renderer, external
charset converter, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This is limited to ISO-2022-KR stateful decoding. It avoids prior charset
coverage for Windows/ISO/Mac/Koi8/Thai/CJK/GB/HZ families, IBM code pages,
Unicode separator/control/emoji/Indic/Southeast Asian display-width slices,
legacy DOC/CFB extraction, Math/TeX handling, and package/container support.

Next non-overlapping candidates: KOI8-T/Tajik, GB12345, or another
display-width edge not already covered by current charset slices.
