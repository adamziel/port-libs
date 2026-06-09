# Pandoc syntax highlighting current-base sed text-payload handoff

Slice: `pandoc-syntax-highlighting-core-current-base-20260609T040657Z`
Base accepted HEAD: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`

## Scope

Implemented one bounded syntax-highlighting support cluster for sed text payloads:

- `SyntaxHighlighter` now tokenizes sed line-by-line so `a\`, `c\`, and `i\` commands enter a literal text-payload state.
- WordPress block comment payloads inserted by sed scripts are preserved as string/source text instead of being retokenized as sed operators, variables, or commands.
- Existing sed address, substitution, branch, label, and flag token handoff remains intact for the following command lines.
- The WordPress syntax-highlighting example self-test now asserts the inserted block payload line.

Source-truth note: this maps the bounded shape of upstream Skylighting's `sed.xml`, where `FindCommand` routes `aic` commands to `AICCommand`, and `AICCommand` falls through or line-continues into `LiteralText`. I statically inspected `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/sed.xml`; no Pandoc, Skylighting, sed, Haskell, or external converter runner was executed.

## Baseline and Non-overlap

- Baseline focused test before this patch: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2470 assertions, 0 failures`.
- This avoids prior accepted syntax clusters for Groovy/Gradle/Jenkins, Fish, Pascal/Delphi, Common Lisp, D, Fortran, Tcl, Protobuf, Meson/Just, Fennel, Raku, Objective-C, Erlang, CSV/TSV, Scheme/Racket, Vimscript, BibTeX, shell, custom theme metadata, token-title metadata, line-highlight metadata, and unsupported-language fallback behavior.
- This slice intentionally does not retokenize the pre-existing sed command/flag ambiguity where a bare `p` command is currently surfaced with the bounded flag token class. That is recorded as a follow-up edge.

## Verification

- `php -l lanes/pandoc/src/SyntaxHighlighter.php` - no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php` - no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php` - no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'` - `pandoc json ok`.
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` - `syntax highlighting handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` - `1 test files, 2487 assertions, 0 failures`.
- `git diff --check -- lanes/pandoc` - no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2275` -> `2276`.
- Mapped denominator: `2677` -> `2678`.
- Focused assertion delta: `+17` in `SyntaxHighlighterTest.php`.
- Added `mappedSyntaxHighlightingSedTextPayloadCases: 1` and `syntaxHighlightingSedTextPayloadAssertions: 17`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `SyntaxHighlighter`, `MarkdownReader` code-block attributes, the existing WordPress syntax fixture/example, and focused `SyntaxHighlighterTest.php` coverage. Full upstream Pandoc/Skylighting runner parity remains an upstream-runner dependency task requiring a hydrated pinned checkout and Haskell test executables.

## Follow-up

Next syntax-highlighting work should choose a non-overlapping sed command/flag disambiguation edge, shell-session transcript highlighting, or another uncovered Skylighting alias family with fixture-backed PHP tests.
