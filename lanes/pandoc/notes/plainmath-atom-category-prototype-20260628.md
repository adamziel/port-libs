# PlainMath Atom Category Prototype - 2026-06-28

Slice: `plib-wj70q.12`, PlainMath typed atom category prototype.

`MathTexConverter` now exposes `texAtomCategorySummary()` as a narrow runtime
prototype for TexMath-like atom categories. The summary is derived from the
converter's generated MathML, so it reuses the current bounded parser and keeps
HtmlWriter/EPUB-facing MathML output unchanged.

The prototype reports `Ord`, `Op`, `Bin`, `Rel`, `Open`, `Close`, `Pun`, and
`Inner` atoms. Explicit `\mathop`, `\mathrel`, `\mathbin`, `\mathopen`,
`\mathclose`, `\mathpunct`, `\mathinner`, and `\mathord` wrappers are read from
the existing `data-tex-math-class` metadata; plain MathML tokens are classified
with a small built-in fallback table for common operator, relation, fence, and
punctuation tokens.

Validation:

- `php -l lanes/pandoc/src/MathTexConverter.php`
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
- Isolated focused case: `summarizes bounded tex atom categories for plainmath
  prototype handoff` passed with 21 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` remains
  baseline-red on the current branch with 6 unrelated failures in existing
  raw-TeX declaration/LatexWriter cases; the new atom-category case passes.

No Pandoc, TeX engine, browser, office suite, or external validator was invoked.

Focused behavior metric: `phpPass` +1 (`457 -> 458`), `phpFail` remains 0.
