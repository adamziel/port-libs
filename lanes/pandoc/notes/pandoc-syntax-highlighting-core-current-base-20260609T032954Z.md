# Pandoc syntax highlighting current-base Groovy handoff

Slice: `pandoc-syntax-highlighting-core-current-base-20260609T032954Z`
Base accepted HEAD: `507b06f9840603abbb77bf4b360c0377f959830e`

## Scope

Implemented one bounded syntax-highlighting support cluster for Groovy review packets:

- `groovy`, `gvy`, `gradle`, `gradle-groovy`, `groovy-script`, `groovy-source`, and `Jenkinsfile` aliases now normalize to `groovy`.
- `SyntaxHighlighter` now emits token spans for Groovy comments, imports, annotations, datatypes, string forms, numeric literals, safe-navigation and Elvis operators, named map attributes, Jenkins/Gradle DSL calls, and function calls.
- The WordPress syntax-highlighting fixture and example smoke now cover a `.gradle` code block with Jenkins pipeline-style review commands and WordPress CLI output.

Source-truth note: this is mapped from Skylighting's Groovy language definition shape, including language name `Groovy` and extensions `*.groovy;*.gradle;*.gvy;Jenkinsfile`, plus its keyword/type/comment/string/function/operator token classes. I inspected the upstream raw XML at `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/groovy.xml`; no Pandoc, Skylighting, Haskell, or external converter runner was executed.

## Baseline and Non-overlap

- Baseline focused test before this patch: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2434 assertions, 0 failures`.
- Baseline source scan: `git show HEAD:lanes/pandoc/src/SyntaxHighlighter.php | rg -n "groovy|gradle|jenkinsfile|Jenkinsfile"` returned no matches.
- This avoids prior accepted syntax clusters for Pascal/Delphi, Common Lisp, D, Fortran, Tcl, Protobuf, Meson/Just, Fennel, Raku, Objective-C, Erlang, CSV/TSV, Scheme/Racket, Vimscript, BibTeX, shell, and other already-fixtured aliases.

## Verification

- `php -l lanes/pandoc/src/SyntaxHighlighter.php` - no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php` - no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php` - no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` - `json ok`.
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` - `syntax highlighting handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` - `1 test files, 2470 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `SyntaxHighlighter`, `MarkdownReader` code-block attributes, `WordPressBlockWriter` handoff, the existing syntax fixture, and the local example smoke. Full upstream Pandoc/Skylighting runner parity remains an upstream-runner dependency task requiring a hydrated pinned checkout and Haskell test executables.

## Follow-up

Next syntax-highlighting work should choose a non-overlapping fixture-backed alias/token cluster such as shell-session transcripts, remaining language edge states, or another Skylighting alias family that is not already covered.
