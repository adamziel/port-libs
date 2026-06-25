# Supervisor Goal: Full PlainMath / Generic TeX Parser Parity

## Outcome

- Replace the current direct TeX-to-MathML string-fragment path with a native PHP
  PlainMath layer that mirrors TexMath's `readTeX` then `writeMathML` boundary.
- Keep current HTML/EPUB MathML behavior green while building the new parser in
  shadow mode first.
- Drive every promotion with fixture-backed tests, not command-table accounting.

## Intensity

- Level: high.
- Starting workers: local integration plus bounded sidecar workers for token
  stream extraction and fixture/backlog audit.
- Scaling rule: add workers only for disjoint files or read-only audits with
  concrete artifacts; do not parallel-edit the same parser class.

## Non-Goals

- No runtime Pandoc, Cabal, Haskell TexMath, MathJax, KaTeX, or network calls.
- No JavaScript renderer, DRM, crypto authorization, or protected-content work.
- No claim of full TeX engine/package parity, category-code mutation, or visual
  browser layout parity.
- No broad alias-table patches without formulas that prove parser behavior.

## Ground Truth

- Current PHP behavior: `lanes/pandoc/src/HtmlWriter.php`.
- Current corpus: `lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`.
- Current plan: `lanes/pandoc/notes/plainmath-parser-extraction-plan-20260625.md`.
- Upstream TexMath shape: `.upstream-cache/texmath/src/Text/TeXMath/Types.hs`,
  `Readers/TeX.hs`, `Readers/TeX/Macros.hs`, `Readers/TeX/Commands.hs`, and
  `Writers/MathML.hs`.

## Worker Topology

- supervisor: owns branch, integration, commits, and test gates.
- shadow-writer: implements `PlainMath\Expression`, `MathMlBuilder`, and
  `MathMlWriter` with direct expression-to-MathML tests.
- token-stream worker: prototypes `PlainMath\TexTokenStream` plus focused cursor
  tests without wiring production behavior.
- fixture/backlog explorer: prioritizes next parser slices from current notes,
  corpus gaps, and upstream TexMath fixtures.

## Workflow

1. Land the current bounded custom environment gap closure.
2. Add shadow typed expression and MathML writer tests.
3. Add `expectedExpression` corpus metadata for selected existing fixtures so
   future parsers can prove typed shape independently from serialized MathML.
4. Add a token stream and preprocessor extraction behind independent tests.
5. Implement a core typed parser subset for atoms, rows, scripts, fractions,
   roots, infix fractions, and delimiters.
6. Add typed category normalization for `Bin` demotion, operator application,
   atom coercion, and Unicode symbol categories.
7. Move operators, arrays/environments, and text/style commands onto structural
   nodes by family.
8. Add writer fidelity for explicit delimiter `form`, per-cell table alignment,
   and constrained styled Unicode conversion once typed nodes carry the needed
   metadata.
9. Dual-run the typed renderer behind an internal option before changing
   `HtmlWriter` defaults.
10. Remove old `HtmlWriter` helpers only by covered family.

## Reader-Only Work Chunks

The project does not need a TeX writer. The remaining TeX work is a reader:
native PHP TeX/PlainMath source to typed `Expression` nodes, then existing
MathML output. Work should be sliced as:

1. **Core reader**
   Parse atoms, rows, groups, scripts, `\frac`, `\sqrt`, infix `\choose`, and
   `pmatrix` into `Expression` nodes. Gate this on the existing
   `expectedExpression` corpus metadata and the shadow `MathMlWriter`.
   Status: first slice implemented by `PlainMath\TexParser` and
   `PlainMathParserTest.php` for the initial five expression-shape fixtures.

2. **Preprocessor reader front end**
   Extract macro/comment/label/operator/environment definition handling from
   `HtmlWriter` into `TexPreprocessor`, returning normalized TeX plus
   diagnostics. This is still reader work because it feeds parse input.

3. **Reader category normalization**
   Add `SymbolCatalog` and a post-parse normalization pass for TexMath-style
   `Ord`, `Bin`, `Rel`, `Open`, `Close`, `Pun`, and `Op` categories, including
   bin-to-ord correction and atom coercion. This is the highest-value parity
   blocker.

4. **Structural reader families**
   Move operators, arrays/environments, text/style/enclosure, spacing, and
   remaining structural commands from `HtmlWriter` helpers into `TexParser`
   family by family.

5. **HtmlWriter adapter**
   Add `PlainMath\HtmlMathRenderer` and dual-run covered families before
   switching production rendering. Delete old helpers only after the typed
   reader owns the corresponding fixture family.

## Quality Gates

- Every code slice gets focused tests and `php -l` on touched PHP files.
- MathML expectations use XML parsing or exact strings only where stable.
- `PlainMathConformanceTest.php`, `HtmlWriterTest.php`, and `EpubWriterTest.php`
  pass before behavior changes reach `HtmlWriter`.
- Full `php tools/run-tests.php lanes/pandoc/tests` passes before push.

## Rejected Distractions

- More symbol aliases without typed parser or fixture coverage.
- Large Unicode table imports before category correction and styled Unicode
  expectations are explicit.
- Reformatting `HtmlWriter.php` around unrelated parser helpers.
- Treating valid-but-different browser rendering as parser failure.
- Package-specific TeX surfaces such as SIUnitX before core TexMath expression
  parity has a typed boundary.

## Prioritized Backlog

| Priority | Slice | Owned files | Gate |
| --- | --- | --- | --- |
| P0 | Expression shape fixtures | `plainmath-conformance-corpus.php`, `PlainMathWriterTest.php` | Corpus metadata round-trips through `Expression` and `MathMlWriter`. |
| P0 | Token stream skeleton | `PlainMath/TexTokenStream.php`, `PlainMathTokenStreamTest.php` | Cursor tests for commands, groups, optional brackets, spans, and UTF-8. |
| P0 | Preprocessor extraction | `PlainMath/TexPreprocessor.php`, `PlainMathPreprocessorTest.php`, narrow `HtmlWriter` delegation later | Macro/operator/environment fixtures and recursive fallback diagnostics. |
| P0 | Core typed parser | `PlainMath/TexParser.php`, `SymbolCatalog.php`, `MathMlWriter.php` | First slice covers atoms, rows, scripts, fractions, roots, infix `\choose`, and `pmatrix`; next slice adds symbol catalog and delimiter/operator breadth. |
| P0 | Category normalization | `SymbolCatalog.php`, parser normalizer tests | Promote `atom-coercion-bin-context-correction` from known gap. |
| P1 | Structural operators | `TexParser.php`, `Expression.php`, `MathMlWriter.php` | Declared/operatorname/limits fixtures use typed operator metadata. |
| P1 | Typed arrays/environments | `TexParser.php`, `Expression.php`, `MathMlWriter.php` | AMS/matrix/cases fixtures preserve table shape and enable per-cell fidelity. |
| P1 | HtmlWriter adapter | `PlainMath/HtmlMathRenderer.php`, `HtmlWriter.php` | Dual-run covered corpus before deleting old helpers. |

## Final Acceptance Criteria

- The branch has a tested typed expression/writer layer.
- A token stream and preprocessor layer exist or have concrete remaining tasks.
- The next parser parity backlog is fixture-backed and prioritized.
- Current bounded MathML output remains green across focused and full Pandoc
  tests.
