# Config Include Legacy Dot Remote Subsection Parity

Slice: `gitoxide-config-include-conditional-parity-20260601T023419Z`

Source truth:
- `gix-config/src/parse/from_bytes/mod.rs` accepts deprecated unquoted section
  headers as `[name.subsection]` and splits at the first dot when no quoted
  subsection is present.
- `gix-config/src/file/includes/mod.rs` resolves
  `includeIf.hasconfig:remote.*.url` by searching sections named `remote`.

Native PHP delta:
- `GitConfig` now preserves Gitoxide's deprecated dot-subsection parse shape,
  so `[remote.origin]` becomes section `remote` with subsection `origin`
  instead of an unrelated `remote.origin` section.
- `includeIf "hasconfig:remote.*.url:..."` now sees legacy `[remote.origin]`
  URLs when deciding whether to load conditional include files.
- Quoted subsection headers such as `[remote.origin "quoted"]` keep their
  literal dotted section name, matching the upstream split-only-unquoted rule.

Verification:
- `php -l lanes/gitoxide/src/GitConfig.php`,
  `php -l lanes/gitoxide/tests/GitConfigTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`,
  and `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  passed.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  passed with `1 test files, 193 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `40 test files, 6940 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  exited 0.
- `git diff --check -- lanes/gitoxide` passed.

Dependency closure:
- No new support component is needed. This reuses the existing bounded
  GitConfig parser and conditional include resolver.
