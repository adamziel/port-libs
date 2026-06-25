# PlainMath Parser Architecture Plan

Issue: `plib-wj70q.2`

Branch target: `plainmath-parity-20260625`

## Scope

This note covers the parser architecture lane from
`plainmath-supervisor-20260625.md`: inspect the current PHP TeX-to-MathML path in
`HtmlWriter`, inspect upstream TexMath `readTeX` and `writeMathML`, and define a
safe migration plan toward a native PHP PlainMath layer.

No runtime shell-outs, Cabal/Haskell execution, MathJax/KaTeX runtime parsing, or
network service is in scope. Existing EPUB/HTML MathML output must stay green
through every migration step.

The supervisor-referenced `.upstream-cache/texmath` checkout was absent in this
amber worktree. Inspection used the read-only shared cache at
`/home/claude/port-libs/polecats/flint/port_libs/.upstream-cache/texmath`,
confirmed against upstream commit `170899673ee31de9096e178605e8da31a36e4185`.

## Current PHP Shape

`lanes/pandoc/src/HtmlWriter.php` currently owns all native TeX-to-MathML output:

- `renderMath()` selects `mathml`, `mathjax`, `katex`, `webtex`, `gladtex`, or
  fallback span output.
- `renderMathML()` accepts trusted existing `<math>` payloads, otherwise calls
  `texMathToMathML()`.
- `texMathToMathML()` trims source, calls `parseTexMathRow()`, wraps generated
  fragments in `<math><semantics>...<annotation encoding="application/x-tex">`.
- `parseTexMathRow()`, `parseTexMathScriptedAtom()`, `parseTexMathAtom()`, and
  `parseTexMathCommand()` form a recursive parser, but they emit MathML strings
  directly rather than building a typed intermediate tree.
- Later helpers cover arguments, optional bracket arguments, raw groups,
  delimiters, matrix body splitting, command-name scanning, negated relations,
  command lookup, and `mathMLRow()`.

The current code is valuable but structurally hard to extend:

- Parser state is an `int &$offset`; there is no token stream, source span, error
  object, or recoverable parse result.
- Commands mix lexical parsing, semantic interpretation, and MathML emission in
  one long method.
- MathML string fragments are used as the parser's AST. This makes later semantic
  rewrites, such as TeX binop-to-ord correction, function application insertion,
  text styling, or display-style decisions, fragile.
- `mathMLRow()` decides whether to wrap arbitrary generated XML in `<mrow>`,
  which means tree shape can depend on already-serialized strings.
- The path is private to `HtmlWriter`, so future workers can only add more
  private methods or broad command tables unless a seam is introduced.

## Upstream TexMath Shape

TexMath separates the pipeline into typed phases:

- `readTeX :: Text -> Either Text [Exp]` in
  `src/Text/TeXMath/Readers/TeX.hs` parses source to a typed `Exp` list.
- `parseMacroDefinitions` and `applyMacros` in
  `src/Text/TeXMath/Readers/TeX/Macros.hs` run before formula parsing, with
  bounded recursive expansion.
- `Types.hs` defines `Exp`, including `ENumber`, `EGrouped`, `EDelimited`,
  `EIdentifier`, `EMathOperator`, `ESymbol`, `ESpace`, `ESub`, `ESuper`,
  `ESubsup`, `EOver`, `EUnder`, `EUnderover`, `EPhantom`, `EBoxed`, `ECancel`,
  `EFraction`, `ERoot`, `ESqrt`, `EScaled`, `EArray`, `EText`, and `EStyled`.
- `TeXSymbolType` keeps TeX atom categories (`Ord`, `Op`, `Bin`, `Rel`, `Open`,
  `Close`, `Pun`, `Accent`, `Fence`, `TOver`, `TUnder`, `Alpha`, `BotAccent`,
  `Rad`) independent from output tags.
- `readTeX` applies a `fixBinList` pass after parsing, converting binary
  operators to ordinary symbols when TeX spacing rules require it.
- `Commands.hs` stores semantic maps for style commands, text commands,
  enclosures, operators, symbols, and `siunitx` units.
- `writeMathML :: DisplayType -> [Exp] -> Element` in `Writers/MathML.hs` walks
  the typed tree, inserts function-application operators, computes MathML
  elements/attributes, and returns an XML tree before pretty-printing.

The golden tests are source/target fixtures:

- `test/reader/tex/*.test`: `<<< tex` to `>>> native`.
- `test/writer/mml/*.test`: `<<< native` to `>>> mml`.
- `test/regression/*.test`: focused historical behavior.
- `test/roundtrip/*.native`: round-trip fixtures.

Representative fixtures inspected:

- `reader/tex/macros.test`: macro definitions, optional args, comments,
  `\renewcommand`, and `\newenvironment`.
