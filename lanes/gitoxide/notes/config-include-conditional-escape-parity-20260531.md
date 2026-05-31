# Gitoxide Config Conditional Include Escape Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T090002Z`

Accepted base: `9986ffeeb381ed3e9dc9166d1668e256084ca733`

Upstream source truth:

- `gix-config/src/parse/from_bytes/mod.rs` parses quoted section subsections by copying the byte after any backslash escape. This means `includeIf "gitdir:work\\tree/"` is a `worktree/` condition, not a condition containing a tab.
- `gix-config/src/file/includes/mod.rs` passes conditional patterns into `gix_glob::wildmatch`, where a backslash escapes the next pattern byte. This is used by focused upstream gitdir tests such as `pattern_with_backslash` and `pattern_with_escaped_backslash`, and by `hasconfig:remote.*.url` globs.
- `gix-config/tests/config/file/init/from_paths/includes/conditional/gitdir/mod.rs` and `hasconfig.rs` provide the focused parity cases for escaped gitdir conditions and remote URL glob matching.

Native PHP movement:

- `GitConfig` now separates quoted subsection unescaping from quoted value unescaping. Values keep Git-style `\n`, `\t`, and `\b` behavior, while section subsections fold any `\x` to `x`, matching the upstream parser boundary.
- `GitConfig::wildmatch()` now treats backslash as a pattern escape before interpreting `*`, `**`, `?`, and character classes. This lets escaped condition globs match literal wildcard bytes.
- The WordPress config include fixture/example now includes an escaped `gitdir` conditional policy include.

Focused evidence:

- Red-first check before the implementation: `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` failed the new conditional include escape test with `Expected: 'override-value'`, `Actual: 'base-value'`.
- After the fix: `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` passed `1 test files, 48 assertions, 0 failures`.
- Full Gitoxide lane check after the fix: `php tools/run-tests.php lanes/gitoxide/tests` passed `36 test files, 3281 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-config-include-conditional.php` exited 0.

Dependency closure:

No new support component is needed. This slice reuses the existing native Git config parser, include resolver, and bounded glob matcher; no shared dependency row or activation gate is proposed.

Non-overlap:

This extends the accepted config include/includeIf slice from source `6d9f6eff` with the missing quoted-subsection and backslash-glob escape boundary. It does not touch protocol, transport, pack, reference, object database, index, or merge surfaces, and it does not rework the stale smart HTTP receive-pack notes listed in old Gitoxide handoff rework files.
