# PlainMath Parser Extraction Plan

Date: 2026-06-26 UTC
Bead: `plib-wj70q.19`

## Scope

This is a plan-only lane for moving the TeX math path from private string
fragment parsing toward a testable PlainMath parser and writer layer. No runtime
code prototype is included: `MathTexConverter.php` is a high-conflict active
lane, and the safe deliverable here is a precise extraction boundary.

The current `HtmlWriter` boundary is small. `HtmlWriter::renderMath()` selects
HTML output modes (`mathjax`, `katex`, `webtex`, `gladtex`, or plain escaped
source) and does not own the native MathML parser. The practical extraction
target is therefore the `MathTexConverter` private parser/writer cluster that
Html/WordPress/EPUB handoff lanes already depend on for source TeX to MathML.

## Current Boundaries

### Keep in `HtmlWriter`

- `HtmlWriter::renderMath()` should stay responsible for HTML math method
  selection and source-preserving fallback spans.
- Add native MathML later as a new `htmlMathMethod` value only after the
  PlainMath facade is stable. That integration should call a public facade; it
  should not import parser internals into `HtmlWriter`.

### Split from `MathTexConverter`

Keep `MathTexConverter` as the compatibility facade with the current public API:

- `latexFor(AstNode $node)`
- `mathMlFor(AstNode $node, array $macros = [], array $referenceLabels = [])`
- `accessibleMathMlFor(...)`
- `texToMathMl(...)`
- `texToAccessibleMathMl(...)`

Extract these private responsibilities behind it:

| New module | Responsibility | Current source cluster |
| --- | --- | --- |
| `PortLibs\Pandoc\PlainMath\RawTexDefinitions` | Normalize macro/environment/declaration definitions before parsing. | `normalizeMacroDefinitions`, `extractRawTexEnvironmentDefinitions`, `readRawTexMacroDefinition`, `readRawTexDeclaredMathOperator`, paired delimiter declaration readers, and `expandRawTexMathMacros*`. |
| `PortLibs\Pandoc\PlainMath\Parser` | Parse expanded TeX into a typed PlainMath tree with cursor/error state. | `parseExpression`, `parseAtom`, `parseCommand`, `parseEnvironment`, `parseTexFragment`, group/bracket/fence readers, spacing/comment readers, and script placement state. |
| `PortLibs\Pandoc\PlainMath\Nodes` | Value objects or tagged arrays mirroring the useful TexMath `Exp` surface. | Current inline MathML strings returned from parser methods. |
| `PortLibs\Pandoc\PlainMath\MathMlWriter` | Serialize `Nodes` to MathML fragments and full `<math><semantics>...` wrappers. | `row`, token XML string construction, equation metadata rendering, array table rendering, styled Unicode rewrite, and transient metadata stripping. |
| `PortLibs\Pandoc\PlainMath\AccessibilityWriter` | Derive alt text and intent from the generated MathML or, later, directly from nodes. | `mathMlAccessibilityMetadata`, `mathMlNodeAltText`, and `mathMlNodeIntent` families. |

## PlainMath Node Shape

Ground the first node set in TexMath `Types.hs`, not in local ad hoc XML. The
useful subset maps directly:

- `ENumber` -> `NumberNode`
- `EIdentifier` -> `IdentifierNode`
- `EMathOperator` -> `MathOperatorNode`
- `ESymbol TeXSymbolType Text` -> `SymbolNode($type, $text)`
- `EGrouped [Exp]` -> `GroupNode`
- `EDelimited open close [Either Middle Exp]` -> `DelimitedNode` with explicit
  middle separators
- `ESpace Rational` -> `SpaceNode`
- `ESub`, `ESuper`, `ESubsup` -> script nodes
- `EOver`, `EUnder`, `EUnderover` -> over/under nodes with the convertible flag
- `EFraction FractionType`, `ERoot`, `ESqrt`, `EScaled`
- `EArray [Alignment] [ArrayLine]`
- `EText TextType` and `EStyled TextType [Exp]`
- local extensions for review metadata: equation labels/tags, row spacing,
  array rules, `\multicolumn` provenance, href/ref targets, and cancel-to
  annotations

This keeps TexMath parity visible while preserving local metadata that upstream
`Exp` does not model.

## Migration Slices

1. Characterization slice: add `PlainMathParserCharacterizationTest` fixtures
   that lock current outputs for `x_1 + \frac{a}{b}`,
   `\left(a\middle|b\right)`, and `\begin{array}{lcr}...`. This is the guard
   rail before structural extraction.
2. Node scaffold slice: add `PlainMath\Nodes` and a parser result type, with no
   production wiring. Include constructors for the TexMath-aligned subset above
   and tests that compare PHP arrays from fixture builders.
3. First parser extraction slice: move only numeric, identifier, operator
   punctuation, grouping, scripts, and `\frac` into `PlainMath\Parser`. Keep
   `MathTexConverter::texToMathMl()` calling the existing private parser until
   the new parser can round-trip one fixture through the writer.
4. First writer extraction slice: write `MathMlWriter` for the nodes from slice
   3 and compare exact MathML fragments against the existing converter for the
   first fixture.
