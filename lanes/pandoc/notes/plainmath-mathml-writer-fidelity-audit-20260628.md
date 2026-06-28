# PlainMath MathML Writer Fidelity Audit - 2026-06-28

Bead: `plib-wj70q.17`

Scope: audit the native PlainMath `MathTexConverter` MathML writer against
TexMath's MathML writer for the post-parser surfaces called out in the lane:
operator `form` attributes, per-cell alignment, `style` / `text-align`, and
styled Unicode conversion. This is an audit note only; no runtime behavior or
known-gap metadata was changed.

## Source Truth

- Upstream source inspected:
  `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Writers/MathML.hs`
  - Lines 73-80: stretchy operators receive `form="prefix|infix|postfix"`.
  - Lines 97-106: styled text uses `mathvariant` and `toUnicode`.
  - Lines 114-134: every array cell gets `mtd columnalign` and a
    `style="text-align: ..."` attribute, with extra padding hints for
    right-left sequences.
  - Lines 156-157 and 216-231: fences and typed symbols preserve MathML
    prefix/postfix/infix form semantics.
  - Lines 188-211 and 256-259: styled identifiers/numbers/text are converted
    through the writer's text style context before emission.
- Local implementation reviewed:
  `lanes/pandoc/src/MathTexConverter.php`
  - `parseCommand()` routes bounded TeX commands into direct MathML strings.
  - `environmentTable()` emits table rows and ordinary `<mtd>` cells.
  - `arrayColumnAttributes()` emits table-wide `columnalign`, `columnwidth`,
    `columnlines`, and bounded `data-tex-*` metadata.
  - `arrayMulticolumnCellAttributes()` emits per-cell metadata only for
    `\multicolumn`.
  - `parseMathVariantCommand()` and `rewriteMathVariantIdentifiers()` rewrite
    `<mi>` and `<mn>` children under math alphabet wrappers.
  - `parseTextModeCommand()` / `textModeTextNode()` emit styled `<mtext>` via
    `mstyle mathvariant`, but do not rewrite the text payload to Unicode
    mathematical alphanumerics.

## Coverage Matrix

| Surface | Current local coverage | TexMath writer delta | Classification |
| --- | --- | --- | --- |
| Display/source wrapper | Covered. `texToMathMl()` emits `<math display=...>` plus source TeX semantics annotations, and accessibility annotations are available through the existing path. | No material gap for this lane. | Accepted coverage. |
| Operator `form` attributes | Partially covered as review metadata. Explicit class wrappers such as `\mathopen`, `\mathclose`, `\mathrel`, and `\mathbin` emit `data-tex-math-class`, and `texAtomCategorySummary()` can infer review categories without changing MathML. Emitted `<mo>` tokens generally lack `form`. | TexMath adds `form` on fences, stretchy delimiters, and typed symbols. | Writer-fidelity gap, not a parser blocker for current PlainMath handoff. It becomes a blocker only for strict MathML parity consumers that inspect `form`. |
| Table/array alignment | Covered at table level. Arrays, matrices, cases, AMS rows, alignedat, flalign, eqnarray, subarray, row spacing, row labels/tags, column widths, column lines, and column hooks are represented with `mtable` attributes or bounded `data-tex-*` metadata. `\multicolumn` can emit per-cell `columnspan`, `columnalign`, width, valign, and hook metadata. | TexMath emits `columnalign` and `style="text-align: ..."` on every `mtd`, not only on `mtable` or multicolumn cells. | Mostly accepted layout difference for local WordPress review. Strict per-cell MathML parity remains a writer-fidelity gap. |
| Per-cell alignment overrides | `\multicolumn{...}{...}{...}` is covered for bounded column specs. Ordinary cells inherit table-level column alignment. | TexMath writer assigns each cell independently from the alignment vector. | Gap is localized to ordinary-cell emission, not row parsing. No parser blocker unless a fixture requires cell-local attributes independent of table columns. |
| `style` / `text-align` | Not emitted in MathML table cells. Local table/HTML writers elsewhere in the lane do preserve `text-align`, but `MathTexConverter` MathML tables do not. Array preamble hooks are preserved as bounded metadata, not interpreted into CSS. | TexMath emits CSS `text-align` per cell and right-left sequence padding hints. | Writer-fidelity gap. Treat CSS omission as an accepted layout difference unless strict browser-rendered MathML parity is requested. |
| Styled text commands | Covered structurally. `\textbf`, `\textit`, `\textsf`, `\texttt`, nested text-mode groups, escaped text tokens, and inner math are parsed into valid MathML with `mstyle mathvariant` and `mtext`. | TexMath converts styled text payloads through `toUnicode`, so browser rendering does not depend only on `mathvariant`. | Writer-fidelity gap for styled `mtext` Unicode conversion, not a parser blocker. |
| Math alphabet wrappers | Good coverage for bounded wrappers. `\mathbf`, `\mathbb`, `\mathcal`, `\mathfrak`, `\mathsf`, `\mathtt`, bold/italic/sans-serif variants, digits, ASCII runs, Greek variants, and common exceptions are tested. | TexMath applies style context in `vnode`, with some uppercase Greek and bold identifier special cases. | Mostly covered, but strict TexMath comparison fixtures should guard special-case differences before changing behavior. |
| Styled Unicode accessibility | Covered in reverse for accessibility: local helper code can map mathematical alphanumeric codepoints back to base characters for alt text / intent. | TexMath forward-converts styled output with `toUnicode`. | Accessibility is covered; forward styled text Unicode output remains a writer-fidelity gap. |

## Prioritized Fixtures And Gaps

P1: operator `form` fixture.

