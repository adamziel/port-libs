# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T105414Z`

Base accepted HEAD: `5981f5c45290b13799261fadb4def4415f4483ff`

## Behavior Added

- Added bounded native Math/TeX support for legacy `eqnarray` and
  `eqnarray*` environments.
- Each row must have exactly three top-level cells, matching the legacy
  `right center left` alignment shape around relation markers such as
  `p_i &=& m_i`.
- The converter reuses the existing MathML table renderer, row tag/label
  metadata extraction, source-TeX semantics annotations, and malformed row
  guards.
- Empty environments, trailing row separators, and wrong column counts are
  rejected before MathML handoff rather than invoking a TeX engine.
- Updated the WordPress Math/TeX handoff example so legacy `eqnarray` source
  remains editable while the native MathML table stays visible for review.

## Source Truth And Scope

Source truth is the lane's accepted Pandoc-like math contract: preserve source
TeX in math nodes, convert bounded math structures to native MathML where safe,
and keep WordPress review packets external-renderer free.

Prior Math/TeX slices already covered fractions, roots, generalized and infix
fractions, delimiters, arrays, smallmatrix/subarray, AMS row environments,
`alignedat`, `flalign`, `multline`, equation wrappers, row tags, labels,
references, and array preamble metadata. This slice adds only the bounded
legacy `eqnarray` three-column alignment handoff.

## Verification

- Baseline focused check:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 466 assertions, 0 failures`.
- Red-first direct conversion:
  `php -r 'require "tools/bootstrap.php"; $c = new \PortLibs\Pandoc\MathTexConverter(); echo $c->texToMathMl("\\begin{eqnarray}p_i &=& m_i \\\\ x_i &=& y_i\\end{eqnarray}", true);'`
  - Result: fatal `InvalidArgumentException`, `Unsupported TeX environment
    eqnarray at offset 16`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 475 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+9` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- Syntax checks:
  `php -l lanes/pandoc/src/MathTexConverter.php`,
  `php -l lanes/pandoc/tests/MathTexConverterTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - Result: no syntax errors detected.
- JSON status validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path . " ok\n"; }'`
  - Result: both lane JSON files decoded successfully.
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: passed with no whitespace errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP
`MathTexConverter` row splitting, table rendering, equation metadata,
`AstNode`, `MarkdownReader`, `LatexWriter`, `WordPressBlockWriter`, the
WordPress Math/TeX handoff example, and lane-local manifest/status tracking.

No Pandoc, Cabal solver/build/test command, Haskell runner, `texmath`,
MathJax, KaTeX, TeX/PDF engine, browser renderer, online sanitizer, online
service, live provider test, or live-service provider test was executed.

## Status Delta

- `phpPass`: `1304` -> `1305`.
- `benchmarkDenominator.mapped`: `1718` -> `1719`.
- `mathTexConversionCoreCases`: `13` -> `14`.
- `mappedMathTexConversionCoreCases`: `13` -> `14`.
- `mathTexConversionCoreAssertions`: `72` -> `81`.
- Focused `MathTexConverterTest.php`: `59` -> `60` PASS cases and `466` ->
  `475` assertions.

## Non-Overlap

This does not repeat accepted Math/TeX roots, fractions, generalized
fractions, infix fractions, scripts, source annotations, delimiters, sized
delimiter mechanics, `\middle`, operators, functions, explicit limits,
relation/set/logic commands, negated relation overlays, accents, extensible
arrows, macro expansion, matrix/cases/subarray conversion, AMS align/gather/
split row environments, alignedat, flalign, multline, equation wrappers, row
tags, equation references, automatic numbering, prime notation, text-mode
aliases, color/phantom/cancel/layout boxes, math alphabet variants, spacing,
array column lines, `\hline`, `\cline`, paragraph-width array columns, or
repeated array preambles.

This does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff,
OPC, archive compression, citations, YAML, doctemplates, tables, legacy
DOC/CFB, XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
upstream-runner dependency closure.

## Follow-Up

Keep full `texmath` parity, renderer validation, broader equation numbering
semantics, decimal array alignment, pre/post array column hooks, package macro
expansion, MathML intent refinements, and upstream Pandoc runner dependency
planning as separate bounded slices.
