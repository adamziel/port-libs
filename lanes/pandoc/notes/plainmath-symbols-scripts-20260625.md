# PlainMath Symbols, Operators, And Scripts Lane - 2026-06-25

Hook bead: `plib-wj70q.5`.

Scope: `HtmlWriter` native TeX-to-MathML generation for symbol/operator/script
coverage, plus EPUB MathML tests.

## Implemented Coverage

| Area | PHP behavior added or verified | Evidence |
| --- | --- | --- |
| Direct Unicode identifiers | `HtmlWriter` now reads UTF-8 letter runs with Unicode-aware tokenization instead of byte-wise fallback. Direct Greek identifiers such as `α`, `β`, `γ`, `θ`, and `Δ` serialize as `mi`. | `writes epub3 tex unicode identifiers and prime shorthand as mathml` in `lanes/pandoc/tests/EpubWriterTest.php`. |
| Direct Unicode operators | Non-ASCII operator and relation glyphs now advance by full UTF-8 character and serialize as a single `mo`, avoiding replacement-character output. | The same test covers `∂`, `/`, and `≤`, and asserts the generated XHTML has no `�`. |
| Prime shorthand scripts | TeX prime suffixes (`'`, `''`, `'''`, `''''`) are consumed as superscript prime operators. They compose with subscripts through existing `msubsup` handling. | The same test covers `β'`, `γ''`, and `F'_n(x)`. |
| Existing command aliases | Existing tests still cover named operators, relation/operator aliases, accents, under/over scripts, large-operator limits, substack/subarray scripts, and MathML annotations. | Focused `EpubWriterTest.php` passed: 10514 assertions, 0 failures. |

## Unsupported Or Deferred Matrix

| TexMath behavior | Current PHP status | Decision |
| --- | --- | --- |
| `Bin` to `Ord` context correction | PHP emits direct MathML strings and does not retain TexMath symbol category state after parsing. Generated MathML still uses `mo`, so the distinction is not represented as an explicit node attribute. | Deferred until the parser has typed atom/category objects or a post-parse operator dictionary pass. |
| Atom coercion commands (`\mathop`, `\mathrel`, `\mathbin`, `\mathord`, `\mathopen`, `\mathclose`, `\mathpunct`) | No category-preserving model exists; adding command aliases alone would hide the missing semantics. | Accepted gap for this lane. Needs typed atom categories before exact parity claims. |
| Invisible function application after `EMathOperator` | Named operators serialize as normal-variant `mi`, but PHP does not insert the MathML invisible apply-function operator. | Deferred to a writer normalization pass so existing named-operator tests can be updated coherently. |
| `\operatorname*` implicit limits | `\operatorname` is supported, but starred operator names do not yet carry an internal "limits" flag through script parsing. Explicit `\limits` remains covered. | Deferred until command parsing can return atom metadata instead of only XML strings. |
| Full upstream Unicode fixture parity | Direct UTF-8 identifiers/operators now serialize safely, but the full TexMath `unicode.test` also exercises broader package/macros/category behavior. | Partial pass. Keep full upstream `unicode.test` as accepted-gap until the conformance harness owns static upstream fixtures. |

## Verification

- `php -l lanes/pandoc/src/HtmlWriter.php`
- `php -l lanes/pandoc/tests/EpubWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests` (12 files, 22403 assertions, 0 failures)
