# PlainMath Parser Extraction Plan - 2026-06-25

Issue: `plib-wj70q.19`

Target branch: `plainmath-parity-20260625`

## Scope

This is a focused extraction plan for moving the current PlainMath path out of
`HtmlWriter` private string-fragment helpers and into a testable parser/writer
layer. It is grounded only in the current target branch:

- `lanes/pandoc/src/HtmlWriter.php`
- `lanes/pandoc/tests/PlainMathConformanceTest.php`
- `lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
- PlainMath notes already on `plainmath-parity-20260625`

This branch does not contain a separate `MathTexConverter.php` or `PlainWriter`
surface for the PlainMath path. Those are not migration boundaries for this
plan. Runtime Pandoc, Cabal, TexMath, MathJax, KaTeX, browser rendering,
network services, EPUBCheck, and non-PlainMath readers/writers stay out of
scope.

## Current Boundary

`HtmlWriter` owns three concerns that need to become separable:

1. HTML math method dispatch:
   - `renderMath()`
   - `renderMathML()`
   - `looksLikeMathMLElement()`
   - `mathMLWithRequiredAttributes()`

2. TeX preprocessing and parsing:
   - `preprocessTexMathSource()`
   - `stripTexMathIgnorable()`
   - `extractTexMathOperatorDeclarations()`
   - `extractTexMathMacroDefinitions()`
   - `expandTexMathMacros()`
   - `parseTexMathRow()`
   - `parseTexMathScriptedAtom()`
   - `parseTexMathAtom()`
   - `parseTexMathCommand()`
   - `parseTexMathArgument()`
   - delimiter, environment, matrix, raw group, command-name, number,
     identifier, and UTF-8 character readers

3. MathML string emission:
   - `texMathToMathML()`
   - `texMathFractionElement()`
   - `texMathGeneralFractionElement()`
   - `texMathInfixFractionElement()`
   - `texMathMStyleElement()`
   - `texMathModuloElement()`
   - `texMathNamedOperatorElement()`
   - `texMathMatrixToMathML()`
   - `texMathFencedRow()`
   - `texMathCommandElement()`
   - `mathMLRow()`

The conformance harness currently proves the integrated behavior by rendering
through `HtmlWriter(['writerHTMLMathMethod' => 'mathml'])`. The corpus records
51 passing MathML cases, seven fallback cases, and three known gaps. The important
remaining architectural blocker is that parser output is already serialized
MathML, so there is nowhere durable to hold TexMath-like atom categories,
operator metadata, source spans, or diagnostics.

## Target Model

TexMath separates the pipeline into `readTeX` producing typed `Exp` values and
`writeMathML` consuming those values. The PHP target should mirror that shape
without trying to port Haskell APIs literally.

New module boundary:

- `lanes/pandoc/src/PlainMath/Expression.php`
  Immutable tagged node with `kind`, `value`, `children`, `attributes`, and
  optional source span fields.
- `lanes/pandoc/src/PlainMath/TexParseResult.php`
  `ok`, `expressions`, `diagnostics`, `source`, and consumed byte count.
- `lanes/pandoc/src/PlainMath/TexTokenStream.php`
  Cursor for commands, groups, optional brackets, comments, whitespace,
  delimiters, UTF-8 characters, and source spans.
- `lanes/pandoc/src/PlainMath/TexPreprocessor.php`
  Bounded macro/operator/environment definition extraction and expansion.
- `lanes/pandoc/src/PlainMath/TexParser.php`
  Converts TeX source to `Expression` lists.
- `lanes/pandoc/src/PlainMath/SymbolCatalog.php`
  Command maps plus TexMath-style atom category metadata.
- `lanes/pandoc/src/PlainMath/MathMlWriter.php`
  Converts expression lists to MathML fragments or full `<math>` documents.
- `lanes/pandoc/src/PlainMath/MathMlBuilder.php`
  Small XML builder for escaping, attributes, namespace, `semantics`, and TeX
  annotation output.
- `lanes/pandoc/src/PlainMath/HtmlMathRenderer.php`
  Adapter used by `HtmlWriter::renderMathML()` once the new path is ready.

`HtmlWriter` should end with only method dispatch, existing explicit-MathML
sanitization, and fallback span behavior. It should not own TeX parsing or
MathML expression semantics long term.

Expression kinds should cover the current corpus and TexMath `Types.hs` shape:

- atom nodes: `number`, `identifier`, `mathOperator`, `symbol`, `space`
- grouping nodes: `row`, `group`, `delimited`
- script nodes: `sub`, `super`, `subsup`
- over/under nodes: `over`, `under`, `underover`
- structure nodes: `fraction`, `sqrt`, `root`, `array`
- text/style nodes: `text`, `styled`
- enclosure/layout nodes: `phantom`, `boxed`, `cancel`, `padded`
- metadata fields: atom category, convertible-limits flag, display style,
  source span, and diagnostics code

## Migration Slices

1. **Shadow writer, no HtmlWriter wiring**

   Add `Expression`, `MathMlBuilder`, and `MathMlWriter` with tests that build
   expression trees directly and compare canonical MathML. Cover only the
   smallest stable families first: numbers, identifiers, symbols, rows, scripts,
   fractions, roots, fenced rows, and a two-row array. Do not call this path from
   `HtmlWriter` yet.

2. **Corpus expression fixtures**

   Extend `plainmath-conformance-corpus.php` with optional `expectedExpression`
   metadata for a small subset. This lets tests prove parser shape separately
   from MathML serialization before default output changes.

3. **Token stream and preprocessor extraction**

   Move the current macro/comment/operator preprocessing behavior into
   `TexPreprocessor` with exact compatibility tests for:

   - `macro-square`
   - `macro-optional-default`
   - `declared-operator`
   - `ignorable-label-tag-comment`
   - recursive macro fallback

   Keep `HtmlWriter` behavior unchanged during this slice, either by wrapper
   delegation or by testing the new preprocessor independently before wiring.

4. **Core parser subset**

   Implement `TexParser` for atoms, rows, groups, scripts, `\frac`, `\sqrt`,
   infix fractions, delimiters, and simple command lookup. Run parsed
   expressions through `MathMlWriter` and compare canonical MathML to the
   existing corpus subset. This is where source spans and structured diagnostics
   start to matter.

5. **Typed category pass**

   Add TexMath-style atom categories and a post-parse normalization pass before
   MathML writing. This should target the current P0 gaps:

   - `unicode-symbol-category-parity`
   - bin-to-ord correction
   - atom coercion commands
   - function application after math operators
   - `\operatorname*` convertible limits metadata

6. **Environment and text expansion**

   Move arrays, matrices, align/gather/multline/eqnarray, text commands, style
   commands, enclosures, and spacing into the typed parser only after the core
   parser is stable. This avoids another broad private-method growth cycle in
   `HtmlWriter`.

7. **HtmlWriter adapter dual-run**

   Introduce `PlainMath\HtmlMathRenderer` behind an internal option first. Tests
   should compare old and new output for the pass corpus. Only after that should
   `HtmlWriter::renderMathML()` default to the new renderer for covered cases.

8. **Delete old helpers by family**

   Remove old private `texMath*` helpers only after the replacement family has
   focused tests, EPUB integration proof, and full lane proof. Deletion order:
   atoms/scripts, fractions/roots, delimiters, environments, text/style,
   macros/preprocessing, command catalog.

## First Safe Extraction Step

The first code slice is now a shadow `PlainMath\MathMlWriter` plus
`PlainMath\Expression`, not a parser move and not a `HtmlWriter` rewrite.

Reason:

- It touches new files instead of the hot `HtmlWriter.php` parser region.
- It proves the output side of the future boundary with canonical XML tests.
- It gives later parser work a typed target.
- It does not change current HTML/EPUB behavior or fallback policy.

Concrete first slice:

- Added `lanes/pandoc/src/PlainMath/Expression.php`.
- Added `lanes/pandoc/src/PlainMath/MathMlBuilder.php`.
- Added `lanes/pandoc/src/PlainMath/MathMlWriter.php`.
- Added `lanes/pandoc/tests/PlainMathWriterTest.php`.
- Covered direct expression-to-MathML cases corresponding to corpus IDs
  `script-super`, `implicit-product-identifiers`, `display-fraction-root`,
  `infix-choose`, and `pmatrix-two-by-two`.
- Added `expectedExpression` metadata for those corpus cases so future parser
  work has a typed shape contract independent of MathML serialization.
- Added a shadow `PlainMath\TexTokenStream` skeleton with immutable cursor,
  span, command, raw group, optional bracket, comment/whitespace, and UTF-8
  character tests.
- Added `PlainMath\TexParseResult` and the first `PlainMath\TexParser` reader
  slice. It parses the initial expression-shape corpus families into typed
  `Expression` nodes: atoms/rows, groups, scripts, `\frac`, `\sqrt`, infix
  `\choose`, and `pmatrix`.
- Did not modify `HtmlWriter.php` in this extraction slice.

The next reader-only chunk is preprocessor extraction, followed by a
`SymbolCatalog` plus category-normalization pass. No TeX writer is planned.

## Test Strategy

Focused gates for planning and extraction slices:

- `php -l lanes/pandoc/src/HtmlWriter.php`
- `php -l lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainMathConformanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/HtmlWriterTest.php`

When any HTML adapter behavior changes:

- `php tools/run-tests.php lanes/pandoc/tests/PlainMathConformanceTest.php lanes/pandoc/tests/HtmlWriterTest.php`

When generated EPUB XHTML or manifest math properties can change:

- `php tools/run-tests.php lanes/pandoc/tests/PlainMathConformanceTest.php lanes/pandoc/tests/HtmlWriterTest.php lanes/pandoc/tests/EpubWriterTest.php`

Before submitting behavior-changing PlainMath branches:

- `php tools/run-tests.php lanes/pandoc/tests`

Assertions should prefer DOM canonicalization for MathML. Exact strings should
stay limited to fallback spans, manifest/package snippets, and current writer
surface compatibility tests.

## Risks

- `HtmlWriter.php` is an active integration file. Avoid moving parser code there
  until new writer/parser tests are in place.
- Direct XML-string parsing hides atom category and source-span information.
  Preserve current output until typed expressions can prove intentional changes.
- Macro expansion can loop. Keep bounded expansion and fallback behavior before
  adding diagnostics.
- UTF-8 token handling must stay byte-safe for source offsets and XML-safe for
  output.
- MathML writer fidelity changes can break EPUB packaging through manifest
  `mathml` properties and XHTML validity.
- Broad command-table additions without formula-level corpus coverage should be
  rejected as shallow progress.

## Verification For This Plan

This lane is a documentation and migration-plan lane. No code prototype is added
because the lowest-risk first implementation slice should be isolated new
PlainMath writer files, and other workers are actively changing the same
`HtmlWriter` math region.

Inspected artifacts:

- `lanes/pandoc/src/HtmlWriter.php`
- `lanes/pandoc/tests/PlainMathConformanceTest.php`
- `lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
- `lanes/pandoc/notes/plainmath-supervisor-20260625.md`
- `lanes/pandoc/notes/plainmath-architecture-20260625.md`
- `lanes/pandoc/notes/plainmath-texmath-inventory-20260625.md`
- `lanes/pandoc/notes/plainmath-conformance-20260625.md`
- `lanes/pandoc/notes/plainmath-evaluator-20260625.md`
