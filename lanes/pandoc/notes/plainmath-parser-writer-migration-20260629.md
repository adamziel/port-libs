# PlainMath Parser/Writer Migration Note

Date: 2026-06-29 UTC
Bead: `plib-wj70q.19`

## Scope

This is a plan-only lane for extracting the current TeX math path into a
testable PlainMath parser/writer layer. No code prototype is included: the
current `MathTexConverter.php` surface is broad and active, and the safest
deliverable is an architecture note that preserves existing behavior while
identifying the first low-conflict extraction.

The current local boundary is narrower than the older "HtmlWriter parser"
description suggests. `HtmlWriter::renderMath()` selects the HTML math output
mode. `HtmlWriter::renderMathML()` either accepts a prebuilt `<math>` fragment
from the AST and adds required attributes, or delegates source TeX to
`MathTexConverter::texToMathMl()`. The real parser/writer cluster is therefore
inside `MathTexConverter`: raw TeX definition expansion, equation metadata,
cursor parsing, direct MathML fragment construction, atom-category review
metadata, accessibility metadata, and final `<math><semantics>...` wrapping.

## Module Boundaries

| Module | Responsibility | Current owner |
| --- | --- | --- |
| `HtmlWriter` | Keep HTML method selection for `mathjax`, `katex`, `webtex`, `gladtex`, `mathml`, and fallback source spans. Do not import parser internals. | `HtmlWriter::renderMath()`, `renderMathML()`, `looksLikeMathMLElement()`, `mathMLWithRequiredAttributes()`. |
| `PlainMath\MathMlFragment` | Validate/normalize prebuilt MathML fragments that enter through AST `mathml`/`html` attributes. This is the only piece worth extracting from `HtmlWriter` first. | `looksLikeMathMLElement()`, `mathMLWithRequiredAttributes()`. |
| `MathTexConverter` | Stay as the compatibility facade for existing callers: `latexFor()`, `mathMlFor()`, `accessibleMathMlFor()`, `texToMathMl()`, `texToAccessibleMathMl()`, and `texAtomCategorySummary()`. | Public `MathTexConverter` API. |
| `PlainMath\RawTexDefinitions` | Normalize macro/environment definitions, declared operators, paired delimiters, and raw-TeX declarations before parsing. | `normalizeMacroDefinitions()`, `extractRawTexEnvironmentDefinitions()`, `expandRawTexMathMacros*()`, declaration readers. |
| `PlainMath\Parser` | Parse expanded TeX into a typed PlainMath tree with cursor state and structured errors. | `parseExpression()`, `parseAtom()`, `parseCommand()`, `parseEnvironment()`, group/fence/script/spacing/comment readers. |
| `PlainMath\Node` | Value objects aligned with TexMath `Types.hs`, plus bounded local metadata needed by WordPress review. | Current ad hoc MathML strings returned by parser methods. |
| `PlainMath\MathMlWriter` | Serialize PlainMath nodes to MathML fragments and full source-preserving wrappers. | `row()`, token XML builders, `renderEquationBody()`, `environmentTable()`, array/cell attributes, styled Unicode rewrite. |
| `PlainMath\AtomClassifier` | Report TexMath-like atom categories without mutating emitted MathML. | `texAtomCategorySummary()` and `collectMathMlAtomCategories()`. |
| `PlainMath\AccessibilityWriter` | Preserve current alt text and intent output. Start by deriving from generated MathML; only move to node-native accessibility after node parity is stable. | `mathMlAccessibilityMetadata()`, `mathMlNodeAltText()`, `mathMlNodeIntent()`. |

## TexMath Grounding

TexMath HEAD checked for this note:

```text
170899673ee31de9096e178605e8da31a36e4185	HEAD
```

The PlainMath node model should mirror the practical subset of
`Text.TeXMath.Types` instead of inventing a local XML-first AST:

- `ENumber`, `EIdentifier`, `EMathOperator`, and `ESymbol TeXSymbolType Text`
  map to number, identifier, named-operator, and typed-symbol nodes.
- `TeXSymbolType` categories `Ord`, `Op`, `Bin`, `Rel`, `Open`, `Close`,
  `Pun`, `Accent`, `Fence`, `TOver`, `TUnder`, `Alpha`, `BotAccent`, and `Rad`
  should be represented directly enough for atom-category tests.
- `EGrouped` and `EDelimited open close [InEDelimited]` map to group and
  delimiter nodes with explicit middle separators.
- `ESub`, `ESuper`, `ESubsup`, `EOver`, `EUnder`, and `EUnderover` map to
  script and over/under nodes with the convertible flag retained.
- `EFraction FractionType`, `ERoot`, `ESqrt`, `EScaled`, `EPhantom`, `EBoxed`,
  and `ECancel StrokeType` map to structural nodes instead of early MathML.