5. Delimiter slice: move `\left`, `\middle`, `\right`, sized delimiters, and
   `readFenceDelimiter`. This is where TexMath `EDelimited` and `ESymbol
   Open/Close/Fence` become testable separately from XML attributes.
6. Array slice: move `array`, matrix, AMS row environments, column specs, row
   spacing, rules, and `\multicolumn`. This should happen after the delimiter
   slice because it is the broadest conflict surface.
7. Facade switch slice: flip `MathTexConverter::texToMathMl()` to use
   `RawTexDefinitions -> Parser -> MathMlWriter`, keeping source annotations,
   equation metadata, and accessibility output byte-compatible for covered
   fixtures.
8. Html integration slice: add a native `htmlMathMethod => 'mathml'` path in
   `HtmlWriter` that delegates to the facade. This should be last, because
   `HtmlWriter` should not be the parser extraction battleground.

## First Safe Extraction Step

Do slice 1, then slice 2. The first production-code move should be a very small
slice 3:

- introduce `PlainMath\Parser::parseInline(string $tex): list<Node>`;
- support only identifiers, numbers, ordinary single-character operators,
  braced groups, `_`, `^`, and `\frac`;
- introduce `PlainMath\MathMlWriter::writeFragment(array $nodes): string`;
- assert byte parity with the current converter body for
  `x_1 + \frac{a}{b}`;
- leave `HtmlWriter` untouched and leave the current `MathTexConverter`
  internals as the fallback for every unsupported construct.

That step proves the parser/writer split without touching the delimiter,
environment, macro, accessibility, or HtmlWriter surfaces.

## Test Strategy

- Parser unit tests should assert node arrays, not MathML strings.
- Writer unit tests should assert MathML fragments for the same nodes.
- Facade tests should keep using `MathTexConverterTest.php` fixtures so existing
  public behavior stays covered.
- Add targeted `HtmlWriter` tests only when `htmlMathMethod => 'mathml'` is
  introduced.
- Keep current source annotation behavior stable:
  `<annotation encoding="application/x-tex">...</annotation>` is a local review
  feature and should survive the split.
- Use focused runs first:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`,
  plus new `PlainMath*Test.php` files.

## Risks

- The current parser returns MathML strings directly, so parser changes can
  accidentally change writer behavior before there is a node-level test.
- Macro/environment expansion is interleaved with paired-delimiter special
  cases; extracting it too early will collide with active paired-delimiter lanes.
- Array support carries local review metadata that TexMath `EArray` does not
  model. Preserve metadata as local node attributes rather than forcing a
  byte-for-byte upstream AST.
- Accessibility currently derives alt text and intent by reparsing generated
  MathML. A direct node-based accessibility path is attractive, but it should
  wait until node parity is proven.
- Known current test failures in the broad math handoff suite are unrelated to
  this note and should be resolved before treating a full `MathTexConverterTest`
  run as a clean extraction gate.

## Verification

Source truth checked:

```text
git ls-remote https://github.com/jgm/texmath.git HEAD
170899673ee31de9096e178605e8da31a36e4185	HEAD
```

Primary upstream source inspected:

- `https://github.com/jgm/texmath/blob/170899673ee31de9096e178605e8da31a36e4185/src/Text/TeXMath/Types.hs`
- `https://github.com/jgm/texmath/blob/170899673ee31de9096e178605e8da31a36e4185/src/Text/TeXMath/Readers/TeX.hs`
- `https://github.com/jgm/texmath/blob/170899673ee31de9096e178605e8da31a36e4185/src/Text/TeXMath/Writers/MathML.hs`

Local source inspected:

- `lanes/pandoc/src/HtmlWriter.php`
- `lanes/pandoc/src/MathTexConverter.php`
- `lanes/pandoc/tests/MathTexConverterTest.php`
- `lanes/pandoc/notes/plainmath-mathml-writer-fidelity-audit-20260626.md`

Local probes:

```text
php -r 'require "tools/bootstrap.php"; $c=new PortLibs\Pandoc\MathTexConverter(); foreach (["x_1 + \\frac{a}{b}", "\\left(a\\middle|b\\right)", "\\begin{array}{lcr}a&b&c\\\\x&y&z\\end{array}"] as $tex) { echo "--- ".$tex."\n"; echo $c->texToMathMl($tex, true)."\n"; }'
```

Observed:

- `x_1 + \frac{a}{b}` currently emits one `<mrow>` with `msub`, `mo +`, and
  `mfrac`.
- `\left(a\middle|b\right)` currently emits fence/stretch/separator MathML
  directly from parser methods; the prior audit records missing TexMath-style
  `form` attributes as a writer gap.
- `array{lcr}` currently emits table-level `columnalign="left center right"`
  and plain `mtd` cells; the prior audit records per-cell alignment/style as a
  writer gap.

Focused gate:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1352 assertions, 6 failures
```

The six failures are pre-existing current-branch failures around markdown raw
TeX declaration capture / paired-delimiter templates and LaTeX writer source
handoff. This lane changed only this architecture note.
