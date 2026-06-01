# URL/refspec match-rhs parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T162216Z`

Source truth:

- Upstream Gitoxide cache:
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/match_group/mod.rs`
  `MatchGroup::match_rhs()`.
- Supporting upstream helper:
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/match_group/util.rs`
  `Matcher::matches_rhs()` and `Needle::to_bstr_replace()`.
- Focused upstream tests inspected:
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/tests/refspec/match_group.rs`
  and `tests/refspec/matching.rs`.

Native PHP delta:

- Added `RefSpec::matchFetchLocalRefs()` to reverse-map local tracking refs
  through fetch refspec destinations, mirroring upstream `match_rhs`.
- The helper skips source-only and negative fetch specs, expands simple glob
  captures from local destination refs back into remote source refs, converts
  partial ref names to full `refs/heads/*`/`refs/tags/*`/`refs/remotes/*`
  names, and matches object-id destinations against optional local ref
  `target`/`object` metadata.
- Updated the WordPress URL/refspec fixture and example so deployment tooling
  can infer remote branches/tags from existing `refs/remotes/deploy/*` and tag
  refs before fetch/prune planning.

Verification:

- Baseline before patch: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php`
  passed `1 test files, 831 assertions, 0 failures`.
- Focused after patch: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php`
  passed `1 test files, 844 assertions, 0 failures`.
- Syntax:
  `php -l lanes/gitoxide/src/RefSpec.php`,
  `php -l lanes/gitoxide/tests/UrlRefSpecTest.php`,
  `php -l lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`, and
  `php -l lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php` all
  reported no syntax errors.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.
- Whitespace check: `git diff --check -- lanes/gitoxide` exited 0.

Status and non-overlap:

- Focused assertion delta: `+13`.
- Expected lane `phpPass`: `10127 -> 10140`.
- Mapped coverage remains `1807 / 2886` until supervisor admission.
- This does not repeat accepted URL parse normalization, from-parts/from-bytes,
  credential mutation, argument-safety, match-lhs, validated fetch, one-sided
  push writer, colon-path classification, transport/protocol, object database,
  pack/index, reference transaction, tree-merge, pathspec, or merge-base work.

Dependency closure:

- No new support component is needed. The slice reuses the existing native
  `RefSpec`, `ReferenceName`, URL/refspec fixture, and test runner.
