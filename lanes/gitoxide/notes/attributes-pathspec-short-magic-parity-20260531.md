# Attributes Pathspec Short Magic Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260531T221145Z`

Base accepted HEAD: `6f5231cf32a6827b588751d49dba711af77e658b`

## Upstream Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-pathspec/src/parse.rs::parse_short_keywords()` accepts `/`, `!`, `^`, and `:` as implemented short magic.
- The same parser rejects unimplemented short-magic bytes, including `;`, `-`, and `@`, before long-magic `attr:` parsing begins.
- `gix-pathspec/src/search/init.rs` feeds parsed pathspecs into the search mapping only after this parse boundary succeeds.

## Native PHP Delta

- `PathspecPattern::parse()` now rejects the same unimplemented short-magic bytes as `PathspecMatcher` and upstream `gix-pathspec`.
- Valid exclude spellings `:!:` and `:^:` still carry attr filters into `PathspecSearch`.
- The WordPress attributes/pathspec example now records that malformed short magic before an attr filter is rejected instead of silently becoming a literal path.

## Red-First Evidence

Before the change, this probe accepted the malformed pathspecs:

```sh
php -r 'require "tools/bootstrap.php"; foreach ([":;wp-content/plugins/**", ":-wp-content/plugins/**", ":@wp-content/plugins/**"] as $spec) { try { PortLibs\Gitoxide\PathspecSearch::fromSpecs([$spec]); echo $spec . " accepted\n"; } catch (Throwable $e) { echo $spec . " rejected: " . $e->getMessage() . "\n"; } }'
```

It printed:

```text
:;wp-content/plugins/** accepted
:-wp-content/plugins/** accepted
:@wp-content/plugins/** accepted
```

## Verification

- `php -l lanes/gitoxide/src/PathspecPattern.php`
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  - `1 test files, 133 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 5916 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  - exited `0`
- `git diff --check -- lanes/gitoxide`
  - exited `0`

## Non-Overlap

This deepens the existing attributes/pathspec parser and match cluster without claiming a new mapped denominator row. It does not repeat selected-assignment semantics, value suffix handling, tab-after-value rejection, POSIX class behavior, recursive macro lookup, empty long-magic component rejection, tree pathspec walking, sparse checkout, protocol, transport, pack, object database, reference, reflog, or merge-base behavior. The old May 25 receive-pack rework notes are stale for this slice.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local PHP pathspec parser/search implementation, Git attribute provider, existing WordPress attributes/pathspec example, and root test harness. It does not shell out to Git, run live provider tests, read credentials, or require a shared support activation gate.