- Fixture: `\left( x \middle| y \right) + \mathopen{[}q\mathclose{]}`.
- Expected strict target: prefix/postfix/infix `form` attributes on delimiter
  operators, alongside existing source annotations.
- Current local status: parser accepts the bounded constructs, but emitted
  operators lack `form`.
- Priority reason: this is the clearest strict TexMath MathML writer delta and
  it aligns with the atom-category prototype without requiring external engines.

P1: ordinary cell alignment/style fixture.

- Fixture: `\begin{array}{lcr}a&b&c\\d&e&f\end{array}`.
- Expected strict target: each emitted `mtd` carries its own `columnalign` and
  `style="text-align: ..."` attribute.
- Current local status: emitted `mtable` carries `columnalign="left center right"`;
  ordinary `mtd` cells do not carry per-cell attributes. This is acceptable for
  current review handoff but not strict writer parity.
- Priority reason: low parser risk if implemented as additive emission, but it
  is a visible MathML writer fidelity delta.

P1: styled `mtext` Unicode fixture.

- Fixture: `\textbf{abc 123} + \textsf{review} + \textit{delta}`.
- Expected strict target: styled text content is converted through the same
  Unicode mathematical alphabet path used by TexMath `makeText`.
- Current local status: local output uses `mstyle mathvariant` around unchanged
  `mtext` payloads.
- Priority reason: parser coverage is already good; the remaining gap is
  isolated to writer text payload conversion.

P2: math alphabet special-case comparison fixture.

- Fixture: `\mathbf{ABCxyz123} + \boldsymbol{\Gamma\alpha} + \mathbb{R2}`.
- Expected strict target: verify exact TexMath `TextBold`, uppercase Greek, and
  digit behavior before changing local output.
- Current local status: bounded wrappers rewrite many `mi` and `mn` tokens to
  mathematical alphanumeric Unicode and pass current tests.
- Priority reason: likely close, but should be compared before claiming full
  styled Unicode parity.

P2: right-left sequence text-align padding fixture.

- Fixture: an array/AMS row sequence that alternates right-left alignments:
  `\begin{aligned}a&=b\\c&=d\end{aligned}` and `\begin{array}{rl}a&b\end{array}`.
- Expected strict target: if local parity follows TexMath closely, per-cell
  styles include the padding adjustments TexMath applies for right-left
  alignment sequences.
- Current local status: no per-cell CSS style attributes are emitted.
- Priority reason: renderer-specific layout detail; useful only after P1
  per-cell style emission is accepted.

P3: array hook-to-style interpretation fixture.

- Fixture candidate: column preamble hooks that express visual alignment or
  spacing intent, such as `>{\text{src}}l` and already accepted spacing hooks.
- Current local status: bounded hooks are preserved as `data-tex-column-hooks`;
  unsupported hook commands fail closed.
- Priority reason: this is parser/preamble semantics, not core MathML writer
  output. Keep it separate from the strict writer-fidelity fixtures above.

## Parser Blockers Vs Accepted Layout Differences

Parser parity blockers:

- Any unsupported TeX syntax that fails before MathML exists remains a parser
  blocker. This audit did not find a new blocker in the requested surfaces.
- Future hook macros outside the current whitelist should be filed as parser
  work, not as MathML writer output work.

Accepted layout differences:

- Table-level `mtable columnalign` without per-cell `mtd columnalign/style` is
  acceptable for current bounded WordPress review because the formula source is
  preserved and the table shape is reviewable.
- Missing per-cell CSS `text-align` is a strict MathML rendering parity delta,
  not a current parser parity failure.
- `mstyle mathvariant` around text-mode content is structurally valid for the
  current handoff. Forward Unicode rewriting for styled `mtext` should be a
  focused writer slice, not a parser rewrite.

Known-gap metadata update: not needed. The repo did not expose a dedicated
PlainMath `knownGaps` registry for these writer-fidelity items, and the existing
lane status already records the baseline-red MathTexConverter failures as
unrelated raw-TeX declaration / LatexWriter issues.

## Verification

Commands run:

```text
curl -sL --fail https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Writers/MathML.hs | nl -ba | sed -n '58,270p'
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
rg -n "converts bounded (tex )?(array|ams|matrix|smallmatrix|subarray|eqnarray|math variant|texmath.*variant|plain tex alignment|plain tex matrix|text-mode|tex text|styled|style|row|tag|align|math class)|mathvariant|columnalign|columnspan|columnwidth|columnlines|data-tex-column|mlabeledtr|data-tex-math-class|data-tex-rowspacing|text-align|unicode|form=|data-tex" lanes/pandoc/tests/MathTexConverterTest.php -S
```

Result:

- Upstream MathML writer source confirmed the four audited deltas:
  `form`, per-cell `mtd` alignment, per-cell `style="text-align: ..."`, and
  `toUnicode` conversion for styled text/identifiers.
- Current `MathTexConverterTest.php` reported:
  `1 test files, 1385 assertions, 6 failures`.
- The six failures match the already recorded baseline cluster: five raw-TeX
  declaration capture failures and one LatexWriter source-content failure.
  They are not caused by the audited writer-fidelity surfaces.
- Relevant table/style/variant tests passed in that same run, including:
  text-mode aliases/token/nesting, math class wrappers, AMS/array alignment,
  row tags/labels, row spacing, array width/repeated/hook/multicolumn/rule
  metadata, color/background wrappers, math alphabet variants, and Unicode
  alphanumeric rewrite coverage for math alphabet wrappers.

No Pandoc executable, TexMath executable, MathJax, KaTeX, TeX engine, browser
renderer, Haskell/Cabal runner, or external converter was run.
