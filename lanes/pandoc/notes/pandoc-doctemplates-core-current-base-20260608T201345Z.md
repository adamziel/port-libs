# Pandoc Doctemplates Core Current Base - 2026-06-08T201345Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-doctemplates-core-current-base-20260608T201345Z`
- Base accepted HEAD: `94d7cef270e305ef6fc0f67053ec55d96bb371c3`
- Behavior cluster: bounded doctemplate braced directives with separators whose payload contains a literal opening bracket.

## Source Truth

- Upstream doctemplates parser source: `Text.DocTemplates.Parser` `pSep` in doctemplates 0.11.0.1 parses separator payloads from `[` through the first `]`, so literal `[` and `}` are payload characters rather than nested bracket structure.
- Source link: https://hackage.haskell.org/package/doctemplates-0.11.0.1/docs/src/Text.DocTemplates.Parser.html

## Implementation

- `DocTemplate::findBracedDirectiveClosing()` now skips bracketed separator payloads to the first closing bracket while scanning `${...}` directives.
- This preserves already accepted closing-brace separator behavior and lets native PHP rendering handle braced variables such as `${ sources[[ ] }`, piped variables, and applied partials without shelling out to Pandoc or an external template engine.
- The WordPress doctemplate review-packet example now includes the same bracket separator path.

## Evidence

- Rework-note check: `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20` returned no files.
- Baseline focused suite before this test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 898 assertions, 0 failures`.
- Red-first after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with `1 test files, 898 assertions, 1 failures` on `Unclosed doctemplate ${...} directive at <template>:1:10`.
- Final focused suite after implementation: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 899 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed.
- PHP lint: `php -l lanes/pandoc/src/DocTemplate.php`, `php -l lanes/pandoc/tests/DocTemplateTest.php`, and `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php` all passed.
- Status validation: `php -r '$files = ["lanes/pandoc/lane-status.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'` passed.
- Whitespace check: `git diff --check -- lanes/pandoc` passed.

## Dependency Closure

- No new support component is needed. This reuses the native PHP `DocTemplate` parser/rendering path, existing focused doctemplate tests, and the existing WordPress review-packet example.
- Exclusions: no Pandoc command, Cabal solver/build/test, Haskell runner, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Next

- A non-overlapping doctemplate follow-up could cover remaining upstream separator edge diagnostics or writer-specific default-template metadata. Avoid repeating this bracket-separator scanner behavior.
