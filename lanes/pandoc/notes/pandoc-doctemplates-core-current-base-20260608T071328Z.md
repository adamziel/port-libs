# Pandoc doctemplates core current-base legacy HTML slide defaults

Slice: `pandoc-doctemplates-core-current-base-20260608T071328Z`
Base accepted HEAD: `46202efa14a54e48d6402cf95aed247ffe0ec061`
Lane: `pandoc`

## Source Truth

No rework note was present for `port-pandoc-*.needs-lane-rework.md`.

The local upstream Pandoc cache was not present at `/home/claude/port-libs/.upstream-cache/pandoc`, so the bounded template shapes were checked against pinned upstream raw files at `jgm/pandoc` commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`:

- `data/templates/default.s5`: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.s5
- `data/templates/default.slidy`: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.slidy
- `data/templates/default.slideous`: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.slideous
- `data/templates/default.dzslides`: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.dzslides

No Pandoc, Haskell test binary, browser, office suite, TeX/PDF engine, external template engine, or online conversion service was run.

## Implementation

`DocTemplate` now resolves bounded native default resources for the legacy HTML slide writer formats `s5`, `slidy`, `slideous`, and `dzslides`. The resolver maps `templates/default` plus the requested format extension to the correct default template, and `defaultTemplateResourceBasenames()` exposes the new basenames so `${ default() }` partial fallback remains extension-sensitive.

The default templates preserve the source-truth contract needed by the PHP renderer: writer-specific doctypes, resource URLs, title/metadata slots, author/date/institute handoff, table-of-contents hooks, body insertion, include-before/after hooks, and format-specific runtime/core markers.

The WordPress review packet smoke now exercises all four legacy HTML slide fallbacks without shelling out to Pandoc or a browser.

## Evidence

Baseline before the new test:

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 649 assertions, 0 failures`

Red-first after adding the missing-resource test and before implementation:

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 649 assertions, 1 failures`, failing on `Missing doctemplate resource templates/default`

Final focused test:

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 689 assertions, 0 failures`

Focused delta: `+1` PHP PASS case and `+40` assertions for this doctemplates slice.

Example smoke:

`php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`

Result: `OK wordpress doctemplate review packet`

Syntax checks:

- `php -l lanes/pandoc/src/DocTemplate.php`: `No syntax errors detected`
- `php -l lanes/pandoc/tests/DocTemplateTest.php`: `No syntax errors detected`
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`: `No syntax errors detected`

Status artifact checks:

- `php -r '$paths = ["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($paths as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); fwrite(STDOUT, $path . " OK\n"); }'`: both files decoded successfully
- `git diff --check -- lanes/pandoc`: passed with no output

## Non-overlap

This slice does not change existing delimiter parsing, comments, loops, partial resolution semantics, revealjs, beamer, html4/html5 defaults, markdown/man/ms/commonmark/html chunk defaults, or the template lexer. It adds only bounded legacy HTML slide default resources and direct tests for those resources.

## Dependency Closure

No new support component is required. The slice reuses the existing native PHP `DocTemplate` resource resolver, partial renderer, and lane example path. Full browser slide runtime behavior, complete upstream template parity, and upstream Haskell runner execution remain out of scope for this bounded support-library slice.

Root harness: not run - isolated micro-slice.
