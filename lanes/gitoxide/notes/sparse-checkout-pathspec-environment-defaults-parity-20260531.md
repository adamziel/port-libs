# Sparse Checkout Pathspec Environment Defaults Parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T232023Z`

Base: `afee0853cdadd52fa12dbc1e24d633ac7329910c`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/defaults.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/init.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config-value/src/boolean.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config-value/tests/value/boolean.rs`

## Behavior Ported

`SparseCheckoutSpec::fromPathspecsWithEnvironment()` now maps gitoxide
`gix-pathspec::Defaults::from_environment()` behavior for sparse pathspec
matching:

- `GIT_LITERAL_PATHSPECS` forces literal default matching and wins over glob and no-glob defaults without parsing those skipped globals.
- `GIT_GLOB_PATHSPECS` forces path-aware glob default matching.
- `GIT_NOGLOB_PATHSPECS` forces literal default matching when the variable is present while explicit `:(glob)` still overrides per spec; a parsed false value avoids the glob/no-glob conflict but still overrides glob mode, matching upstream control flow.
- `GIT_ICASE_PATHSPECS` folds pathspec matching without folding the caller-provided prefix.
- `GIT_GLOB_PATHSPECS` plus `GIT_NOGLOB_PATHSPECS` is rejected unless literal mode already won.
- Boolean parsing follows gitoxide config-value booleans for yes/on/true, no/off/false, numeric zero/nonzero within signed 64-bit bounds, empty false, and invalid strings.

Red-first probe on this base:

```sh
php -r 'require "tools/bootstrap.php"; echo method_exists("PortLibs\\Gitoxide\\SparseCheckoutSpec", "fromPathspecsWithEnvironment") ? "yes\n" : "no\n";'
```

Result before implementation: `no`.

## Verification

```sh
php -l lanes/gitoxide/src/SparseCheckoutSpec.php
php -l lanes/gitoxide/tests/SparseCheckoutTest.php
php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php
```

Result: no syntax errors.

```sh
php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php
```

Result: `1 test files, 268 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php
```

Result: `2 test files, 408 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/gitoxide/tests
```

Result: `40 test files, 6270 assertions, 0 failures`.

```sh
php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["environmentLiteralIcaseMagicTextIncluded", "environmentLiteralColonIncluded", "environmentGlobNestedPluginSkipped", "environmentNoGlobLiteralPluginIncluded", "environmentNoGlobMagicOverrideIncluded", "environmentFalseNoGlobLiteralIncluded", "environmentFalseNoGlobPluginSkipped", "environmentIcaseUpperPrefixIncluded", "environmentIcaseLowerPrefixSkipped", "environmentGlobNoGlobConflictRejected"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "sparse checkout pathspec environment example ok\n";'
```

Result: `sparse checkout pathspec environment example ok`.

```sh
git diff --check -- lanes/gitoxide
```

Result: no whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted sparse checkout cone, negative nil root,
directory-only exclude, wildcard traversal, POSIX class, absolute-root, prefix,
or default search-mode tree walking work. It is limited to upstream
environment-derived default pathspec settings and boolean/conflict semantics.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
`SparseCheckoutSpec` pathspec parser/search implementation and adds only the
environment-default adapter needed for gitoxide parity.
