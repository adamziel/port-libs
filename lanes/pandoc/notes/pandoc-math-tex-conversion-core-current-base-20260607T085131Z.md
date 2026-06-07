# Pandoc Math/TeX Conversion Core - Math Alphabet Aliases

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260607T085131Z`
Accepted base: `bed5eb0577e7b3da6f9d9150fbc09175dc986376`

## Source Truth

- Upstream behavior target: texmath TeX reader command-table handling for math alphabet/style aliases, including common unicode-math aliases for bold, upright, double-struck, bold italic, bold sans-serif, bold script, bold fraktur, and sans-serif italic output.
- Primary upstream source reference: `https://github.com/jgm/texmath/blob/master/src/Text/TeXMath/Readers/TeX.hs`.
- No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Red-First Evidence

Baseline focused test before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 588 assertions, 0 failures
```

Manual pre-change probes showed the aliases rendered as literal identifiers, for example `<mi>\bm</mi>`, `<mi>\mathbfit</mi>`, and `<mi>\mathbfsfup</mi>`, instead of semantic MathML variant output.

## Implementation

- `MathTexConverter` now recognizes bounded texmath/unicode-math aliases:
  `\mathup`, `\symbf`, `\bm`, `\pmb`, `\mathbold`, `\mathbfup`, `\mathds`, `\mathbfit`, `\mathbfsfup`, `\mathbfsfit`, `\mathbfscr`, `\mathbfcal`, `\mathbffrak`, and `\mathsfit`.
- The converter maps supported ASCII letters and digits to stable MathML math alphanumeric Unicode codepoints for the newly covered variants.
- Source-TeX annotations are preserved so WordPress review packets can show the original TeX while the MathML handoff remains semantic.
- The WordPress math TeX handoff example now includes a self-test guard for the alias output and verifies representative MathML fragments.

## Focused Verification

Post-change focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 603 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

The slice adds `+1` focused PHP PASS case and `+15` focused assertions. The manifest mapped denominator moves from `1896` to `1897`; Math/TeX conversion core cases move from `14` to `15`; Math/TeX conversion core assertions move from `85` to `100`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `MathTexConverter` tokenization, mathvariant Unicode rewriting, source-TeX annotations, and the existing WordPress math handoff example.

Full upstream Pandoc/texmath runner parity remains blocked by the missing hydrated Pandoc checkout plus an explicitly authorized non-mutating Cabal solver/build plan, not by this math-alphabet primitive.

## Non-Overlap And Follow-Up

This does not repeat the already accepted math slices for `alignedat`, `multline`, `array` width columns, `\bangle`, modular commands, or raw TeX comments. It is scoped only to bounded style-command aliases and their MathML/WordPress handoff.

Follow-up Math/TeX work should stay bounded to remaining non-overlapping texmath reader gaps such as bracket/accent/operator aliases, guarded `\mathchoice`-style branching, or additional MathML handoff metadata with focused PHP tests.
