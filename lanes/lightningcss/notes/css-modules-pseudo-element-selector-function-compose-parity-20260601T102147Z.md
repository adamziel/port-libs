# LightningCSS CSS Modules Pseudo-Element Selector Function Parity

Slice: `lightningcss-css-modules-local-global-compose-parity-20260601T102147Z`

Source truth:
- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Direct upstream Node NAPI oracle used `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Upstream drops selector-list arguments that cannot follow a pseudo-element for forgiving selector functions after pseudo-elements. Examples: `.card::before:has(:hover, .child, :global(.legacy))` serializes with only `:hover`, `.card::part(icon):is(.active)` serializes as `:is()`, chained tails such as `.card::before:is(.x):hover` keep `:hover`, and no dropped local class is exported.
- Upstream rejects strict negation arguments that cannot follow a pseudo-element. Example: `.card::before:not(.child)` throws `CSS pseudo-elements cannot be followed by selectors`.

Pre-change probe:
- The PHP port previously scoped and exported local classes inside `:has()`, `:is()`, and `:where()` after terminal pseudo-elements.
- The PHP port also accepted `.card::before:not(.child)`, which differs from upstream strict `:not()` behavior after pseudo-elements.

Implementation:
- `CssModulesTransformer` now tracks when selector rewriting has crossed a pseudo-element at top level.
- Forgiving selector functions after a pseudo-element filter invalid local/global/class selector arguments without collecting CSS Modules local exports.
- Strict `:not()` after a pseudo-element preserves pseudo-class-only tails and rejects local/global/class selector arguments with the existing pseudo-element selector diagnostic.
- `CssModulesTransformerTest` covers `::before:has()`, `::selection:where()`, `::part():is()`, `::cue():has()`, chained pseudo-class tails after filtered selector functions, local composes export preservation, valid pseudo-class-only `:not()`, and invalid local/global strict `:not()` cases.
- The WordPress terminal-pseudos smoke now covers block pseudo-element/part selector-function filtering and the strict negation rejection path.

Verification:
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-terminal-pseudos.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 531 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-terminal-pseudos.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7376 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

Status delta:
- Full LightningCSS lane evidence moved from recorded `7365` to `7376` assertions with `0` failures.
- `lanes/lightningcss/lane-status.json` `phpPass` updated to `7376`; conservative mapped denominator remains `2365 / 3532` because this deepens an already represented CSS Modules local/global/composes cluster.

Dependency closure:
- No new support component is needed. This slice reuses the native PHP selector scanner, CSS escape handling, CSS Modules export composer, and lane example harness.

Non-overlap:
- This is a bounded CSS Modules local/global/composes parity cluster for selector functions after pseudo-elements.
- It does not repeat accepted escaped local/global delimiters, namespace delimiters, escaped comments/newlines, terminal pseudo classification, `::host`/`::slotted`/`::cue` scoping, nested composes rejection, source-map, bundle/import graph, CSSOM read/write, media-query, target-prefixing, or custom at-rule visitor surfaces.
- Root harness was not run; this isolated micro-slice used lane-focused verification only.