- `reader/tex/subsup.test`: sub/sup ordering, convertible limits, accents,
  over/under constructs, and bare sub/sup.
- `reader/tex/genfrac.test`: general fraction to `EDelimited` plus
  `EFraction NoLineFrac`.
- `writer/mml/01.test`: native `Exp` quadratic formula to exact MathML.
- `regression/234.test`: binop context behavior.

## Proposed PHP Architecture

Introduce a native PlainMath package under `lanes/pandoc/src/PlainMath/` only
after a conformance harness is in place.

Initial classes:

- `PlainMath\TexParser`: public entry `parse(string $source): TexParseResult`.
- `PlainMath\TexParseResult`: `ok`, `expressions`, `diagnostics`,
  `consumedBytes`, and original source.
- `PlainMath\TexTokenStream`: byte/UTF-8 aware cursor, source spans, comments,
  control sequences, groups, optional brackets, and whitespace/ignorable
  skipping.
- `PlainMath\TexMacroExpander`: parses and applies `\newcommand`,
  `\renewcommand`, `\providecommand`, `\newenvironment`,
  `\renewenvironment`, and `\DeclareMathOperator` in a bounded loop.
- `PlainMath\Expression`: value objects or tagged arrays mirroring TexMath
  `Exp`. Prefer a single immutable node class with `kind`, `value`, and
  `children` fields until the model stabilizes; avoid dozens of PHP classes at
  first.
- `PlainMath\SymbolCatalog`: generated/static maps for commands, symbol type,
  enclosures, style ops, text ops, spacing, and named operators.
- `PlainMath\MathMlWriter`: `write(DisplayMode $mode, list<Expression> $exprs,
  string $annotationSource): string`.
- `PlainMath\XmlBuilder`: tiny escaping/element builder used by MathML output,
  not tied to `HtmlWriter::esc()`.
- `PlainMath\HtmlMathRenderer`: adapter used by `HtmlWriter::renderMathML()` to
  wrap successful output in existing `<semantics>`/annotation behavior and
  preserve current fallback spans on parse failure.

Expression model to mirror TexMath:

- `number(text)`
- `identifier(text)`
- `mathOperator(text)`
- `symbol(type, text)`
- `space(widthEmRationalOrString)`
- `group(list<Expression>)`
- `delimited(open, close, list<Expression|middle>)`
- `sub(base, sub)`, `super(base, super)`, `subsup(base, sub, super)`
- `over(convertible, base, above)`, `under(convertible, base, below)`,
  `underover(convertible, base, below, above)`
- `fraction(type, numerator, denominator)`
- `sqrt(base)`, `root(index, base)`
- `array(alignments, rows)`
- `text(textType, text)`, `styled(textType, exprs)`
- `phantom(expr)`, `boxed(expr)`, `cancel(strokeType, expr)`, `scaled(scale, expr)`

Keep source spans optional but planned on every node. They will make future
diagnostics and fallback decisions precise without changing output semantics.

## Migration Steps

1. **Harness first**

   Add `PlainMathConformanceTest.php` with a fixture helper that can express:

   - TeX input.
   - Expected normalized PHP expression tree.
   - Expected normalized MathML.
   - Whether the formula is accepted or must fall back to escaped text/span.

   Use a small hand-selected fixture set from TexMath before importing broad
   upstream fixture directories. The first test group should include:
   numbers/identifiers/operators, grouping, sub/sup/subsup, `\frac`, `\sqrt`,
   `\left...\right`, named operators, text/style commands, arrays, and one
   macro fixture.

2. **Create AST and writer without switching `HtmlWriter`**

   Implement `Expression`, `MathMlWriter`, and a minimal `XmlBuilder`.
   Start by reproducing the MathML shape currently emitted by `HtmlWriter` for
   simple already-covered formulas. Do not change `HtmlWriter` yet.

3. **Add parser for the smallest stable subset**

   Implement `TexTokenStream` and `TexParser` for:

   - Numbers, identifiers, single-character operators.
   - Groups.
   - Control sequence scanning.
   - Subscript/superscript.
   - Fractions and square roots.

   Add a post-parse binop correction pass before MathML writing.

4. **Dual-run behind a private adapter**

   In `HtmlWriter::renderMathML()`, call the new renderer only for the subset
   marked safe by tests, or behind a private option defaulting off during the
   extraction slice. Compare generated output against the old path in tests
   before flipping default behavior.

5. **Move existing `HtmlWriter` coverage by family**

   For each existing EPUB MathML family, move behavior into the new parser/writer
   and delete only the corresponding old helper code after focused tests and
   `EpubWriterTest.php` pass. Recommended order:

   - Core atoms, grouping, rows, scripts.
   - Fractions, roots, infix fraction forms.
   - Delimiters and matrices.
   - Text/style/spacing.
   - Accents, over/under, enclosures, phantom/cancel.
   - Symbol/operator catalog.
   - Macros and environments.

