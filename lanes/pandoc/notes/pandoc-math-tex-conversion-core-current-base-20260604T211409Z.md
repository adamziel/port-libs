# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260604T211409Z`

Base accepted HEAD: `1480bbab70b54431a9debcd67786a4a112caa532`

## Behavior Added

- Extended `MathTexConverter` so bounded MathML handoff wraps presentation
  MathML in a `semantics` element.
- Added escaped `annotation encoding="application/x-tex"` payloads carrying the
  source TeX string for inline and display math handoff.
- Normalizes multi-token presentation output into a single `mrow` inside
  `semantics`, while leaving single presentation nodes such as `mfrac` intact.
- Updated the WordPress math handoff smoke so reviewer/import tooling verifies
  source-TeX annotations alongside existing editable TeX spans, LaTeX writer
  output, and bounded MathML.

## Source Truth

The accepted Pandoc inventory already maps Markdown math evidence from
`test/testsuite.txt`, `test/testsuite.native`, `test/markdown-reader-more.txt`,
and `test/markdown-reader-more.native`: Pandoc preserves TeX source in math
nodes. This slice ports the bounded support-library contract for carrying that
source through MathML handoff. It does not attempt full `texmath` parity,
MathJax, KaTeX, TeX rendering, Pandoc execution, or a TeX/PDF engine.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 51 assertions, 0 failures`
- `php -l lanes/pandoc/src/MathTexConverter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 54 assertions, 0 failures`
  - Delta: `+3` focused assertions and `+1` focused PASS line.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both JSON files parsed successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

## Non-Overlap

- Does not repeat accepted Math/TeX fraction/root/script/text, delimiter/fence,
  large-operator/function/operator-name, relation/set/logic/arrow, accent, or
  matrix/aligned environment conversion.
- Does not touch DOCX OMML, ODT formulas, PDF engine handoff, OPC, archive
  compression, citations, YAML, doctemplates, tables, legacy DOC/CFB, or
  upstream-runner dependency closure.

## Follow-Up

Keep richer MathML intent annotations, deeper TeX parsing, nested environment
parity, DOCX OMML extraction, and full upstream runner dependency planning as
separate bounded slices.
