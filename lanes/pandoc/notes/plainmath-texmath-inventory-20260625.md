# PlainMath TexMath Inventory - 2026-06-25

Hook bead: `plib-wj70q.1`.

Scope: inventory only. No production code changes were needed. The upstream
cache was hydrated locally under ignored `.upstream-cache/` for inspection.

## Source Revisions

- TexMath: `.upstream-cache/texmath` at `170899673ee3`, version family
  `0.13.1.2`.
- Pandoc: `.upstream-cache/pandoc` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- Pandoc depends on `texmath >= 0.13.1.1 && < 0.14` in
  `.upstream-cache/pandoc/pandoc.cabal:561`.
- Local PHP target: `lanes/pandoc/src/HtmlWriter.php`.

## Upstream Path Summary

Pandoc's HTML MathML path is a direct TexMath path:

- `Text.Pandoc.Options.HTMLMathMethod` includes `MathML` at
  `.upstream-cache/pandoc/src/Text/Pandoc/Options.hs:111-117`.
- `Text.Pandoc.Writers.HTML.inlineToHtml` calls `convertMath writeMathML`
  for `MathML`, and falls back to a math span on conversion failure at
  `.upstream-cache/pandoc/src/Text/Pandoc/Writers/HTML.hs:1519-1550`.
- `annotateMML` wraps generated MathML in `semantics` and adds the original
  TeX source as `annotation encoding="application/x-tex"` at
  `.upstream-cache/pandoc/src/Text/Pandoc/Writers/HTML.hs:1390-1401`.
- `convertMath` calls `readTeX` and the selected writer, reporting a fallback
  on parse failure at `.upstream-cache/pandoc/src/Text/Pandoc/Writers/Math.hs:39-53`.

## TexMath Construct Inventory

The central TexMath tree is `Exp` in
`.upstream-cache/texmath/src/Text/TeXMath/Types.hs:52-98`. The constructors
that matter for this lane are:

| Family | Upstream constructors | MathML writer behavior |
| --- | --- | --- |
| Atoms | `ENumber`, `EIdentifier`, `EMathOperator`, `ESymbol` | `mn`, `mi`, normal-variant operator `mi`, and `mo` mapping in `MathML.hs:213-232` |
| Grouping | `EGrouped`, `EDelimited` | `mrow`, stretchy prefix/infix/postfix delimiters in `MathML.hs:214-220` |
| Spacing | `ESpace` | `mspace width=...em` in `MathML.hs:68-71` |
| Scripts | `ESub`, `ESuper`, `ESubsup` | `msub`, `msup`, `msubsup` in `MathML.hs:234-236` |
| Limits | `EOver`, `EUnder`, `EUnderover` | `mover`, `munder`, `munderover`; accents handled in `MathML.hs:237-242` |
| Enclosures | `EPhantom`, `EBoxed`, `ECancel` | `mphantom` and `menclose` in `MathML.hs:243-251` |
| Fractions and roots | `EFraction`, `ERoot`, `ESqrt` | `mfrac`, `mstyle displaystyle`, `linethickness=0`, `mroot`, `msqrt` in `MathML.hs:53-65` and `252-253` |
| Scaling | `EScaled` | stretchy min/max size in `MathML.hs:82-85` |
| Arrays | `EArray` with `Alignment` | `mtable`, `mtr`, `mtd`, column alignment in `MathML.hs:114-134` |
| Text and style | `EText`, `EStyled`, `TextType` | `mtext`, `mathvariant`, Unicode fallback conversion in `MathML.hs:97-112` and `188-259` |

The parser entry point is `readTeX` at
`.upstream-cache/texmath/src/Text/TeXMath/Readers/TeX.hs:82-88`. It first
parses and applies macro definitions, then parses `formula`, then normalizes
binary operators in context (`fixBinList`) at `TeX.hs:89-136`.

Important parser families:

- Macro expansion: `parseMacroDefinitions` and `applyMacros` handle
  `\newcommand`, `\renewcommand`, `\providecommand`, `\newenvironment`, and
  `\DeclareMathOperator(*)` in
  `.upstream-cache/texmath/src/Text/TeXMath/Readers/TeX/Macros.hs:45-83` and
  `116-230`.