- `EArray [Alignment] [ArrayLine]` maps to array/matrix nodes. Local extensions
  must retain row spacing, labels/tags, column widths, column rules, hook
  provenance, and `\multicolumn` metadata because current review output depends
  on those details.
- `EText TextType Text` and `EStyled TextType [Exp]` map to text/styled nodes.
  Current local output mostly uses `mathvariant`; strict TexMath writer parity
  still needs focused styled-text Unicode fixtures.
- `FormType` should be available to the writer so operator `form` attributes
  can be added as a writer slice without changing parser semantics.

## Migration Slices

1. Characterization guard: keep `PlainMathStaticTexmathFixtureTest.php` as the
   static conformance corpus and add three compact node-facing fixtures before
   moving production parser code: `x_1 + \frac{a}{b}`,
   `\left(a\middle|b\right)`, and `\begin{array}{lcr}a&b&c\\d&e&f\end{array}`.
2. Html boundary cleanup: extract only prebuilt MathML fragment normalization
   from `HtmlWriter` into `PlainMath\MathMlFragment`. `HtmlWriter` still chooses
   the math method and still delegates TeX parsing to the facade.
3. Node scaffold: add `PlainMath\Node` value objects or typed arrays for the
   TexMath-aligned subset above. Tests assert node arrays directly; no runtime
   output switches yet.
4. Raw definition isolation: move macro/environment normalization and expansion
   into `PlainMath\RawTexDefinitions` behind the same facade inputs. Do not
   alter source annotation output.
5. Minimal parser/writer slice: implement `PlainMath\Parser` and
   `PlainMath\MathMlWriter` only for identifiers, numbers, ordinary operators,
   braced groups, `_`, `^`, and `\frac`. Compare the new writer body to the
   current converter body for `x_1 + \frac{a}{b}`.
6. Atom classification slice: move atom category derivation to
   `PlainMath\AtomClassifier`, retaining the existing `texAtomCategorySummary()`
   API and keeping emitted MathML byte-compatible.
7. Delimiter slice: move `\left`, `\middle`, `\right`, sized delimiters, and
   fence token handling. This is where `EDelimited`, `InEDelimited`, `Fence`,
   `Open`, and `Close` become independently testable.
8. Array slice: move array/matrix/AMS rows, column specs, row spacing, labels,
   rules, hooks, and `\multicolumn`. Keep strict per-cell alignment/style as a
   writer-fidelity fixture, not a parser blocker.
9. Text/style slice: move text-mode parsing and math alphabet/style wrappers.
   Gate with existing text-mode and Unicode rewrite tests before attempting
   TexMath-style styled `mtext` Unicode conversion.
10. Facade switch: once covered fixtures are byte-compatible, switch
    `MathTexConverter::texToMathMl()` to `RawTexDefinitions -> Parser ->
    MathMlWriter -> AccessibilityWriter`, leaving unsupported constructs on the
    existing private parser until each family has coverage.

## First Safe Extraction Step

The first production-code move should be small and reversible:

- Add `PlainMath\MathMlFragment::normalize(string $mathml, bool $display):
  ?string`.
- Move `looksLikeMathMLElement()` and `mathMLWithRequiredAttributes()` behavior
  behind that helper.
- Update `HtmlWriter::renderMathML()` to call the helper for prebuilt AST
  MathML, then keep the existing `MathTexConverter` fallback unchanged.
- Add focused `HtmlWriter` coverage for accepted prebuilt MathML, rejected
  processing instructions/scripts, required namespace injection, and display
  attribute injection.

That step extracts the actual `HtmlWriter` fragment parsing without entering
the high-conflict TeX parser. The next safe parser step is slice 5, limited to
`x_1 + \frac{a}{b}` with no delimiter, environment, macro, accessibility, or
HtmlWriter behavior changes.

### First PR Contract

Keep the first extraction PR deliberately smaller than the parser split:

- Add `lanes/pandoc/src/PlainMath/MathMlFragment.php`.
- Add focused coverage in a new or existing `HtmlWriter` math test file for:
  accepted `<math>` fragments, rejected processing instructions, rejected
  `<script>` content, namespace injection, and display-mode injection.
- Change only `HtmlWriter::renderMathML()` and the new helper/test files.
  `MathTexConverter.php` should not change in this PR.
- Preserve the current fallback: if an AST-provided fragment is not accepted by
  `MathMlFragment::normalize()`, `HtmlWriter` still tries
  `MathTexConverter::texToMathMl()` and then falls back to the escaped source
  span on unsupported TeX.
- Make the helper string-based for now. A DOM-normalizing helper would be
  cleaner long-term, but this first step should be byte-compatible with the
  existing `looksLikeMathMLElement()` and `mathMLWithRequiredAttributes()`
  behavior.

The first parser PR should start only after that boundary lands. Its contract is
separate: add node snapshots and a writer parity assertion for
`x_1 + \frac{a}{b}`, leave the existing private parser as the facade fallback,
and avoid arrays, delimiters, raw definitions, equation metadata,
accessibility, and `HtmlWriter`.

