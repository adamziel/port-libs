# Pandoc Charset/Unicode Width Core Current-Base Slice

- Slice: `pandoc-charset-unicode-width-core-current-base-20260608T125850Z`
- Accepted base: `d34cf5bfb31bb5ffe4f24d7cf74e71269251dd8f`
- Upstream contract: Unicode grapheme boundary `Prepend` controls belong with the following display cluster. This matters for Pandoc-style display slicing and writer handoff because zero-width source markers must not be stranded on the previous visible segment.

## Behavior

`UnicodeText::graphemes()` now keeps bounded Unicode Prepend format controls pending until the next non-extender cluster, covering the existing Arabic, Syriac, Kaithi, and Egyptian Hieroglyph format-control ranges already treated as zero width by the charset helper. A trailing Prepend control with no following visible character remains attached to the final cluster so display splitting does not leave a zero-width tail.

The focused red-first probe showed the previous behavior attached U+0600 to the preceding `A`:

`splitAtDisplayWidth("A" . U+0600 . "ر", 1)` returned `["A" . U+0600, "ر"]`.

The final behavior returns `["A", U+0600 . "ر"]`.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 842 assertions, 0 failures`.
- Focused final: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 850 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` passed with `charset unicode handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/UnicodeText.php`, `lanes/pandoc/tests/UnicodeTextTest.php`, and `lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Lane status: `phpPass` increments `1646 -> 1647`; mapped denominator increments `2066 -> 2067`; charset/Unicode mapped cases increment `9 -> 10`; focused charset assertions increment `65 -> 73`.

## Non-Overlap

This slice does not repeat the prior charset format-control zero-width accounting, I Ching/counting-symbol width, default-ignorable width, tab-stop, Hangul/Indic/Myanmar/Khmer, emoji, or CJK byte-decoding slices. It only changes cluster ownership for Unicode Prepend controls during grapheme/display splitting.

## Dependency Closure

No new native PHP support component is needed. The patch reuses `UnicodeText`, `UnicodeTextTest.php`, `MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, and the existing charset Unicode WordPress handoff example. Full upstream Pandoc runner parity remains separate and was not attempted.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.
