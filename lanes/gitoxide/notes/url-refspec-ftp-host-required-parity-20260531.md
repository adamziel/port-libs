# Gitoxide URL/Refspec FTP Host Requirement Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260531T232759Z`

Accepted base: `a364d07040190b68b467cd69fb969339b783a7fe`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/simple_url.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/mod.rs`

`gix-url` stores `ftp` and `ftps` as extension schemes, but its URL-form parser
still treats them as standard host-required schemes. Other extension schemes,
such as `abc:///repo`, may remain hostless.

## Native PHP Delta

- `GitUrl::parse()` now rejects hostless `ftp:///repo` and `ftps:///repo`
  URL-form remotes before a deployment workflow can normalize them as usable
  remotes.
- Valid `ftp://host/repo` and `ftps://host/repo` remotes continue to parse as
  extension schemes with no default port, matching `gix-url::Scheme::Ext`.
- Hostless custom helper schemes such as `abc:///wp-content/site.git` remain
  accepted, preserving the upstream extension-scheme boundary.
- The WordPress URL/refspec example now records hostless FTP remote rejection
  alongside the existing oversized, malformed bracket, and invalid UTF-8 remote
  preflights.

## Focused Evidence

- Pre-fix probe on this accepted base: `GitUrl::parse('ftp:///repo')` accepted
  a URL with `host=null`, diverging from the upstream host-required set in
  `simple_url.rs`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php`
  passed `1 test files, 542 assertions, 0 failures`.
- Full Gitoxide lane after fix: `php tools/run-tests.php lanes/gitoxide/tests`
  passed `40 test files, 6285 assertions, 0 failures`.
- Syntax checks: `php -l lanes/gitoxide/src/GitUrl.php`,
  `php -l lanes/gitoxide/tests/UrlRefSpecTest.php`,
  `php -l lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`, and
  `php -l lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php` reported
  no syntax errors.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP URL-form
parser and a static scheme classification; it does not read live environments,
credential stores, provider config, OAuth state, external remotes, or network
services.

## Non-Overlap

This maps one additional `gix-url` URL-form parse boundary and does not repeat
accepted URL/refspec parse baseline, file authority, one-sided push writer,
short-hex prefix, URL length guard, empty SSH port, SCP bracket boundary,
home-path expansion, or canonical file path work. It also does not touch
transport, protocol v2, pack/object database, reference transactions,
merge/pathspec, partial clone, credential helpers, or the stale May 25 smart
HTTP receive-pack rework notes.
