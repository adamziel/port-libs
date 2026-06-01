# Attributes/Pathspec LF-Byte Wildmatch Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T094734Z`

Base accepted HEAD: `4bdcb2f759c9219bd2d0844e70bcb4381454fd85`

## Source Truth

- Upstream `gix-glob/src/wildmatch.rs` treats `*` and `?` as byte wildcards. LF is an ordinary byte; only path-aware mode blocks `/`.
- Upstream `gix-pathspec/src/search/matching.rs` uses shell-glob mode for default pathspecs and path-aware slash blocking for `:(glob)`.
- Upstream `gix-attributes/src/search/attributes.rs` applies path-aware wildmatch to repo-relative attribute patterns.

## Red-First Probe

Before the patch, native PHP regex wrappers rejected LF bytes for attribute and pathspec wildcard matches and accepted a final LF after an exact suffix through a `$` anchor:

```sh
php -r 'require "tools/bootstrap.php"; use PortLibs\Gitoxide\GitAttributes; use PortLibs\Gitoxide\PathspecMatcher; use PortLibs\Gitoxide\PathspecSearch; $a=GitAttributes::fromString("wp-content/uploads/** lf\n", withBuiltInMacros:false); var_export($a->attributesForPath("wp-content/uploads/line\nhero.jpg", ["lf"])); echo "\n"; var_export(GitAttributes::globMatches("wp-content/uploads/**", "wp-content/uploads/line\nhero.jpg", true)); echo "\n"; var_export(PathspecMatcher::matchesOne("wp-content/uploads/*hero.jpg", "wp-content/uploads/line\nhero.jpg", false)); echo "\n"; var_export(PathspecSearch::fromSpecs(["wp-content/uploads/*.jpg"])->isIncluded("wp-content/uploads/exact.jpg\n", false)); echo "\n";'
```

Observed pre-patch behavior: the LF wildcard cases were false/null while the trailing-LF exact-suffix pathspec was true.

## Native Delta

- `GitAttributes::globRegex()` now compiles wildcard regexes with DOTALL and a `\z` true-end anchor.
- `PathspecSearch::globMatches()` now uses `\z` instead of `$` so final LF bytes cannot satisfy an exact suffix.
- `AttributesPathspecTest` now covers LF-byte wildcard parity and true-end anchor behavior across attributes, `PathspecMatcher`, and `PathspecSearch`.
- `wordpress-attributes-pathspec.php` exposes the same deployment-upload LF-byte and trailing-LF boundary checks as the local smoke path.

## Verification

- `php -l lanes/gitoxide/src/GitAttributes.php` passed.
- `php -l lanes/gitoxide/src/PathspecSearch.php` passed.
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php` passed: `1 test files, 307 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php lanes/gitoxide/tests/AttributesPathspecTest.php` passed: `3 test files, 940 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed: `40 test files, 8541 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php >/tmp/gitoxide-attrs-example.out && echo example-ok` passed.

## Non-Overlap

This extends the existing attributes/pathspec cluster without repeating accepted ASCII whitespace, POSIX class, reversed range, escaped backslash, dangling backslash, double-star component, malformed bracket, sparse-checkout LF-byte, tree-pathspec LF-byte, transport, pack, reference, or object database slices.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP attributes/pathspec parser, PCRE-backed wildmatch translation, lane test harness, and WordPress-oriented attributes example. No live provider, shell-out Git process, credential store, or upstream Cargo workspace runner was used.
