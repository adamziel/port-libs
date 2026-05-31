# Sparse checkout POSIX class boundary parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T132951Z`

Accepted base: `4f24a4c1a8e6dd271c40ece1708b484039354fa3`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: pathspec matching attempts `gix_glob::Pattern::matches_repo_relative_path()` and then falls back to verbatim pathspec matching.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: POSIX classes are ASCII byte ranges; `blank` accepts ASCII whitespace bytes while `space` accepts only literal space in this matcher, and unsupported POSIX class names abort wildcard matching.

Native PHP delta:

- `SparseCheckoutSpec` now treats unsupported POSIX classes in pathspec-origin glob rules as a wildmatch abort and applies Gitoxide-style verbatim fallback instead of expanding later `*` bytes.
- POSIX class ranges are explicit ASCII byte classes shared with the existing Git attributes/pathspec boundary behavior.
- The WordPress sparse-checkout example now exposes upload pathspec behavior for `[[:blank:]]`, `[[:space:]]`, and invalid POSIX class literal fallback.

Focused verification:

- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php` passed.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php` passed.
- Red-first precheck before the implementation showed `:(glob)wp-content/uploads/slot[[:blank:]]/**` skipped `slot\v/photo.jpg` and `:(glob)wp-content/uploads/[[:unknown:]]*.jpg` incorrectly included `[[:unknown:]]hero.jpg`.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php` passed `1 test files, 188 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php` passed `2 test files, 298 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `39 test files, 4644 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-sparse-checkout.php` exited 0.
- `git diff --check -- lanes/gitoxide` passed.

Expected status delta:

- `phpPass`: `4630 -> 4644`.
- Mapped denominator: `1576 / 2886 -> 1577 / 2886`.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local pathspec parser, sparse checkout matcher, tree filtering, and PHP PCRE byte matching.

Non-overlap:

- This does not repeat accepted sparse-checkout prefix, default search-mode, directory exclude, absolute-root, absolute wildcard icase, or bracket/wildmatch pathspec slices. It is bounded to POSIX class byte boundaries and unsupported POSIX class verbatim fallback inside `SparseCheckoutSpec`.
