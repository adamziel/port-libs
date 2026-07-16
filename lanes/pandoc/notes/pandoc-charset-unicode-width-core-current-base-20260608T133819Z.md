# Pandoc Charset/Unicode Width Core - BOM Declaration Preflight

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T133819Z`
Base: `c09710161ff2cdca8a8469de31dd5d314260fa0c`

## Behavior

- `UnicodeText::declaredCharset()` now checks the input byte-order mark before
  `Content-Type`, XML declaration, or HTML meta charset labels.
- BOM preflight is shared with `UnicodeText::decodeBytes()` through one bounded
  helper, so declaration and decode paths keep the same UTF-32, UTF-16, and
  UTF-8 BOM ordering.
- The WordPress charset Unicode handoff example now exposes a `Declared BOM`
  audit row for UTF-8 and UTF-16 BOM source decisions before stale labels are
  trusted.

## Source Truth

This slice ports the format contract that source bytes with an explicit Unicode
BOM identify their encoding before conflicting transport or in-document charset
labels. It stays inside the existing native PHP charset/Unicode support row and
does not invoke Pandoc, Cabal, Haskell runners, external charset converters,
browser renderers, online services, live provider tests, or live-service
provider tests.

## Evidence

- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 850 assertions, 0 failures`.
- Red-first after adding the new case:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  failed with `1 test files, 851 assertions, 1 failures` because
  `declaredCharset()` returned `content-type` before `byte-order-mark`.
- Final focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 864 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed.

## Dependency Closure

No new native PHP support component is needed. This reuses `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, `UnicodeTextTest.php`, and the
WordPress charset Unicode handoff example.

## Non-Overlap

This does not repeat earlier BOM decode coverage, UTF-32 decoding, legacy
code-page decoder coverage, or Unicode display-width cluster slices. The owned
surface is the charset declaration/preflight source decision for BOM-bearing
bytes.

## Next

Choose a non-overlapping charset/Unicode gap such as BOM-aware HTML parser
handoff diagnostics, additional charset-family coverage, or Unicode
line-breaking metadata.
