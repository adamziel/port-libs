# Pandoc Math/TeX Conversion Core Current Base - Binary/Relation Aliases

Base accepted HEAD: `0864f62253e0e164cd7935b30a381c071acdbd24`

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260608T203706Z`

## Behavior

This slice adds a bounded native TeX math command-table mapping for common binary operator and relation aliases that previously fell through as literal identifiers:

- Binary/operator aliases: `\oplus`, `\ominus`, `\otimes`, `\oslash`, `\odot`, `\bullet`, `\circ`, `\star`, `\diamond`, `\div`, and `\mp`.
- Relation aliases: `\asymp`, `\bowtie`, `\vdash`, `\dashv`, `\smile`, and `\frown`.
- Accessibility labels now name the mapped glyphs for `texToAccessibleMathMl()` alt text and intent output.

The WordPress math handoff example now includes the same operator/relation alias row and self-test guard so the aliases stay visible in reviewer source spans while MathML uses semantic `<mo>` nodes.

## Evidence

No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.

Red-first evidence:

- Baseline before the new test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 850 assertions, 0 failures`.
- After adding the focused alias test but before implementation, the same command failed with `1 test files, 852 assertions, 1 failures`; the failure showed `\oplus` rendering as `<mi>\oplus</mi>`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 864 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.
- `php -l lanes/pandoc/src/MathTexConverter.php` passed.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` passed.
- JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1820 -> 1821`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2244 -> 2245`.
- `mathTexConversionCoreCases`: `14 -> 15`.
- `mappedMathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 99`.

## Dependency Closure

No new support component is needed. This reuses the existing native `MathTexConverter` command-table parser, MathML source annotations, `MarkdownReader` math spans, and `WordPressBlockWriter` output. No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat recent Math/TeX slices for bangle infix fractions, modular commands, TeX comments, array width columns, multline/multlined environments, alignedat, or large operator aliases. Follow-up can target non-overlapping delimiter variants, additional relation/operator aliases not listed above, or renderer-neutral accessibility metadata.