- Core command dispatch: `command` checks text, style, color, roots, spacing,
  math atom-type coercion, phantom/boxed/cancel, fractions, substack,
  environments, ensuremath, scaling, negation, siunitx, arrows, and symbols at
  `.upstream-cache/texmath/src/Text/TeXMath/Readers/TeX.hs:212-237`.
- `\operatorname` is special-cased and returns `EMathOperator` plus a
  convertible-limits flag at `TeX.hs:240-255`.
- Infix fractions include `\choose`, `\brack`, `\brace`, and `\bangle`; generic
  `\genfrac` is parsed at `TeX.hs:291-324`.
- Delimiters include `\left`, `\middle`, `\right`, null delimiters, angle
  shorthand, and implicit `\lVert...\rVert` at `TeX.hs:385-433`.
- Environments include `array`, `eqnarray`, `align`, `aligned`, `alignat`,
  `alignedat`, `flalign`, `flaligned`, `cases`, matrix variants, `split`,
  `multline`, `gather`, `gathered`, and `equation` at `TeX.hs:487-526`.
- Sub/sup logic supports under/over limits for convertible operators and large
  operators at `TeX.hs:586-637`.
- Text, inner math, styles, color, roots, spacing, math atom-type coercion,
  fractions, and negation are implemented at `TeX.hs:645-832`.
- Command lookup is table-driven through
  `.upstream-cache/texmath/src/Text/TeXMath/Readers/TeX/Commands.hs`: style
  commands at `40-68`, text commands at `70-79`, enclosures at `81-113`,
  punctuation/operators at `115-136`, and symbol tables starting at `138`.

## Fixture Inventory

TexMath's test harness is Tasty/golden based:

- `test/test-texmath.hs:30-71` enumerates reader, writer, regression, and
  roundtrip groups.
- Golden files use `<<< input-format` and `>>> output-format` markers parsed by
  `readGoldenTest` at `test/test-texmath.hs:90-127`.
- Readers and writers are mapped at `test/test-texmath.hs:135-157`; the MathML
  writer maps `mml` to `writeMathML DisplayBlock`.

Local file counts at the pinned revision:

| Fixture group | Count | Notes |
| --- | ---: | --- |
| `test/reader/tex` | 57 | Direct TeX-to-native parser fixtures. Best source for parser parity. |
| `test/writer/mml` | 561 | Native/MathML writer fixtures, including large Unicode command families. |
| `test/reader/mml` | 576 | MathML reader fixtures. Useful for current HTML import work but not first TeX parser corpus. |
| `test/regression` | 25 | Named issue regressions. Good second wave after parser harness exists. |
| `test/roundtrip` | 561 | Native roundtrip fixtures. Useful after PHP has a stable normalized MathML fixture format. |

Representative first-source fixtures include:

- `test/reader/tex/01.test`: quadratic formula with `\frac`, `\pm`, `\sqrt`,
  superscript, grouping.
- `test/reader/tex/02.test`: nested `\left...\right` around fractions.
- `test/reader/tex/04.test`: subscripted `\text{...}` and grouped power.
- `test/reader/tex/05.test`: integrals, scripts, negative spaces, thin spaces.
- `test/reader/tex/06.test`: sums with under/over limits.
- `test/reader/tex/12.test`: `cases` environment with `\mbox`.
- Named fixtures: `binomial_coefficient.test`, `choose.test`, `genfrac.test`,
  `operatorname.test`, `phantom.test`, `stackrel.test`, `substack.test`,
  `subsup.test`, `text.test`, `macros.test`, `labels.test`, and
  `unicode.test`.

## Current PHP Coverage

`HtmlWriter` already has a native TeX-to-MathML path:

- `renderMath` dispatches `mathml` to `renderMathML` at
  `lanes/pandoc/src/HtmlWriter.php:2410-2425`.
- Explicit MathML payloads are preserved, and generated TeX MathML is attempted
  before falling back to plain math spans at `HtmlWriter.php:2428-2443`.
- Generated MathML includes `xmlns`, display handling, `semantics`, and the
  original TeX annotation at `HtmlWriter.php:2469-2494`.
- Row parsing, infix fractions, scripts, limits, atoms, and command dispatch are
  implemented at `HtmlWriter.php:2496-3035`.
- Fractions, `\genfrac`, style declarations, enclosures, modulo commands,
  named operators, delimiters, style variants, matrices, and matrix splitting
  live at `HtmlWriter.php:3047-3657`.
