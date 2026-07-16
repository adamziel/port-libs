# Pandoc Charset/Unicode Width Core - Tibetan Tsheg Wrapping

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T113646Z`
Base accepted HEAD: `07691b7f82738219a04899114d848d918330fb2f`

## Behavior

- Added bounded visible break-after handling for Tibetan U+0F0B tsheg in
  `UnicodeText::wrapByDisplayWidth()`.
- Preserves the visible tsheg on the preceding syllable group when a wrapped
  line breaks, instead of moving `་` to the start of the next continuation line.
- Keeps existing display-width accounting intact: Tibetan combining vowel signs
  remain zero-width through the Unicode mark path, and the visible tsheg itself
  remains a one-column character.
- Extended the WordPress charset handoff smoke with a `Tibetan tsheg` audit row
  proving wrapped review text is exposed as `བོད་ཡིག་ /   དཔེ་མཛོད /   tail`.

## Source Truth

This ports the bounded writer-format contract needed by Pandoc-style
display-width wrapping: Tibetan tsheg is a visible word/syllable separator and
line-break opportunity, so native wrapping must preserve the marker in output
while allowing a break immediately after it.

No Pandoc runner, Haskell runner, Cabal build/test command, external charset
converter, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Red-First Evidence

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 830 assertions, 0 failures`
- Red-first run after adding the focused test, before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 833 assertions, 1 failures`
  - Failure: expected `བོད་ཡིག་ /   དཔེ་མཛོད /   tail`, actual wrapping moved the
    tsheg to the next line as `  ་མཛོད`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 834 assertions, 0 failures`

## Verification

- `php -l lanes/pandoc/src/UnicodeText.php`
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
- `php -r 'foreach (["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
- `git diff --check -- lanes/pandoc`

Final results: PHP lint passed, focused UnicodeText test passed at
`1 test files, 834 assertions, 0 failures`, charset example smoke printed
`charset unicode handoff self-test ok`, JSON validation passed, and diff
whitespace check passed.

## Dependency Closure

No new support component is needed. This slice reuses `UnicodeText`,
`MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the existing
charset handoff example, and focused lane tests.

## Non-Overlap

This slice is distinct from accepted byte-decoder slices, Unicode normalization,
emoji/tag/variation width, Hangul Jamo width, Indic spacing marks, Indic
virama clusters, Myanmar/Khmer conjuncts, Thai/Lao AM clusters, default
ignorable controls, Unicode separator classes, soft-break controls, prepended
format controls, East Asian ambiguous-width policy, and `x-user-defined`
source-byte decoding. It owns only visible break-after wrapping for Tibetan
U+0F0B tsheg.

## Follow-Up

Additional script-specific visible line-break separators, if needed, should be
added in separate bounded charset/Unicode-width slices with red-first tests and
without running external converters or upstream Pandoc tooling.
