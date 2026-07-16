# Gitoxide URL/Refspec Alternate Serialization Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T040657Z`

Accepted base: `431362468a9b0d67073256297cf9e0acadb56383`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/lib.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/ssh.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/file.rs`

`gix-url` exposes `Url::serialize_alternate_form(true)` to write canonical
file URLs as local paths and canonical SSH URLs as SCP-like targets when the
URL has no password or port. The same writer deliberately falls back to
canonical URL serialization for password-bearing SSH URLs, port-bearing SSH
URLs, and non-file/non-SSH schemes.

## Native PHP Delta

- `GitUrl::withAlternativeForm()` now provides an immutable upstream-style
  toggle for the already stored alternate-serialization flag.
- File alternate serialization now shares the same writer path as SSH instead
  of an early path-only special case, preserving upstream-shaped host/user
  handling for future file-authority callers while keeping ordinary local
  paths byte-for-byte stable.
- `UrlRefSpecTest.php` covers canonical file-to-path output, local
  path-to-file URL output, SSH URL-to-SCP-like output, absolute SSH paths,
  password/port canonical fallback, and non-SSH canonical fallback.
- The WordPress URL/refspec fixture/example now records alternate deployment
  remote bytes and a canonical local mirror URL rendered back as a filesystem
  path without invoking the Git binary.

## Focused Evidence

- Red-first after adding assertions:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed with
  `Call to undefined method PortLibs\Gitoxide\GitUrl::withAlternativeForm()`
  and reported `1 test files, 608 assertions, 1 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 629 assertions, 0 failures`.
- Full Gitoxide lane after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7323
  assertions, 0 failures`.
- Syntax checks passed for the changed PHP files:
  `lanes/gitoxide/src/GitUrl.php`,
  `lanes/gitoxide/tests/UrlRefSpecTest.php`,
  `lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`, and
  `lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.
- JSON and whitespace checks:
  `php -r 'foreach (["lanes/gitoxide/lane-status.json", "lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); echo $file, " json ok\n"; }'`
  reported both files valid, and `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
URL parser, UTF-8 validation, userinfo/path serialization, and WordPress
URL/refspec fixture. No live remote, credential store, environment, upstream
binary, or shared support-library activation gate is required.

## Non-Overlap

This maps one additional `gix-url` URL writer/access behavior cluster and does
not repeat accepted baseline URL parsing, file authority handling, one-sided
push writer normalization, short-hex prefix handling, URL length guards, empty
SSH port normalization, SCP bracket boundaries, home-path expansion,
canonicalized file paths, pathless extension remotes, argument-safety/root-path
helpers, credential mutation/access parity, or transport/status behavior. The
historical May 25 smart HTTP receive-pack rework notes target stale
transport/status metadata conflicts and are not part of this URL/refspec
cluster.
