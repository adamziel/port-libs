# URL/refspec colon-path classification parity - 2026-06-01

## Source truth

- Upstream cache inspected under `/home/claude/port-libs/.upstream-cache/gitoxide`.
- Relevant upstream files:
  - `gix-url/tests/url/parse/file.rs`
  - `gix-url/tests/url/parse/ssh.rs`
  - `gix-url/src/parse.rs`

Gitoxide classifies Unix bare colon paths such as `x:/path/to/git`, `x:\path\to\git`, `user@host.xz:C:/strange/absolute/path`, and `file:..` as scp-like SSH remotes. Explicit relative prefixes such as `./nohost:re/po` and `./[::1]:re/po` remain local file URLs even though their path contains a colon.

## Native delta

- Added focused `GitUrl` coverage for the Unix colon-path split.
- Extended the existing deployment URL/refspec normalize example and fixture:
  - `deploy-mirror:C:/wp-content/site.git` stays SSH/scp-like with host `deploy-mirror`.
  - `./mirrors/nohost:wp-content/site.git` stays a local file URL.
- No production source change was needed; the existing PHP parser already matched this upstream behavior.

## Verification

- Baseline before this slice: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 792 assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/UrlRefSpecTest.php` - no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-url-refspec-normalize.php` - no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php` - no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` - `1 test files, 831 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` - exit 0.
- `git diff --check -- lanes/gitoxide` - passed.

## Dependency closure

No new support component is needed. The behavior is fully covered by the existing native `GitUrl` parser, fixture, and example smoke.

## Non-overlap and next task

This slice avoids the accepted URL/refspec clusters for empty SSH ports, bracket-boundary rejection, URL length guards, from-parts/from-bytes normalization, credential mutation, argument-safety/root-path access helpers, UTF-8 canonical writing, and short-hex refspec normalization. A useful next URL/refspec slice would target a separate unverified parser/writer boundary from `gix-url` or `gix-refspec` rather than another Unix colon-path classification case.