6. **Replace private `HtmlWriter` parser**

   Once the new path covers all currently green behavior, reduce `HtmlWriter` to
   adapter calls plus fallback behavior. Only then remove the old private
   parser helpers.

## Test Plan

Every code slice should run:

- `php -l` for touched PHP files.
- Focused PlainMath tests.
- Relevant `MarkdownReaderTest.php` HTML MathML writer tests if `HtmlWriter`
  integration changes.
- `php tools/run-tests.php lanes/pandoc/tests/EpubWriterTest.php` if EPUB
  MathML output or manifest properties can change.
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
  lanes/pandoc/tests/EpubWriterTest.php` for parser/writer integration slices.
- Full `php tools/run-tests.php lanes/pandoc/tests` before submission of any
  behavior change.

Initial conformance fixtures should be copied conceptually, not wholesale, from
TexMath:

- `reader/tex/01.test`, `02.test`, `tokens.test` for atom/token basics.
- `reader/tex/subsup.test` for script behavior and limits.
- `reader/tex/genfrac.test`, `choose.test`, `binomial_coefficient.test` for
  fraction families.
- `reader/tex/macros.test` after the macro expander exists.
- `reader/tex/operatorname.test` and `text.test` for styled/text content.
- `writer/mml/01.test` for writer tree-to-MathML shape.
- `regression/234.test` for binop correction.

The harness should normalize MathML with `DOMDocument` or `XMLReader`, not
string replacements, so attribute ordering and whitespace do not create false
failures. Exact string assertions should remain only where current EPUB output
requires exact packaging behavior.

## Risks

- **Output drift**: TexMath-shaped AST output may differ from current
  `HtmlWriter` string fragments. Mitigation: dual-run only covered formulas and
  preserve current output until focused tests accept intentional changes.
- **Escaping drift**: `HtmlWriter::esc()` currently escapes all emitted MathML
  text. New XML builder must match escaping behavior for annotation and text
  nodes.
- **UTF-8 cursor bugs**: current parser is byte-oriented and mostly ASCII
  control-flow. New token stream must handle Unicode identifiers/operators
  without corrupting offsets.
- **Macro recursion**: TexMath uses bounded fixed-point expansion. PHP must
  enforce a clear expansion limit and return a diagnostic/fallback on loops.
- **Binop semantics**: current direct MathML output has no typed binop
  correction. Introducing it can change spacing/operator element choices.
  Treat as an intentional parity change only with tests.
- **Environment parsing**: current matrix splitting manually tracks braces,
  brackets, and nested environments. A token-stream parser should own this, but
  transition mistakes can break existing EPUB matrix fixtures.
- **Worker conflicts**: many PlainMath lanes may touch `HtmlWriter.php`.
  Architecture work should avoid moving code until the harness is merged.
- **Fixture volume**: importing all TexMath fixtures at once will make failures
  unreviewable. Add small families with explicit accepted gaps.

## Safe Scaffolding Decision

No PHP scaffolding was added in this slice. The safe prerequisite is the
conformance harness, because extracting parser methods now would create a new
API without tests that prove equivalence. The lowest-risk first code change is a
test-only fixture helper plus a minimal `Expression`/`MathMlWriter` pair that is
not wired into `HtmlWriter`.

## Concrete Next Slices

1. `plainmath-conformance-harness`: add fixture helper and two no-production
   tests that assert current `HtmlWriter` output for simple TeX and validate
   normalized MathML parsing.
2. `plainmath-ast-writer-core`: add `Expression` plus `MathMlWriter` for
   `number`, `identifier`, `symbol`, `group`, `fraction`, `sqrt`, and scripts,
   tested against a small native-to-MathML fixture set.
3. `plainmath-parser-core`: add token stream and parser for atoms, groups,
   scripts, `\frac`, `\sqrt`, and named operators, still not wired into
   `HtmlWriter`.
4. `plainmath-htmlwriter-dual-run`: behind a private option, dual-run new
   parser/writer for a limited set and prove output compatibility before
   defaulting it on.
5. `plainmath-macro-environment`: add macro expansion and arrays/environments
   only after the core parser has stable source spans and diagnostics.

## Verification For This Slice

This slice is documentation only. Required verification is structural:

- Confirmed supervisor brief at `lanes/pandoc/notes/plainmath-supervisor-20260625.md`.
- Inspected `HtmlWriter` math parser/writer region.
- Inspected upstream TexMath `Types.hs`, `Readers/TeX.hs`,
  `Readers/TeX/Commands.hs`, `Readers/TeX/Macros.hs`, `Writers/MathML.hs`, and
  representative golden tests from the shared cache.
- No production PHP was touched, so no PHP unit test was required by this lane.
