# Media Query Explicit Condition Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T043634Z`

Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/media_query.rs::MediaQuery::parse` and `src/lib.rs::test_media`. A direct pinned native transform probe confirmed that LightningCSS rejects explicit media type tails such as `all and all`, `screen and not all`, and `screen and (color) and not (hover)`, while accepting the single-tail form `screen and not (color)`.

Implementation:

- `MediaQueryParser::validateExplicitMediaTypeCondition()` now validates operands after an explicit media type.
- A single `not (<condition>)` tail remains valid for `screen and not (color)`.
- Longer explicit media type `and` chains require each top-level operand to be parenthesized, matching upstream grammar.
- Bare media types or qualifiers after `and` now throw instead of being serialized.

Focused behavior:

- `screen and not (color)` remains valid.
- `screen and (not (color)) and (hover)` remains valid.
- `all and all`, `not all and all`, `all and not all`, `screen and print`, `screen and not all`, and `screen and only all` are rejected.
- `screen and (color) and not (hover)` and `screen and not (color) and (hover)` are rejected unless the negated condition is parenthesized as a condition operand.

Evidence:

- Red probe before patch: pinned native LightningCSS rejected `all and all`, `not all and all`, `all and not all`, and `screen and not all`; the PHP minifier accepted or dropped those incorrectly.
- Pinned native LightningCSS accepted `screen and not (color)` and rejected `screen and (color) and not (hover)`, matching the new PHP behavior.
- Post-patch native-vs-PHP explicit media parity probe passed for valid and invalid explicit media tail cases.
- `php -l lanes/lightningcss/src/MediaQueryParser.php` passed.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` passed: 1 file, 423 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test` passed.
- `git diff --check -- lanes/lightningcss` passed.

Status delta:

- Focused PHP assertions increased by 18 in `MediaQueryParserTest.php`.
- Lane `phpPass` should move from 5978 to 5996 if the integrator accepts this slice on the current base.
- Conservative mapped coverage remains 2336 / 3532 because this deepens the already mapped media-query range/layer cluster.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP media query parser, CSS minifier, and transition prefixer paths.

Non-overlap:

- This does not touch the stale custom-media import-tail rework note; current accepted source already includes later custom-media import-tail behavior.
- This avoids source-map, CSS Modules, bundle/import graph, target-prefixing, and property/value clusters.
