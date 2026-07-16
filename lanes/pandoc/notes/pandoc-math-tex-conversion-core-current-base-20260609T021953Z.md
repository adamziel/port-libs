# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T021953Z`

Base accepted HEAD: `a3acdbf651a3d75d5d84e3bea3aaa5d49ff7e5c6`

## Behavior Added

- Added bounded native Math/TeX command-table support for texmath-style variant
  Greek aliases: `\varGamma`, `\varDelta`, `\varTheta`, `\varLambda`,
  `\varXi`, `\varPi`, `\varSigma`, `\varUpsilon`, `\varPhi`, `\varPsi`,
  `\varOmega`, `\varrho`, `\varsigma`, and `\upUpsilon`.
- Added bounded `\overbar` and `\underbar` accent aliases that reuse the
  existing native mover/munder MathML path; `\underbar` uses texmath's
  combining low-line marker while `\underline` stays on the existing underscore
  path.
- Extended accessibility text and intent labels for the newly mapped variant
  Greek glyphs and the underbar marker.
- Updated the WordPress Math/TeX handoff example so variant aliases stay
  semantic in generated MathML instead of surfacing as literal fallback
  command identifiers.

## Source Truth And Scope

Source truth is the lane's accepted Pandoc-like math contract plus the bounded
texmath command-table alias shape: preserve source TeX annotations, convert
safe command aliases to native MathML, keep accessibility metadata available,
and avoid external math renderers.

This slice only maps the variant Greek and overbar/underbar alias cluster. It
does not attempt full texmath parity, package macro loading, renderer
validation, TeX engine execution, or broader equation-numbering behavior.

## Verification

- Rework notes:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort`
  - Result: no current pandoc rework notes.
- Baseline focused check:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 1103 assertions, 0 failures`.
- Red-first direct conversion:
  `php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); foreach (["\\varDelta + \\varGamma + \\varrho + \\varsigma", "\\overbar{x} + \\underbar{y} + \\square"] as $tex) { echo $tex, "\n", $c->texToMathMl($tex), "\n\n"; }'`
  - Result: variant Greek aliases plus `\overbar` and `\underbar` appeared as
    literal fallback identifiers before the implementation; `\square` was
    already mapped.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 1120 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+17` focused assertions.
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
`MathTexConverter` command tables, accent argument parsing, source-TeX
semantics annotations, accessibility annotation generation, the focused
Math/TeX test file, lane-local manifest/status tracking, and the WordPress
Math/TeX handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, `texmath`,
MathJax, KaTeX, TeX/PDF engine, browser renderer, online sanitizer, online
service, live provider test, or live-service provider test was executed.

## Status Delta

- `phpPass`: `2130` -> `2131`.
- `benchmarkDenominator.mapped`: `2557` -> `2558`.
- `mathTexConversionCoreCases`: `14` -> `15`.
- `mappedMathTexConversionCoreCases`: `14` -> `15`.
- `mathTexConversionCoreAssertions`: `85` -> `102`.
- Focused `MathTexConverterTest.php`: `1103` -> `1120` assertions.

## Non-Overlap

This does not repeat accepted Math/TeX roots, fractions, generalized
fractions, infix fractions, scripts, source annotations, delimiters, sized
delimiter mechanics, `\middle`, operators, functions, explicit limits,
relation/set/logic commands, negated relation overlays, existing accent
commands, extensible arrows, macro expansion, matrix/cases/subarray
conversion, AMS align/gather/split row environments, alignedat, flalign,
multline, equation wrappers, row tags, equation references, automatic
numbering, prime notation, text-mode aliases, color/phantom/cancel/layout
boxes, math alphabet variants, spacing, array column lines, `\hline`,
`\cline`, paragraph-width array columns, repeated array preambles, or legacy
`eqnarray` conversion.

This does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff,
OPC, archive compression, citations, YAML, doctemplates, tables, legacy
DOC/CFB, XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
upstream-runner dependency closure.

## Follow-Up

Keep full texmath parity, renderer validation, package macro expansion,
broader MathML intent refinements, and upstream Pandoc runner dependency
planning as separate bounded slices.