- Command names, negated relations, numbers, ASCII-letter identifiers, text
  groups, and a long command-to-symbol map begin at `HtmlWriter.php:3667-3812`.
- `mathMLRow` uses DOM parsing to avoid unnecessary `mrow` wrappers at
  `HtmlWriter.php:6393-6400`.

Existing PHP evidence:

- The upstream manifest currently records only eight mapped HTML writer math
  cases and eight WordPress math cases, with seven test-suite math constructors
  at `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:95-102`. This is not yet a
  TexMath parity denominator.
- `EpubWriterTest.php` proves explicit MathML preservation and generated TeX
  MathML for core formulas around `15243-15355`.
- Focused EPUB MathML tests now cover fraction families, `\genfrac`,
  enclosures, modulo, infix fractions, color/phantom/text commands, indexed
  roots, and many symbol/operator alias families between
  `EpubWriterTest.php:15388-26676`.
- Alternate rendition MathML coverage appears around
  `EpubWriterTest.php:33583-33775`.

## Current PHP Gaps

These are gaps against TexMath `readTeX` plus `writeMathML`, not necessarily
bugs in the current bounded EPUB behavior.

| Area | Current status | Gap classification |
| --- | --- | --- |
| Macro definitions | No equivalent to TexMath `parseMacroDefinitions`/`applyMacros`. | Parser gap: `\newcommand`, `\renewcommand`, `\providecommand`, `\DeclareMathOperator`, and custom environments should be expected-fail initially. |
| Comments and labels | No visible `%` comment skipper or `\label`/`\tag`/`\ref`-style ignore/preserve policy in `HtmlWriter` math parser. | Parser gap: use upstream `labels.test` as an expected-fail probe. |
| Token model | PHP parses ASCII digits, ASCII letters, and byte-level fallback operators. | Parser gap: direct Unicode math input and multibyte identifiers need explicit behavior before `unicode.test` can pass. |
| Symbol categories | PHP emits direct MathML strings and does not keep TexMath `TeXSymbolType`. | Writer/parsing gap: no `Bin` to `Ord` context correction, no operator dictionary pass, limited form/stretchy/spacing semantics. |
| Function application | TexMath inserts invisible function application for `EMathOperator` before arguments in `MathML.hs:171-186`. | Writer gap: current PHP named operators are normal-variant `mi`, but there is no general function-application insertion pass. |
| Environment breadth | PHP supports `array`, `subarray`, `aligned`, `gathered`, `split`, `cases`, `dcases`, `rcases`, and matrix variants. | Parser gap: `eqnarray`, `align`, `alignat`, `alignedat`, `flalign`, `flaligned`, `multline`, `gather`, and `equation` are not mapped as TexMath does. |
| Text/style breadth | PHP supports a useful subset of text/style/color commands. | Parser/writer gap: TexMath style table includes `\mathup`, `\boldsymbol`, `\bm`, `\symbf`, `\mathbold`, `\pmb`, `\mathbfup`, `\mathds`, `\mathscr`, `\mathbfit`, `\mathbfsfup`, `\mathbfsfit`, `\mathbfscr`, `\mathbffrak`, `\mathbfcal`, and `\mathsfit`; PHP currently maps a smaller set. |
| Spacing breadth | PHP maps several spacing commands through command lookup and uses fixed `mspace` output. | Parser gap: TexMath parses `\mspace{...mu}` and `\hspace{...}` with dimensions; PHP does not appear to parse those argument forms. |
| Atom type coercion | TexMath supports `\mathop`, `\mathrel`, `\mathbin`, `\mathord`, `\mathopen`, `\mathclose`, and `\mathpunct`. | Parser gap: PHP does not preserve atom-category coercion, so tests relying on category/form semantics should be deferred. |
| Root variants | PHP handles `\sqrt` with optional index. | Parser gap: TexMath treats `\surd` as a root command; PHP likely only has symbol fallback behavior. |
| SIUnitX and arrows | TexMath dispatch includes `siunitx` and `arrow` command families. | Out of first corpus. Inventory only until a fixture family owner opens this lane. |
| Error/fallback parity | PHP falls back to `<mtext>` on unconsumed trailing source and plain spans if generation returns empty. | Harness gap: normalized expected-failure cases should assert predictable fallback rather than malformed MathML. |

