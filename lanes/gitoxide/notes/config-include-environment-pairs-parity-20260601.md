# Gitoxide Config Include Environment Pair Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T070718Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/init/from_env.rs`
  builds a config from `GIT_CONFIG_KEY_N` / `GIT_CONFIG_VALUE_N` entries,
  parses keys such as `includeIf.gitdir:...path`, and then resolves includes.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/key.rs` maps
  `includeIf.gitdir/i:C:\bare.git.path` into section `includeIf`, subsection
  `gitdir/i:C:\bare.git`, and value name `path`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  resolves `gitdir`, `onbranch`, and `hasconfig:remote.*.url` conditions for
  the parsed section data.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/tests/config/file/init/from_paths/includes/conditional/gitdir/mod.rs`
  covers the environment-style missing-config-path boundary for relative
  include paths and `gitdir:./...` conditions.

## Native Change

- `GitConfig::fromEnvironmentPairs()` now accepts caller-supplied environment
  config key/value pairs without reading the real process environment.
- Dotted keys are parsed like `gix_config::KeyRef`: the first component is the
  section, the last component is the value name, and the middle component is
  the subsection, preserving conditional bodies that contain dots, colons,
  slashes, and wildcards.
- Environment-style configs have no owning config path, so relative include
  paths and `gitdir:./...` conditions still raise or are ignored according to
  `errOnMissingConfigPath`, matching the upstream boundary.
- The WordPress config include fixture/example now covers per-request
  deployment overrides from caller-supplied config entries without invoking
  `git config` or reading environment secrets.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  - before this slice: `1 test files, 229 assertions, 0 failures`
  - after this slice: `1 test files, 243 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 7956 assertions, 0 failures`
- `php -l lanes/gitoxide/src/GitConfig.php`
  - no syntax errors
- `php -l lanes/gitoxide/tests/GitConfigTest.php`
  - no syntax errors
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`
  - no syntax errors
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  - no syntax errors
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  - exit 0
- `php -r 'json_decode(...)'` for `UPSTREAM_TEST_MANIFEST.json` and
  `lane-status.json`
  - both JSON files decode successfully
- `git diff --check -- lanes/gitoxide`
  - exit 0

## Dependency Closure

No new support component is needed. The slice reuses the existing native config
parser, include resolver, path interpolation, and byte-oriented conditional
wildmatch implementation.

## Non-Overlap

This extends accepted config include work for escaped globs, POSIX classes,
path interpolation, optional prefixes, dot-slash gitdir paths, drive-looking
Unix paths, and symlink gitdirs. It is limited to environment-style config
key/value ingestion and does not touch protocol, transport, refs, pack/index,
object database, merge-base, sparse checkout, attributes/pathspec, or smart HTTP
behavior.
