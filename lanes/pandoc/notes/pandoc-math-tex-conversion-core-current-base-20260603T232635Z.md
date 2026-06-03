# Pandoc Math/TeX Conversion Core Slice

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260603T232635Z`

Base accepted HEAD: `bb492a8177b461eb64d4f0b8495d9b7b155c59df`

## Scope

- Added `MathTexConverter`, a bounded native PHP support component for math
  handoff without invoking Pandoc, TeX engines, MathJax, KaTeX, online
  services, or external binaries.
- Implemented deterministic MathML fragments for a focused TeX subset used by
  existing lane math evidence: fractions, square roots, subscript/superscript,
  common Greek identifiers, common operators, and `\text{...}` groups.
- Wired `LatexWriter` to preserve `math` inline nodes as TeX math delimiters
  and `raw_tex` inline/block nodes as source TeX instead of dropping them.
- Added a WordPress-relevant example, `examples/wordpress-math-tex-handoff.php`,
  that preserves imported formula spans, emits LaTeX handoff output, and emits
  a bounded MathML handoff fragment.

## Source Truth

- This builds on the already mapped Pandoc Markdown math evidence in
  `test/testsuite.txt`, `test/testsuite.native`, and
  `test/markdown-reader-more.txt`: inline dollar math, display dollar math,
  raw TeX commands/blocks, macro-expanded math, and non-math dollar guards.
- The slice ports the bounded format contract for math handoff. It does not
  attempt full `texmath` parity or execute a TeX/MathML renderer.

## Verification

- `php -l lanes/pandoc/src/MathTexConverter.php` passed.
- `php -l lanes/pandoc/src/LatexWriter.php` passed.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed: 1 file, 10 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed: 1 file, 2375 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 7 files, 2821
  assertions, 0 failures.

## Dependency Closure

No new external support dependency is needed. The new support component is
native PHP and bounded by tests. Full upstream Pandoc runner parity remains a
separate Cabal/upstream-checkout blocker recorded in lane status.

## Follow-Up

- Broaden TeX parsing only in explicit follow-up slices: arrays/alignment,
  accents, matrices, more relation/operator commands, and MathML accessibility
  metadata.
- Keep DOCX OMML math extraction as a separate DOCX/OpenXML gate.
