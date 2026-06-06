# Pandoc doctemplates core current-base 2026-06-06T06:51:57Z

## Slice

- Added bounded post-delimiter line whitespace handling to
  `PortLibs\Pandoc\DocTemplate`.
- Spaces and tabs immediately after a closing `$...$` or `${...}` delimiter are
  now skipped when that horizontal run reaches a line ending or EOF.
- The tokenizer applies the rule uniformly to ordinary variables, braced
  variables, control directives, breakable-space toggles, and partial calls.
- Inline template spaces remain literal when another non-newline character
  follows the whitespace run, preserving default-template constructs such as
  inline HTML attributes and adjacent padded variables.
- Updated the WordPress doctemplate review-packet example self-test with a
  native review snippet that proves whitespace-only directive lines do not leak
  extra spaces or blank lines.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents Pandoc-style delimiters, including
  ignored spaces/tabs around delimiter syntax and the same variable, control,
  loop, partial, breakable-space, and pipe surface this lane ports:
  https://hackage.haskell.org/package/doctemplates
- The same Hackage README documents line-oriented `for`/`sep` control blocks,
  partial calls, and automatic nesting behavior, which makes whitespace-only
  directive lines a real template-rendering boundary.
- This slice used only the lane-local native PHP renderer. No Pandoc binary,
  Cabal build/solver/test command, Haskell runner, external template engine,
  browser renderer, online sanitizer, online service, or live provider test was
  executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 198 assertions, 0 failures`.
- Red-first focused command after adding the delimiter-line whitespace
  expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before implementation: `1 test files, 199 assertions, 1 failures`.
  - Failure showed spaces/tabs leaked after `$if(...)$`, `$it$`, `$sep$`,
    `${ author.name }`, and `${ badge() }` when each directive was followed only
    by horizontal whitespace and a line ending.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 199 assertions, 0 failures`.
  - Delta: `+1` focused PHP PASS case and `+1` focused assertion.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - Result: no syntax errors.
- Lane JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, normal loop iteration rebinding, separators, `$it$`, `$^$`,
automatic multiline nesting, parameter-free pipes, deterministic map-pairs
ordering, parameterized block pipes, Unicode display-width padding,
missing/null pipe handling, resource-map partial discovery, path-style partial
lookup, applied partial variable rebinding, partial recursion guards, braced
pipe quoted-string braces, braced separator parsing, alpha overflow labels,
boolean false output, Unicode identifier parsing, multiline control boundary
newline swallowing without line-trailing spaces, empty standalone partial line
swallowing, `chomp` traversal, breakable-space rendering/wrapping, dedented
nesting termination, final newline stripping for included partials,
extensionless custom-template output-format fallback, unclosed diagnostic
source locations, built-in default-template fallbacks, default HTML style
partials, default HTML5 void tag serialization, or rooted filesystem discovery.

It only changes tokenizer handling of spaces/tabs that occur after a closing
template delimiter and before a line ending or EOF. It does not touch ZIP/OPC
package primitives, YAML metadata, Citation/CSL, BibTeX/CSL, Markdown/HTML
readers, Markdown/WordPress writers, DOCX/ODT/EPUB package parsing,
legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, focused doctemplate test file, lane-local manifest and
status metadata, and the WordPress doctemplate review-packet example. Fuller
upstream default-template data-file parity, full doclayout value modeling,
parser recovery/context diagnostics beyond bounded line/column positions, and
full upstream Pandoc runner parity remain separate bounded slices.

Root harness: not run - isolated micro-slice.
