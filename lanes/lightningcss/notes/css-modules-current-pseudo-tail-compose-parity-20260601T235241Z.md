# LightningCSS CSS Modules Current Pseudo Tail Parity

Slice: `lightningcss-wrap-up-css-modules-selector-composes-current-base-20260601T2353Z`

Source truth:
- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`.
- Direct upstream Node NAPI oracle used `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Upstream rejects non-user-action pseudo classes after most pseudo-elements. Examples: `.card::before:current`, `.card::before:target-current`, `.card::before:past`, `.card::before:not(:current)`, and `.card::picker(select):open` throw `Invalid pseudo class after pseudo element, only user action pseudo classes (e.g. :hover, :active) are allowed`.
- Upstream preserves `::part()` as the exception for bare pseudo-class tails. Example: `.card::part(icon):current` serializes as `.EgL3uq_card::part(icon):current{color:red}`.

Pre-change probe:
- The PHP port accepted `.card::before:current` and `.card::before:target-current` selectors and continued producing CSS Modules exports for adjacent `composes` rules.
- The PHP port also accepted `::picker(select):open` in the terminal pseudo smoke even though upstream rejects that tail.

Implementation:
- `CssModulesTransformer` now carries pseudo-element tail mode metadata from pseudo-element detection into tail validation and selector-function rewriting.
- Most pseudo-elements now allow only user-action bare pseudo-class tails (`:hover`, `:active`, `:focus`, `:focus-visible`, `:focus-within`) plus selector-list functions whose surviving arguments can follow pseudo-elements.
- `::part()` keeps upstream's looser bare pseudo-class tail behavior, so `::part(icon):current` remains valid while local class selector arguments inside `:is()`/`:where()` are still filtered.
- The WordPress terminal-pseudos example was rebased to reject `::picker(select):open`, `::before:current`, and `::before:target-current` while preserving `::part(media):current`.

Verification:
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-terminal-pseudos.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 701 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-terminal-pseudos.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `14 test files, 9169 assertions, 0 failures`.

Status delta:
- Full LightningCSS lane evidence moved from recorded `9164` to `9169` assertions with `0` failures.
- `lanes/lightningcss/lane-status.json` `phpPass` updated to `9169`; conservative mapped denominator remains `2439 / 3532` because this deepens an already represented CSS Modules selector/composes cluster.

Dependency closure:
- No new support component is needed. This slice reuses the native PHP selector scanner, CSS escape handling, CSS Modules export composer, and existing WordPress example harness.

Non-overlap:
- This is a bounded CSS Modules pseudo-element tail parity cluster for `:current`/`:target-current`-style selectors with adjacent `composes` exports.
- It does not repeat accepted `:has(:scope)` CSS Modules elision, escaped/local global selector parsing, terminal selector-boundary checks, selector-function filtering after pseudo-elements, SourceMap offset work, bundle/import graph work, CSSOM read/write, custom at-rule visitors, target-prefixing, media-query parity, parser recovery, or property/value parity.
- Root harness was not run; this isolated micro-slice used lane-focused verification only.
