# Gitoxide Attributes Pathspec Recursive Macro Lookup Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260531T154553Z`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-attributes/tests/search/mod.rs` includes `given_attributes_are_made_available_in_given_order()` over the generated `lookup-order/baseline.selected` fixture.
- `gix-attributes/src/search/outcome.rs` fills matching assignments from highest to lowest precedence with a LIFO stack, records each macro/attribute once, and recursively expands final macro definitions while skipping already filled attributes.
- The generated lookup-order baseline expects a later `* -other b-cycle` rule to set `other` false while expanding `b-cycle -> my-text` before the overridden `my-binary -> binary` macro can leave `text` unset.

## Native PHP Delta

- `GitAttributes` now walks matching rules in reverse precedence order and fills each attribute/macro once.
- Macro expansion now uses a LIFO stack with already-filled guards, matching Gitoxide lookup order for recursive/cyclic macros and final macro redefinitions.
- `:(attr:...)` filters now see the corrected selected states, so recursive macro selections can match `text`, `recursive`, `macro-overridden`, and `-other` together.
- The WordPress attributes/pathspec example now includes a recursive macro deployment filter for plugin content.

## Red-First Evidence

Before the change, this probe returned `text => false` and the pathspec match returned `false`:

```sh
php -r 'require "tools/bootstrap.php"; use PortLibs\Gitoxide\{GitAttributes,PathspecMatcher}; $source="[attr]my-text text\n[attr]my-binary binary\n[attr]b-cycle a-cycle my-text\n[attr]a-cycle b-cycle my-binary\n[attr]recursive recursively-assigned-attr\n[attr]my-binary binary macro-overridden recursive\n* other a-cycle\n* -other b-cycle\n"; $attrs=GitAttributes::fromString($source); var_export($attrs->attributesForPath("any", ["text","my-binary","recursive","macro-overridden","other"])); echo "\n"; var_export(PathspecMatcher::matchesOne(":(attr:text recursive macro-overridden -other)wp-content/**", "wp-content/plugins/editor/block.php", false, GitAttributes::fromString(str_replace("* ", "wp-content/** ", $source)))); echo "\n";'
```

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  - `1 test files, 102 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4879 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  - exited `0`

Additional lint and diff checks are recorded in the worker final handoff.

## Non-Overlap

This is additive to the accepted attributes/pathspec selected-assignment, state-adjustment, search attr-filter, POSIX class, POSIX class-boundary, and attr=value tab-boundary slices. It does not repeat pathspec parsing, POSIX character classes, selected unspecified behavior, or transport/protocol work.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local Git attributes parser, pathspec matcher/search APIs, existing WordPress attributes/pathspec example, PHP test harness, and the hydrated upstream Gitoxide cache for source-truth inspection.