## First Parity Corpus Proposal

Create a PHP conformance fixture set that stores:

1. TeX input.
2. Fixture source path from TexMath.
3. Expected normalized MathML, copied from TexMath `test/writer/mml` when an
   exact pair exists or generated once by upstream for inspection.
4. Expected status: `pass`, `accepted-gap`, or `fallback`.
5. Notes linking each case to the construct family.

Do not shell out to TexMath at runtime. Upstream output should be captured as
static fixture data.

### P0 Must-Pass Corpus

These are the first fixtures to promote to PHP exact/normalized assertions:

| Fixture | Constructs | Why first |
| --- | --- | --- |
| `reader/tex/03.test` | identifiers, numbers, infix operators, superscript | Minimal parser sanity case. |
| `reader/tex/01.test` | `\frac`, `\pm`, `\sqrt`, superscript, grouped denominator | Core PlainMath formula used by many examples. |
| `reader/tex/02.test` | nested `\left...\right`, fraction | Delimiter and fraction interaction. |
| `reader/tex/04.test` | `\text{...}` under subscript, grouped power | Text-in-math and grouping. |
| `reader/tex/05.test` | integrals, scripts, thin/negative spaces | Large operator and spacing smoke test. |
| `reader/tex/06.test` | `\sum` under/over limits | Convertible operator limits. |
| `reader/tex/09.test` | `\lim` with subscript and arrow | Named operator limits. |
| `reader/tex/12.test` | `cases`, row/cell split, `\mbox` | First environment fixture. |
| `reader/tex/14.test` | `\frac`, `\tfrac`, explicit control-space | Fraction displaystyle plus spacing. |
| `reader/tex/binomial_coefficient.test` | binomial expression | Existing PHP binomial behavior should be fixture-backed. |
| `reader/tex/choose.test` | infix `\choose` | Infix fraction compatibility. |
| `reader/tex/genfrac.test` | `\genfrac` delimiters/style/thickness | Current PHP has a dedicated implementation. |
| `reader/tex/operatorname.test` | `\operatorname`, optional star | Current PHP has direct support; limits need verification. |
| `reader/tex/stackrel.test` | `\stackrel`/over construct | Current PHP has direct support. |
| `reader/tex/substack.test` | `\substack` as centered table | Current PHP maps to `mtable`. |
| `reader/tex/subsup.test` | subscript/superscript ordering | Core script ordering correctness. |
| `reader/tex/text.test` | text command parsing | Current PHP text support needs fixture-level normalization. |
| `writer/mml/01.test` through `writer/mml/09.test` | native-to-MathML smoke output | Stabilizes normalized MathML writer expectations independent of TeX parsing. |

### P0 Accepted-Gap Probes

Add these to the same corpus as expected gaps, not failures:

| Fixture | Expected initial status | Reason |
| --- | --- | --- |
| `reader/tex/macros.test` | `accepted-gap` | Requires macro definition parsing and fixed-point expansion. |
| `reader/tex/labels.test` | `accepted-gap` | Requires label/tag/comment policy. |
| `reader/tex/unicode.test` | `accepted-gap` | Requires direct multibyte token handling. |
| `reader/tex/ensuremath.test` | `accepted-gap` or `fallback` | TexMath treats `\ensuremath` structurally; PHP does not appear to. |
| A fixture with `align`/`eqnarray`/`gather` from upstream or a minimal derived case | `accepted-gap` | PHP environment support is intentionally narrower than TexMath's environment map. |

### P1 Follow-Up Corpus

After P0 is stable:

- Add `test/regression/187.test`, `191.test`, `234.test`, `273.test`, and
  `280.test` as issue-driven parser edge cases.
- Add Unicode command-block families from `test/writer/mml/*Mathematical*` only
  after the PHP harness separates "command alias maps to a glyph" from "full
  TexMath parser parity".
- Add the MathML reader fixtures only after the current task's TeX-to-MathML
  writer corpus is passing; they exercise a different import direction.

## Files And Artifacts Changed

- Added this inventory artifact:
  `lanes/pandoc/notes/plainmath-texmath-inventory-20260625.md`.
- Hydrated ignored local research cache:
  `.upstream-cache/texmath` and `.upstream-cache/pandoc`.
- No production PHP code changed.
- No test fixtures changed in this inventory lane.
