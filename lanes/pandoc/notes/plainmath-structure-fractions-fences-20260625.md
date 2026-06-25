# PlainMath Structure, Fractions, And Fences Lane

## Scope
- Bead: `plib-wj70q.8`.
- Target branch: `plainmath-parity-20260625`.
- Owned artifacts:
  - `lanes/pandoc/src/HtmlWriter.php`
  - `lanes/pandoc/tests/PlainMathConformanceTest.php`
  - `lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
  - `lanes/pandoc/notes/plainmath-structure-fractions-fences-20260625.md`

## Upstream Reference
- TexMath cache: `.upstream-cache/texmath` at `17089967`, matching the
  PlainMath supervisor brief and conformance corpus metadata.
- Relevant TexMath families:
  - `TeX.hs` root command parsing for `\sqrt` and `\surd`.
  - `TeX.hs` infix fraction family for `\over`, `\atop`, `\choose`,
    `\brack`, `\brace`, and `\bangle`.
  - `TeX.hs` delimiter parsing for `\left`, `\middle`, `\right`, null
    delimiters, and angle shorthand.
  - `TeX.hs` environment parsing for equation, align, alignat, flalign,
    gather, multline, eqnarray, matrix, cases, split, and related aliases.
  - `MathML.hs` writer output for `EFraction`, `ERoot`, `ESqrt`,
    `EDelimited`, and `EArray`.

## Existing Local Coverage Kept
- Fractions: `\frac`, `\dfrac`, `\tfrac`, `\binom`, `\dbinom`,
  `\tbinom`, and `\genfrac`.
- Infix fractions: `\over`, `\atop`, `\choose`, `\brack`, and `\brace`.
- Roots: `\sqrt{...}` and indexed `\sqrt[n]{...}`.
- Delimiters: `\left...\right`, `\middle`, null delimiter `.`, named braces,
  parens, brackets, floors, ceilings, vertical bars, and double bars.
- Environments: `array`, `subarray`, `aligned`, `gathered`, `split`,
  `cases`, `dcases`, `rcases`, and matrix variants.

## Changes Integrated
| Family | Local behavior after this lane | Fixture coverage |
| --- | --- | --- |
| Surd alias | Explicit structural `\surd{x_2}` now emits `msqrt` over the radicand. Unbraced symbol-alias use such as `\surd V` remains a radical operator for existing EPUB coverage. | `surd-root` |
| Infix angle fraction | `a \bangle b` now parses as a no-line fraction fenced by angle delimiters. | `infix-brack-brace-bangle` |
| Angle delimiters | Bare `<` and `>` in delimiter position now normalize to angle fences for `\left<...\right>` and `\genfrac{<}{>}{...}` style inputs. | `left-angle-middle-right-angle` |
| Null delimiters | Existing null-left delimiter behavior is fixture-backed for scripted right bars. | `left-null-right-bar-scripts` |
| Equation aliases | `equation` and `equation*` parse their body as ordinary math content rather than visible `begin`/`end` identifiers. | `equation-environment` |
| AMS alignment aliases | `align`, `align*`, `alignat`, `alignat*`, and `alignedat` parse as `mtable columnalign="right left"`; `flalign` and `flaligned` parse as `columnalign="left right"`; `alignat` and `alignedat` consume the leading column-count group. | `align-environment`, `alignat-environment`, `flalign-star-environment` |
| Centered structure aliases | `gather`, `gather*`, `multline`, `multline*`, and `multlined` parse as centered `mtable` output. | `gather-environment`, `multline-environment` |
| Eqnarray | `eqnarray` and `eqnarray*` parse as `mtable columnalign="right center left"`. | `eqnarray-environment` |

## Accepted Gaps
| Gap | Reason |
| --- | --- |
| Full TexMath array alignment fidelity | This lane maps common environment aliases to stable local `columnalign` output. It does not implement TexMath's complete alignat/flalign spacing and column-count semantics. |
| Whole-span parse fallback for malformed structural commands | `\frac{a}{` remains XML-parseable but degrades to visible command tokens instead of falling back the entire math span. The corpus records this as `malformed-structural-command-fallback`. |
| Macro-expanded or label/comment-sensitive structures | Macro definitions, custom environments, labels, tags, refs, and comments remain owned by the macro/lexing lane. |
| Writer style attributes beyond structural shape | TexMath emits additional writer attributes in some cases. This lane asserts stable MathML structure, fences, and parseability rather than claiming browser rendering parity. |

## Verification
- `php -l lanes/pandoc/src/HtmlWriter.php`
- `php -l lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
- `php -l lanes/pandoc/tests/PlainMathConformanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainMathConformanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/EpubWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
