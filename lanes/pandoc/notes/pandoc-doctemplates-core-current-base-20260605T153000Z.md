# Pandoc doctemplates core current-base 2026-06-05T15:30:00Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` loop and applied-partial rebinding for
  piped missing variable paths with upstream doctemplates.
- Piped missing lookups such as `missing/length` can still produce an iterable
  value and expose it through `it`, but the renderer no longer fabricates the
  absent `missing` path in the loop or applied-partial context.
- Existing paths still rebind normally after pipes, so `nullish/length` exposes
  both `it` and `nullish` as the derived length value.
- Updated the WordPress doctemplate review-packet smoke with a derived missing
  warning count audit line that proves absent reviewer metadata stays absent.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents loops, partial application, and
  pipes as Pandoc's template system:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Internal.withVariable` always inserts `it`, but
  updates the named variable path through `Data.Map.adjust`, which only changes
  an existing path and does not create a missing one:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- No Pandoc binary, Cabal build, Haskell runner, external template engine,
  browser renderer, JavaScript, online sanitizer, online conversion service, or
  live service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 69 assertions, 0 failures`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 70 assertions, 0 failures`.
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

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, normal loop iteration rebinding, separators, `$it$`, `$^$`,
automatic multiline nesting, parameter-free pipes, deterministic map-pairs
ordering, parameterized pipes, Unicode display-width padding, missing/null pipe
rendering, resource-map partial discovery, path-style partial lookup, applied
partial variable rebinding for existing paths, partial recursion guards,
braced directive tokenizer behavior, alpha overflow labels, boolean false
output, Unicode identifier parsing, multiline control boundary newline
swallowing, empty standalone partial line swallowing, `chomp` traversal,
breakable-space wrapping, dedented nesting termination, final newline stripping
for included partials, or extensionless output-format resource fallback.

It only changes explicit-loop and applied-partial context rebinding when a pipe
produces an iterable value from a missing variable path.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DocTemplate` renderer and the accepted WordPress doctemplate review-packet
example. Full doclayout `Doc` value modeling, richer source-location
diagnostics, filesystem-backed template discovery beyond the existing resource
map, default-template data-file parity, and full upstream Pandoc runner parity
remain separate activation slices.

Root harness: not run - isolated micro-slice.
