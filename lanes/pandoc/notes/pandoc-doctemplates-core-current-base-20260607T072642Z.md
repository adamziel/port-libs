# Pandoc doctemplates core current-base 2026-06-07T07:26:42Z

## Slice

- Added bounded native support for Pandoc data-file partial fallback by
  basename when rendering template resources.
- A custom resource template can now reference nested built-in partial paths
  such as `components/styles.html()` or `fragments/default.plain()` and still
  fall back to the bundled `styles.html` or `default.plain` resource when no
  local partial exists.
- Caller-supplied local partial resources keep precedence, and direct
  `render()` calls with no resource map still report missing partials.
- Updated the WordPress doctemplate review-packet self-test with the nested
  default partial fallback path.

## Source Truth

- Pinned Pandoc `Text.Pandoc.Templates` falls back missing partial/template
  fetches to Pandoc data files by `takeFileName`:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
- Upstream doctemplates parses bare partials and applied partials as template
  partial references with optional separators and pipes:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Hackage doctemplates documents Pandoc-style partials, pipes, loops, and
  breakable-space rendering:
  https://hackage.haskell.org/package/doctemplates

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
template engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 372 assertions, 0 failures`.
- Red-first local probe before implementation:
  `renderResource("templates/review.html", ["templates/review.html" => "<style>\n$components/styles.html()$\n</style>"], ...)`
  - Result: `UnexpectedValueException: Missing doctemplate partial components/styles.html at templates/review.html:2:1`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 379 assertions, 0 failures`.
  - Delta: `+1` focused PHP PASS case and `+7` focused assertions.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - Result: no syntax errors.
- Lane manifest/status JSON validation passed.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loops, separators, `$it$`, `$^$`, automatic multiline nesting,
parameter-free or parameterized pipes, Unicode display-width padding, missing
and null pipe handling, deterministic map-pairs ordering, path-style partial
lookup, applied-partial rebinding, partial recursion guards, braced pipe
arguments, braced separators, alpha overflow labels, boolean false output,
Unicode identifier parsing, multiline control newline swallowing, empty
standalone partial line swallowing, `chomp`, breakable-space rendering/wrapping,
dedented nesting termination, final newline stripping for included partials,
source-location diagnostics, extensionless custom-template format fallback,
filesystem resource loading, user-data partial lookup, or bounded default
HTML5/Markdown/CommonMark/plain/LaTeX/Beamer/man/ms/OpenXML/OpenDocument/EPUB3/
Typst template resources.

It owns only the missing nested built-in partial fallback-by-basename behavior
for resource-rendered templates plus the matching WordPress review-packet smoke.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
renderer, built-in default template/partial resources, in-memory resource
fixtures, focused lane test harness, and WordPress doctemplate review-packet
example.

Full upstream Pandoc/Haskell runner parity, external template engines,
browser rendering, filesystem/HTTP remote template fetching beyond bounded
local resource maps, and complete default-template parity for every writer
remain out of scope for this isolated micro-slice.

Root harness: not run - isolated micro-slice.
