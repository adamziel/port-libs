# Pandoc doctemplates core current-base 2026-06-06T00:29:14Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` braced directive scanning with
  upstream doctemplates separator parsing.
- Braced directives now keep `}` characters that appear inside bracketed
  separators until the separator's closing `]`, so variables and applied
  partials like `${ sources[}] }` and `${ rows:row()[}] }` render the brace as
  separator text instead of ending the `${...}` directive early.
- Updated the WordPress doctemplate review-packet smoke with a compact
  brace-separated source list through the native resource-map renderer.

## Source Truth

- Upstream `Text.DocTemplates.Parser.pSep` parses separators as bracketed text
  ending at `]`, which means `}` can be separator content inside `${...}`:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `pInterpolate` and `pPartial` apply `pSep` to variable
  interpolation and variable-applied partials, matching the two native PHP
  cases covered here.
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
  - Result before edits: `1 test files, 139 assertions, 0 failures`.
- Red-first focused command after adding the braced-separator expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before implementation: `1 test files, 139 assertions, 1 failures`;
    failure was `Unsupported doctemplate pipe uppercase[` because the braced
    directive closed at the `}` separator byte.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 140 assertions, 0 failures`.
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
conditionals, loop scoping, separators that do not contain braces, `$it$`,
`$^$`, automatic multiline nesting, parameter-free pipes, deterministic
map-pairs ordering, parameterized block pipes, Unicode display-width padding,
missing/null pipe handling, resource-map partial discovery, path-style partial
lookup, applied partial variable rebinding, partial recursion guards, braced
pipe quoted-string braces, alpha overflow labels, boolean false output, Unicode
identifier parsing, multiline control boundary newline swallowing, empty
standalone partial line swallowing, `chomp` traversal, breakable-space
rendering/wrapping, dedented nesting termination, final newline stripping for
included partials, extensionless custom-template output-format fallback,
unclosed ordinary-dollar diagnostics, default-template lookup, default-template
metadata/title block/TOC expansion, default HTML style partial resources,
unclosed `$~$` breakable-space rejection, or default HTML5 void tag
serialization.

It only changes `${...}` closing detection when a `}` byte appears inside a
balanced bracketed separator. It does not touch ZIP/OPC package primitives,
YAML metadata, Citation/CSL, BibTeX/CSL, Markdown/HTML readers,
Markdown/WordPress writers, DOCX/ODT/EPUB or legacy-DOC parsing, table
geometry, math conversion, PDF handoff planning, archive compression, syntax
highlighting, XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency
audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, in-memory resource map, and WordPress doctemplate
review-packet example. Fuller upstream default-template data files and
partials, filesystem/HTTP-backed template discovery, richer source-location
diagnostics, full doclayout value modeling, and full upstream Pandoc runner
parity remain separate bounded slices.

Root harness: not run - isolated micro-slice.
