# Pandoc HTML Writer Inline Attributes Current Base

Slice: `pandoc-html-writer-inline-attributes-current-base-20260609T203300Z`

## Implementation

- The current `WordPressBlockWriter` preserves `xml:lang` on inline-safe Pandoc
  Attr metadata.
- The focused Pandoc JSON handoff case covers inline `Span`, `Link`, `Code`,
  and `Image` output with safe `data-*`, class/id/title metadata plus
  `xml:lang`.
- Unsafe inline `style` and event handler attributes remain filtered.

## Evidence

- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  failed with `1 test files, 194 assertions, 1 failures` because inline
  `xml:lang` was dropped.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed with `1 test files, 288 assertions, 0 failures`.
- Syntax:
  `php -l lanes/pandoc/src/WordPressBlockWriter.php` and
  `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php` passed.
- JSON validation:
  `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded successfully.
- Whitespace:
  `git diff --check -- lanes/pandoc` passed.
- Full gate:
  `php tools/run-tests.php lanes/pandoc/tests` passed with
  `42 test files, 58757 assertions, 0 failures`.

## Mapping Delta

- `phpPass`: `2929` -> `2930`.
- `benchmarkDenominator.mapped`: `3108` -> `3109`.
- `mappedHtmlWriterInlineAttributeCases`: `1`.
- `htmlWriterInlineAttributeAssertions`: `13`.

## Scope

This is a bounded native PHP WordPress HTML writer slice. It does not invoke
Pandoc, Cabal/Haskell runners, browser renderers, online sanitizers, external
validators, office suites, TeX/PDF engines, zip/unzip, Jupyter, or Node tooling.

Direct-format parity accounting is unchanged; this slice extends the existing
HTML writer handoff path rather than adding a new direct format.
