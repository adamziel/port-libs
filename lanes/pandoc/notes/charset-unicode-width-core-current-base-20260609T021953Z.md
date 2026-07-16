# Charset Unicode Width Core Current Base - IBM737/CP737

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T021953Z`
Accepted base: `a3acdbf651a3d75d5d84e3bea3aaa5d49ff7e5c6`

## Behavior

- Added native bounded IBM737/CP737/DOS737 byte decoding labels to `UnicodeText`.
- Mapped the CP737 single-byte table from local source truth `/usr/share/tcl9.0/encoding/cp737.enc`.
- Added MarkdownReader sourceEncoding provenance, Unicode display-width checks, and a WordPress charset audit row for DOS Greek review packets.

## Red-First Evidence

- Baseline before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1271 assertions, 0 failures`
- Red-first after adding the focused CP737 test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1272 assertions, 1 failures`
  - Failure was the expected fallback to `utf-8-repaired` instead of `ibm737`.

## Final Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 1284 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - `charset unicode handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText` single-byte decoding, `MarkdownReader` sourceEncoding provenance, `MarkdownWriter` display-width helpers, and `WordPressBlockWriter`. No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted IBM855/CP855, IBM865/CP865, IBM869/CP869, IBM775, IBM861, IBM862, IBM863, IBM864, IBM866, Windows/ISO/Mac Greek, CJK multibyte, Unicode separator/control, emoji, Indic/Southeast Asian, or syntax-highlighting work. It is limited to IBM737/CP737 DOS Greek byte decoding and WordPress charset audit coverage under `lanes/pandoc/**`.

## Next

Pick a non-overlapping charset or width gap such as ISO-2022-KR state handling, KOI8-T/Tajik, GB12345, MacUkraine-specific overrides, or another display-width edge not already covered by current charset slices.
