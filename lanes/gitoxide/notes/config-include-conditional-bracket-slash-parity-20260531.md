# Gitoxide Config Conditional Include Bracket Slash Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T100539Z`

Accepted base: `db6e720333280b900b4f227c59e0153ddd55f2fc`

Upstream source truth:

- `gix-config/src/file/includes/mod.rs` evaluates `gitdir`, `onbranch`, and `hasconfig:remote.*.url` conditions through `gix_glob::wildmatch` with `Mode::NO_MATCH_SLASH_LITERAL`.
- `gix-glob/src/wildmatch.rs` rejects a slash match after bracket-class evaluation when `NO_MATCH_SLASH_LITERAL` is active. This applies even if a bracket class explicitly contains `/`.
- Existing upstream conditional include tests already cover slash-aware `*`, `?`, and `[xwz]` classes; this slice closes the same slash boundary for bracket classes in the native config matcher.

Native PHP movement:

- `GitConfig::wildmatch()` now prefixes bracket-class regex fragments with a slash lookahead guard, so `[/]`, `[!x]`, or ranges cannot consume `/` inside config include conditions.
- `GitConfigTest.php` adds focused conditional include checks for `gitdir:work[/]tree/`, `onbranch:feature[/]start`, and `hasconfig:remote.*.url:...[/]...` refusing slash separators, while preserving non-slash bracket class matches.
- The WordPress config include fixture/example now records a rejected bracket-slash remote URL policy and a still-matching non-slash bracket URL policy.

Focused evidence:

- Red-first check before the implementation: `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` failed with `Expected: 'base', Actual: 'slash-class-override'` for `gitdir:work[/]tree/` and `Expected: NULL, Actual: 'should-not-load'` for the WordPress hasconfig slash-class policy.
- After the fix: `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` passed `1 test files, 63 assertions, 0 failures`.
- Full lane check: `php tools/run-tests.php lanes/gitoxide/tests` passed `38 test files, 4038 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-config-include-conditional.php` exited 0.
- Syntax and diff checks passed for changed PHP files and `git diff --check -- lanes/gitoxide`.

Dependency closure:

No new support component is needed. This slice reuses the existing native Git config parser, include resolver, and bounded glob matcher; no shared dependency row or activation gate is proposed.

Non-overlap:

This extends the accepted config include/includeIf escape and double-star slices with the missing bracket-class slash boundary. It does not touch protocol, transport, pack, reference, object database, index, sparse checkout, pathspec, URL/refspec, or merge behavior. The old Gitoxide smart-HTTP rework notes are stale for this slice because they target receive-pack redirect/status metadata conflicts, not config include parity.
