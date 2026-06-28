# PlainMath Atom Coercion Direct Tokens - 2026-06-28

Slice: `plib-wj70q.13`, PlainMath atom coercion commands lane.

The current `MathTexConverter` already supports grouped texmath atom coercion
commands through `data-tex-math-class` metadata and
`texAtomCategorySummary()`. This slice adds a focused conformance fixture for
the separate unbraced-token parser path:

```tex
\mathop\sum_i + x \mathrel= y \mathbin+ z \mathord+ \mathopen( q \mathclose) \mathpunct, r
```

The fixture verifies:

- `\mathop`, `\mathrel`, `\mathbin`, `\mathord`, `\mathopen`,
  `\mathclose`, and `\mathpunct` accept a single following atom without braces.
- Generated MathML remains well formed.
- Explicit atom categories are visible in `texAtomCategorySummary()` as
  `Op`, `Rel`, `Bin`, `Ord`, `Open`, `Close`, and `Pun`.
- No raw TeX command identifiers leak as `<mi>\...</mi>` MathML tokens.

No runtime behavior was changed; this locks existing parser semantics into the
PlainMath static texmath fixture corpus and complements the grouped coercion
coverage already present on current `main`.

Verification:

```text
php -l lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php
php tools/run-tests.php lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php
```

Result: `1 test files, 81 assertions, 0 failures`.

No Pandoc executable, TexMath executable, TeX engine, MathJax, KaTeX, browser
renderer, Haskell/Cabal runner, or external converter was run.
