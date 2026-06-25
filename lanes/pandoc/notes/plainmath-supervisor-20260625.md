# Supervisor Goal: PlainMath / Generic TeX Parser Parity

## Outcome
- Move native PHP MathML generation from bounded EPUB-specific TeX handling
  toward parity with Pandoc's PlainMath path: TexMath `readTeX` plus
  `writeMathML`.
- Produce inspectable upstream inventory, conformance fixtures, parser/writer
  design, implementation slices, and verification evidence.
- Preserve the existing green EPUB3 behavior while expanding math coverage for
  HTML/EPUB MathML output.

## Intensity
- Level: high.
- Starting workers: 8 independent lanes.
- Scaling rule: add workers only for independent fixture families or parser
  components with defined artifacts; do not spawn multiple workers against the
  same `HtmlWriter` region without an integration owner.

## Non-Goals
- Do not shell out to Pandoc, Cabal, a Haskell executable, MathJax, KaTeX, or a
  network service at runtime.
- Do not claim perfect TeX engine parity, package loading, layout rendering, or
  browser MathML rendering.
- Do not regress existing EPUB3 package, XHTML, or MathML manifest behavior.
- Do not expand DRM, JavaScript, EPUBCheck, PDF, DOCX, ODT, or CSL scope.

## Ground Truth
- Local PHP implementation: `lanes/pandoc/src/HtmlWriter.php`, especially
  `texMathToMathML`, `parseTexMathRow`, `parseTexMathCommand`, and related
  helper methods.
- Primary upstream source: `.upstream-cache/texmath` at `1708996`, version
  `0.13.1.2`, especially:
  - `src/Text/TeXMath/Types.hs`
  - `src/Text/TeXMath/Readers/TeX.hs`
  - `src/Text/TeXMath/Readers/TeX/Commands.hs`
  - `src/Text/TeXMath/Readers/TeX/Macros.hs`
  - `src/Text/TeXMath/Writers/MathML.hs`
  - `test/test-texmath.hs`
  - `test/reader/tex`, `test/writer/mml`, `test/regression`, and
    `test/roundtrip`
- Pandoc integration source: `.upstream-cache/pandoc` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, which depends on
  `texmath >= 0.13.1.1 && < 0.14`.

## Worker Topology
- supervisor: owns branch, brief, bead decomposition, integration, test gates,
  and rejection of shallow command-table-only patches.
- lane-upstream-inventory: map TexMath parser/writer types, supported commands,
  macros, test fixture format, and select an initial parity corpus.
- lane-architecture: propose and/or implement an extracted PHP math layer
  rather than continuing unbounded private-method growth inside `HtmlWriter`.
- lane-conformance-harness: build PHP tests/helpers that can express TeX input,
  normalized MathML expectations, fallback behavior, and EPUB/HTML integration.
- lane-macros-lexing: implement or audit comments, labels/tags, macro
  definitions, whitespace, control sequences, and ignorable TeX constructs.
- lane-symbols-operators: implement or audit TexMath symbol/operator classes,
  bin-to-ord context correction, identifiers, numbers, primes, and named
  operators.
- lane-structure-scripts: implement or audit grouped expressions,
  sub/sup/subsup, under/over limits, fractions, roots, infix fractions, and
  delimiter handling.
- lane-environments-arrays: implement or audit arrays, matrices, aligned/cases,
  row/cell splitting, column alignment, and nested environment handling.
- lane-style-text: implement or audit style commands, text commands,
  enclosures, color/background, phantom/padded, operatorname, and spacing.
- lane-evaluator: independently compare implemented coverage against TexMath
  fixtures, run focused/full PHP tests, and record accepted gaps.

## Workflow
1. Inventory upstream TexMath and current PHP math behavior.
2. Establish a conformance harness with normalized expected MathML fixtures.
3. Extract or isolate parser/writer architecture so future parity work remains
   testable and reviewable.
4. Integrate implementation slices from smallest stable parser constructs
   outward.
5. Run focused math/EPUB/HTML tests after each integration batch.
6. Run full `php tools/run-tests.php lanes/pandoc/tests` before closure.

## Quality Gates
- Every implementation slice must add or extend PHP tests with exact MathML or
  normalized MathML assertions.
- Generated MathML must remain XML-parseable and keep the original TeX
  annotation when used by HTML/EPUB MathML output.
- Existing EPUB3 MathML tests must continue passing.
- Do not count a command as covered merely because it appears in a lookup table;
  it must parse through a representative formula.
- Parser failures must fall back predictably without producing malformed XHTML.

## Rejected Distractions
- More Unicode command aliases without addressing parser semantics.
- Runtime shell-outs to upstream `texmath`.
- Broad docs-only progress without a fixture, diff, or grounded gap list.
- Rewriting unrelated Pandoc readers/writers.
- Treating visual browser rendering differences as parser parity blockers.

## Final Acceptance Criteria
- A documented coverage matrix ties PHP behavior to TexMath parser/writer
  constructs and fixture families.
- Native PHP MathML generation covers the selected PlainMath parity corpus with
  exact or normalized MathML assertions.
- Existing EPUB3 reader/writer and full Pandoc lane tests pass.
- Remaining gaps are explicit, classified, and backed by failing/skipped
  fixtures or a clear out-of-scope decision.