## Test Strategy

- Parser tests assert node arrays or value-object snapshots, not MathML strings.
- Writer tests assert MathML fragments for hand-built nodes and compare selected
  facade output against current converter output.
- Facade tests keep using `MathTexConverterTest.php` and
  `PlainMathStaticTexmathFixtureTest.php` so public behavior remains covered.
- Atom tests keep asserting `texAtomCategorySummary()` categories and the
  absence of leaked raw TeX command identifiers.
- HtmlWriter tests should remain limited to method selection and MathML fragment
  normalization. They should not know parser node classes.
- Strict TexMath writer-fidelity fixtures should be separate from parser
  acceptance: operator `form`, per-cell `mtd columnalign/style`, styled `mtext`
  Unicode conversion, and right-left alignment padding are writer gaps, not
  reasons to block the parser split.
- Existing source annotations must remain stable:
  `<annotation encoding="application/x-tex">...</annotation>`.

## Risks

- Moving parser methods before node tests exist will turn every migration slice
  into a MathML string regression hunt.
- Macro expansion, declared math operators, and paired-delimiter declarations
  are tightly coupled today; extracting them together would create a broad
  conflict surface.
- Arrays carry local review metadata beyond TexMath `EArray`. Preserve that
  metadata as local node attributes instead of forcing a pure upstream AST.
- Accessibility currently reparses generated MathML. A node-native writer is
  cleaner, but switching it early risks changing review text/intent output.
- `MathTexConverterTest.php` is baseline-red on unrelated raw-TeX declaration
  and LaTeX writer cases in recent PlainMath notes. Treat focused passing tests
  as the lane gate until those baseline failures are cleared.
- Adding strict TexMath `form` or per-cell style output too early may change
  browser-facing MathML. Keep those as opt-in writer slices with fixtures.

## Verification

Commands run while preparing this note:

```text
git fetch origin main
git rebase origin/main
git ls-remote https://github.com/jgm/texmath.git HEAD
curl -sL --fail https://raw.githubusercontent.com/jgm/texmath/170899673ee31de9096e178605e8da31a36e4185/src/Text/TeXMath/Types.hs | nl -ba | sed -n '1,180p'
rg -n "function (parseExpression|parseAtom|parseCommand|parseEnvironment|renderEquationBody|environmentTable|arrayColumnAttributes|parseTextModeCommand|parseMathVariantCommand|rewriteMathVariantIdentifiers|normalizeMacroDefinitions|expandRawTexMathMacros|extractEquationMetadata|row\()" lanes/pandoc/src/MathTexConverter.php
rg -n "PlainMath|plainmath|MathML|TexMath|Types\.hs|writeMath|math" lanes/pandoc/src/HtmlWriter.php lanes/pandoc/src/MathTexConverter.php lanes/pandoc/src/MarkdownWriter.php lanes/pandoc/src/PlainWriter.php lanes/pandoc/tests
php tools/run-tests.php lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php
git diff --check
```

Local sources inspected:

- `lanes/pandoc/src/HtmlWriter.php`
- `lanes/pandoc/src/MathTexConverter.php`
- `lanes/pandoc/tests/MathTexConverterTest.php`
- `lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php`
- `lanes/pandoc/notes/plainmath-parser-extraction-plan-20260626.md`
- `lanes/pandoc/notes/plainmath-mathml-writer-fidelity-audit-20260628.md`
- `lanes/pandoc/notes/plainmath-atom-category-prototype-20260628.md`
- `lanes/pandoc/notes/plainmath-atom-coercion-direct-tokens-20260628.md`

Focused local gate:

```text
php tools/run-tests.php lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php
1 test files, 81 assertions, 0 failures
```

Follow-up verification on current `origin/main` after the later PlainMath
fixture and text-glyph normalization slices:

```text
git rebase origin/main
Current branch polecat/1780/plib-wj70q.19@mqzcmyy0 is up to date.

php tools/run-tests.php lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS promotes static texmath reader fixtures into plainmath conformance corpus
PASS promotes additional texmath reader fixtures into plainmath conformance corpus
PASS promotes texmath atom coercion fixtures into plainmath conformance corpus
PASS promotes unbraced texmath atom coercion tokens into plainmath conformance corpus

1 test files, 103 assertions, 0 failures
```

Current first-PR-contract update:

```text
git diff --check

php tools/run-tests.php lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS promotes static texmath reader fixtures into plainmath conformance corpus
PASS promotes additional texmath reader fixtures into plainmath conformance corpus
PASS promotes texmath atom coercion fixtures into plainmath conformance corpus
PASS promotes unbraced texmath atom coercion tokens into plainmath conformance corpus

1 test files, 103 assertions, 0 failures
```

No runtime behavior was changed in this lane.
