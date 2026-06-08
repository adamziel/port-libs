# Pandoc Charset/Unicode Width Core - X-user-defined Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T105815Z`
Base accepted HEAD: `f7346403b056bde300569d4be1d141cef265e110`

## Behavior

- Added bounded native `x-user-defined` source-byte decoding in
  `UnicodeText::decodeBytes()`.
- Normalizes `x-user-defined` and `user-defined` labels to canonical
  `x-user-defined`.
- Decodes ASCII bytes unchanged and high bytes `0x80..0xFF` into private-use
  code points `U+F780..U+F7FF`.
- Keeps declared HTML charset detection from treating `x-user-defined` as an
  unknown UTF-8 fallback.
- Extends the WordPress charset handoff example with an `X-user-defined source`
  row: `Legacy U+F780 U+F781 U+F7FE U+F7FF source.` with narrow/wide widths
  `19/23`.

## Source Truth

The byte algorithm follows the WHATWG Encoding Standard, section 14.5.1
(`https://encoding.spec.whatwg.org/#x-user-defined-decoder`):
`x-user-defined` returns ASCII bytes as matching code points and returns
`0xF780 + byte - 0x80` for non-ASCII bytes.

No Pandoc runner, Haskell runner, Cabal build/test, external charset converter,
browser renderer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 816 assertions, 0 failures`
- Red-first probe before implementation:
  - `UnicodeText::decodeBytes("\\x80\\x81\\xFE\\xFF", "x-user-defined")`
  - returned `utf-8-repaired`, four replacement characters, and `4` repairs.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 830 assertions, 0 failures`

## Verification

- `php -l lanes/pandoc/src/UnicodeText.php`
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
- `php -r 'foreach (["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
- `git diff --check -- lanes/pandoc`

Final results: PHP lint passed, focused UnicodeText test passed at
`1 test files, 830 assertions, 0 failures`, charset example smoke printed
`charset unicode handoff self-test ok`, JSON validation passed, and diff
whitespace check passed.

## Dependency Closure

No new support component is needed. This slice reuses `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, the existing charset example, and
focused lane tests.

## Non-Overlap

This slice is distinct from accepted IBM437/IBM866, ISO-8859, Windows code
page, KOI8, Japanese, Chinese, Korean, emoji/display-width, Unicode separator,
format-control, Indic virama, Myanmar/Khmer conjunct, Thai/Lao AM, and
default-ignorable slices. It owns only the algorithmic `x-user-defined` legacy
source-byte decoder and WordPress charset audit handoff.
