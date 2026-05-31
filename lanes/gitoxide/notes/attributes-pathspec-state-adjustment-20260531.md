# Attributes Pathspec State Adjustment Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260531T090251Z`

Base accepted HEAD: `9986ffeeb381ed3e9dc9166d1668e256084ca733`

## Upstream Source Truth

- `gix-attributes/src/parse.rs` parses attribute assignments by splitting at `=` first, then applying `-` and `!` state prefixes. That makes `-attr=value` an unset assignment for `attr`, and `!attr=value` an unspecified assignment for `attr`.
- `gix-attributes/tests/parse/mod.rs` includes `attributes_see_state_adjustments_over_value_assignments`, which asserts `-unset=a` and `!unspecified=b` ignore the value suffix and keep their state adjustment.
- `gix-pathspec/src/parse.rs` parses `:(attr:...)` requirements through the same attribute assignment parser after validating and unescaping pathspec attr values.

## Native PHP Delta

- `GitAttributes::parseAssignment()` now splits an optional value suffix before applying `-` or `!` prefixes.
- Strict pathspec parsing still validates escaped attr value bytes before discarding value text for `-attr=value` and `!attr=value`.
- `AttributesPathspecTest.php` adds focused assertions for `.gitattributes` state adjustments and `:(attr:-diff=legacy !review=stale)` / `:(attr:!deploy=old -merge=ours)` pathspec requirements.
- `wordpress-attributes-pathspec.php` now includes a must-use plugin selected through `:(attr:-diff=legacy)`.

## Verification

- Red-first evidence before the parser fix:
  `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  failed with 2 failures because `-diff=legacy` discarded the attributes line and `:(attr:-diff=legacy)` parsed as an empty/invalid requirement.
- After the fix:
  `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `1 test files, 47 assertions, 0 failures`.
- Full lane before metadata edits:
  `php tools/run-tests.php lanes/gitoxide/tests`
  passed `36 test files, 3280 assertions, 0 failures`.

## Non-Overlap

This extends the accepted `6d9f6eff` attributes/pathspec slice without repeating its existing whitespace, macro, escaped-value, exclude-precedence, or sparse-checkout pathspec coverage. The new behavior is limited to state-adjustment value suffixes shared by `gix-attributes` and `gix-pathspec`.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local `.gitattributes` parser, pathspec matcher, fixture, and PHP test harness.
