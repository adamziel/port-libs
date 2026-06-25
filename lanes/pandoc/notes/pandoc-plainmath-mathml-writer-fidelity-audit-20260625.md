# PlainMath MathML Writer Fidelity Audit

Date: 2026-06-25 UTC
Issue: `plib-wj70q.17`
Target branch: `plainmath-parity-20260625`

## Scope

This is a report-only audit of MathML writer fidelity gaps that still matter
after the current PlainMath parser work on the target branch. The target branch
does not contain a separate `MathTexConverter.php` or `PlainWriter.php`; the
native TeX-to-MathML path for this slice is `lanes/pandoc/src/HtmlWriter.php`.

Grounded local artifacts:

- `lanes/pandoc/src/HtmlWriter.php`
- `lanes/pandoc/tests/PlainMathConformanceTest.php`
- `lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
- `lanes/pandoc/notes/plainmath-supervisor-20260625.md`
- `lanes/pandoc/notes/plainmath-conformance-20260625.md`
- `lanes/pandoc/notes/plainmath-environments-arrays-20260625.md`
- `lanes/pandoc/notes/plainmath-structure-fractions-fences-20260625.md`
- `lanes/pandoc/notes/plainmath-style-text-enclosure-20260625.md`
- `lanes/pandoc/notes/plainmath-evaluator-20260625.md`

No runtime code was changed. No corpus `knownGaps` entry was changed because the
existing corpus already records the category/model blockers as
`unicode-symbol-category-parity` and `atom-coercion-bin-context-correction`,
while the remaining writer-only fidelity items are documented in this note and
the target branch's parity notes.

## Current Branch Shape

`HtmlWriter` owns both parsing and MathML emission:

- `renderMath()` dispatches `writerHTMLMathMethod=mathml` to `renderMathML()`.
- `renderMathML()` preserves trusted existing `<math>` payloads or calls
  `texMathToMathML()`.
- `texMathToMathML()` preprocesses TeX, parses it through `parseTexMathRow()`,
  and wraps generated fragments in `<math><semantics>...<annotation>`.
- `parseTexMathCommand()` handles fences, arrays, environments, atom coercion
  commands, style commands, color/background, text, macros, fractions, roots,
  and symbol commands.
- `texMathMatrixToMathML()` emits table rows/cells and currently places
  resolved alignment on `mtable columnalign`.
- `texMathFencedRow()` emits stretchy `mo` fences, without `form` attributes.
- Style commands emit `mstyle mathvariant=...`; they do not convert styled
  characters to Mathematical Alphanumeric Symbols.

The PlainMath corpus currently has 32 passing `mathml` fixtures, 6 fallback
fixtures, and 4 `knownGaps` entries. Relevant fixture IDs include
`pmatrix-two-by-two`, `align-environment`, `alignat-environment`,
`flalign-star-environment`, `gather-environment`, `multline-environment`,
`eqnarray-environment`, `left-angle-middle-right-angle`,
`left-null-right-bar-scripts`, `mathopen-mathclose-mathpunct-coercion`,
`mathbin-mathord-coercion`, `mathop-styled-operator-name`,
`unicode-symbol-category-parity`, and `atom-coercion-bin-context-correction`.

## Upstream Writer Facts

TexMath comparison used the inspected upstream snapshot
`170899673ee31de9096e178605e8da31a36e4185` from
`/tmp/port-libs-texmath-audit`. The target branch notes reference the same
`17089967` family for fixture provenance.

Writer facts that matter for this audit:

- `MathML.hs` assigns `form="prefix"`, `form="postfix"`, or `form="infix"` to
  fence/operator nodes when TexMath has symbol role context.
- `makeArray` writes alignment on each `mtd`, using both
  `columnalign="left|center|right"` and `style="text-align: ..."`.
- Right/left array sequences can also receive padding tweaks in the same
  per-cell `style` attribute.
- Styled text/identifiers are passed through `toUnicode` while keeping
  `mathvariant`, because browser support for `mathvariant` alone is incomplete.
- `mstyle` remains in upstream output for compatibility, even though modern
  browser MathML narrows its styling role.

## Local Probe Summary

Probes were run through
`HtmlWriter(['writerHTMLMathMethod' => 'mathml'])` on this branch.

| Area | Probe/corpus evidence | Current branch output | Fidelity result |
| --- | --- | --- | --- |
| Explicit fence `form` | `\left< a \middle| b \right>` and corpus `left-angle-middle-right-angle` | Emits stretchy `mo` fences for left, middle, and right delimiters. No `form` attributes. | Gap. Explicit left/right/middle context exists in the current parser path, so this is mostly writer emission work for those commands. |
| Atom-coercion `form` | Corpus `mathopen-mathclose-mathpunct-coercion` | Emits `form="prefix"` for `\mathopen{[}` and `form="postfix"` for `\mathclose{]}`. | Covered for explicit atom-coercion commands, but not a general delimiter-category model. |
| Bare delimiter `form` | Probe `f(x)` | Emits `<mo>(</mo>` and `<mo>)</mo>` as ordinary character operators. | Parser/model blocker. The direct string-fragment parser does not retain Open/Close/Pun atom category state for ordinary delimiters. |
| Per-cell table alignment | Probe `\begin{array}{lcr}a&b&c\\x&y&z\end{array}` plus corpus environment cases | Emits `<mtable columnalign="left center right">` with plain `<mtd>` cells. | Gap. Alignment is already computed, so per-cell `mtd columnalign` is a narrow writer-fidelity patch. |
| CSS `text-align` on cells | Same array/environment probes | No generated MathML cell has `style="text-align: ..."`. | Gap. Existing notes explicitly call this out as compact table-level output instead of TexMath per-cell writer output. |
| Row spacing/rules/layout hooks | Environment notes for arrays/matrices/AMS structures | Ignores row spacing, TeX rules, and full layout package behavior. | Accepted layout difference unless a renderer-specific fixture proves impact. These are not parser parity blockers. |
| `mathcolor`/`mathbackground` | Probe `\textcolor{red}{x_i}` and style/text note | Emits `mstyle mathcolor="red"`; colorbox paths emit `mathbackground`. | Covered for bounded commands. This is not a generic CSS style parser. |
| `mstyle mathvariant` | Probe `\textbf{draft} + \textsf{review}` and style/text note | Emits `mstyle mathvariant="bold"` / `sans-serif` around parsed content or text. | Covered as bounded local style output. |
| Styled Unicode conversion | Probe `\mathbb{R09} + \mathcal{F} + \mathfrak{g} + \mathbf{\Gamma\alpha}` | Keeps plain child characters under `mstyle mathvariant`; it does not emit styled Unicode codepoints. | Gap. Existing style note already says this branch does not claim full TexMath styled Unicode conversion parity. |

## Coverage Matrix

| Fidelity target | Branch evidence | Classification | Priority | Recommended fixture/gap action |
| --- | --- | --- | --- | --- |
| `form` on explicit `\left...\middle...\right` delimiters | `parseTexMathCommand()` recognizes `left` and `middle`; `parseTexMathRowUntilRight()` finds the paired `right`; `texMathFencedRow()` emits left/right fence `mo`. | Writer emission gap for explicit fences. | P1 | Extend `left-angle-middle-right-angle` or add a dedicated fixture expecting left `form="prefix"`, middle `form="infix"`, and right `form="postfix"`. |
| `form` from explicit atom coercion commands | `texMathCoercedAtomElement()` and `texMathSymbolElementForAtomType()` emit `form` for `\mathopen` and `\mathclose`; corpus fixture `mathopen-mathclose-mathpunct-coercion` covers it. | Covered for bounded coercion commands. | Done/P2 for broader category semantics | Keep the current fixture. Do not treat it as full `fixBinList` or ordinary delimiter-category parity. |
| `form` on delimiter-producing infix fractions | `texMathInfixFractionElement()` and `texMathFencedRow()` wrap `choose`, `brack`, `brace`, and `bangle` output. | Writer emission gap for generated fences. | P1 | Extend `infix-choose` / `infix-brack-brace-bangle` expectations after explicit-fence form support lands. |
| `form` on ordinary bare delimiters | `parseTexMathAtom()` turns unclassified characters into `<mo>...</mo>`. Corpus `knownGaps.unicode-symbol-category-parity` and `knownGaps.atom-coercion-bin-context-correction` name the remaining category/model limits. | Parser/model blocker. | P2 | Do not fixture as writer-only. Tie this to typed atom/category work from the supervisor/evaluator notes. |
| Per-cell `mtd columnalign` | `texMathArrayColumnAlign()` and `texMathColumnAlignForTable()` already produce resolved alignment strings; `texMathMatrixToMathML()` emits plain `mtd`. | Low-risk writer emission gap. | P1 | Add array fixture for `lcr` expecting each `mtd` to carry resolved `columnalign`; then apply the same path to AMS/matrix table emitters. |
| Per-cell `style="text-align: ..."` | Existing environment note states the branch uses compact table-level `columnalign` instead of TexMath per-cell attributes. | Low-risk writer/layout compatibility gap. | P1 | Pair with the per-cell `columnalign` fixture and assert `text-align: left|center|right`. |
| Right/left padding style tweaks | Current parser has no right/left sequence distinction beyond compact alignment strings. | Layout fidelity gap with partial parser/model dependency. | P2 | Add only after the first per-cell style patch; use aligned/right-left sequences if browser rendering requires it. |
| Table-level `mtable columnalign` | Corpus covers `array`, matrix, align, alignat, flalign, gather, multline, and eqnarray families. | Covered for bounded branch behavior. | Done | Keep current fixtures; update expected MathML only when intentionally tightening writer fidelity. |
| Row spacing, TeX rules, arraystretch, full package layout | Environment note lists these as deliberate gaps. | Accepted layout differences. | Accepted | No parser or writer fixture unless a product requirement changes the scope. |
| `mathcolor` and `mathbackground` | Style/text note and probes show `mstyle mathcolor` / `mathbackground` for bounded color commands. | Covered. | Done | Existing EPUB/style coverage is enough for this audit. |
| Generic CSS/style parsing | No source of arbitrary CSS style declarations exists in the PlainMath parser. | Parser/format-scope blocker. | P3 | Do not add a generic style fixture until the TeX source syntax and target semantics are scoped. |
| Styled Unicode conversion for style commands | `texMathStyleCommandVariant()` maps variants, but `parseTexMathCommand()` wraps existing child MathML in `mstyle` instead of rewriting characters. | Writer/model gap; robust support wants typed nodes or targeted XML rewriting. | P1 for fixture, P2 for broad implementation | Add a fixture for `\mathbb{R09}`, `\mathcal{F}`, `\mathfrak{g}`, and `\mathbf{\Gamma\alpha}` documenting expected TexMath-like Unicode output. Implement only with a constrained conversion table and XML-safe tests. |
| Function application after named operators | Evaluator note lists invisible apply-function insertion as P0/P1 parser-model work. | Parser/model blocker. | P2 | Keep out of this writer-only audit unless the typed expression model lands. |

## Prioritized Follow-Ups

1. Add per-cell `mtd columnalign` and `style="text-align: ..."` in
   `texMathMatrixToMathML()`.
   Alignment is already available on this branch, so this is the narrowest
   low-risk writer fidelity patch. It should be fixture-backed through the
   PlainMath conformance corpus, not only an EPUB string assertion.

2. Add `form` attributes for explicit generated fences.
   Start with `\left...\right`, `\middle`, and fences produced by
   `\choose`/`\brack`/`\brace`/`\bangle`. Preserve the existing
   atom-coercion fixture coverage, and do not claim ordinary bare delimiter
   parity until typed Open/Close/Pun category state exists.

3. Add a styled Unicode conversion fixture before implementing conversion.
   Current `mstyle mathvariant` output is valid bounded MathML, but it is not
   TexMath writer parity. A fixture should make the intended codepoints explicit
   and limit the first patch to Latin, digits, and Greek samples already named
   in the audit.

4. Keep row spacing, rules, arbitrary hooks, and generic CSS out of the
   immediate writer-fidelity slice.
   Existing notes classify those as layout or format-scope differences, not
   PlainMath parser parity blockers.

## Verification

Commands run from the port-libs worktree:

- `git status --short --branch --ahead-behind`
  - Result: branch is based on and ahead of `origin/plainmath-parity-20260625`.
- `git -C /tmp/port-libs-texmath-audit log -1 --format='%H %cs %s'`
  - Result: `170899673ee31de9096e178605e8da31a36e4185 2026-06-09 Mention StarMath in cabal description.`
- `rg` over `/tmp/port-libs-texmath-audit/src/Text/TeXMath/Writers/MathML.hs`
  and `/tmp/port-libs-texmath-audit/src/Text/TeXMath/Unicode/ToUnicode.hs`
  for `form`, `columnalign`, `text-align`, `mathvariant`, `mstyle`, and
  `toUnicode`.
- PHP probe script using `HtmlWriter(['writerHTMLMathMethod' => 'mathml'])` for:
  - `\left< a \middle| b \right>`
  - `f(x)`
  - `\begin{array}{lcr}a&b&c\\x&y&z\end{array}`
  - `\begin{align}a&=b+c\\d&=e\end{align}`
  - `\textbf{draft} + \textsf{review} + \textcolor{red}{x_i}`
  - `\mathbb{R09} + \mathcal{F} + \mathfrak{g} + \mathbf{\Gamma\alpha}`
- `php -l lanes/pandoc/src/HtmlWriter.php`
- `php -l lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
- `php -l lanes/pandoc/tests/PlainMathConformanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainMathConformanceTest.php`
  - Result: 1 test file, 240 assertions, 0 failures on the updated target
    branch.
- `git diff --check origin/plainmath-parity-20260625...HEAD`
