# XML/HTML5 DOM Editorial Link Relations

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T230707Z`
Base: `ca789a5b6d3e33e7c4378e92f189c08d2e32e040`

## Behavior Added

`Html5DomFragment` now treats safe `author`, `license`, `help`, and `bookmark` HTML `<link>` relations as passive review metadata. The sanitizer converts them into inert reviewer anchors using trusted `<base>` URL resolution before WordPress raw HTML handoff.

Mixed active-resource relations such as `author preload`, unsafe targets such as control-separated `javascript:` URLs, and their titles remain hidden. Safe reviewer attributes such as `title`, `type`, and `hreflang` continue to be preserved only on generated inert anchors.

## Source Truth And Scope

This continues the lane-local XML/HTML5 DOM support contract for passive link metadata. The accepted passive-link slice already covered `canonical`, `alternate`, and `shortlink`; this slice maps the adjacent editorial/review relation family that remained out of that narrower case.

The manifest upstream checkout path is not present in this isolated worktree, so source truth for this bounded support-library patch is the current lane manifest, accepted notes, existing sanitizer tests, and WordPress handoff behavior. No Pandoc runner, Cabal build/test command, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Evidence

- Rework notes checked: no `port-pandoc-*.needs-lane-rework.md` note was present for this lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 795 assertions, 0 failures`.
- Red-first: the same focused command failed with `1 test files, 796 assertions, 1 failures` because editorial passive `<link>` declarations serialized as only `<p>after</p>`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 814 assertions, 0 failures`.
- XML/HTML DOM family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 1087 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- Assertion delta: `+19` focused assertions and `+1` PHP PASS case.
- Manifest delta: mapped denominator `1823 -> 1824`; XML/HTML5 DOM core cases `6 -> 7`; XML/HTML5 DOM core assertions `89 -> 108`.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php` - no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php` - no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php` - no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` - `1 test files, 814 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` - `3 test files, 1087 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` - `html5 dom fragment handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "UPSTREAM_TEST_MANIFEST.json ok\n";'` - `UPSTREAM_TEST_MANIFEST.json ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json ok\n";'` - `lane-status.json ok`.
- `git diff --check -- lanes/pandoc` - passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `Html5DomFragment` relation normalization, trusted base URL resolution, `AstNode` raw HTML handoff, `WordPressBlockWriter`, and the existing WordPress HTML5 DOM fragment smoke.

Remaining follow-up stays separate: full HTML5 tree-builder parity, browser sanitizer parity, CSS/media resource loading, external XML/HTML tools, online services, full Pandoc runner parity, live provider tests, and live-service provider tests.
