# Gitoxide URL/Refspec Slash-Literal Pattern Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T123327Z`

Base accepted HEAD: `c0f466b52b24855ebf2184044c0a755725b1aa01`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/match_group/util.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/tests/refspec/match_group.rs`

`gix-refspec` routes one-sided complex fetch patterns through
`gix_glob::wildmatch(..., Mode::NO_MATCH_SLASH_LITERAL)`. In that mode,
wildcard `*`, wildcard `?`, and bracket classes do not match `/`; a slash must
be matched by a literal slash in the pattern. Component-boundary `**/` remains
the upstream escape hatch for matching across multiple slash-separated
components.

## Native PHP Delta

- `RefSpec::matchFetchRemoteRefs()` now keeps slash boundaries for one-sided
  complex pattern matching by translating ordinary `*` to `[^/]*`, `?` to
  `[^/]`, and guarding bracket classes from consuming `/`.
- The matcher preserves component-boundary globstar behavior for patterns such
  as `refs/heads/**/release`.
- The WordPress URL/refspec normalization fixture now includes a deployment
  refspec pattern `refs/heads/*/release/*` that matches release branch refs but
  rejects extra path components hidden inside either wildcard segment.

## Focused Evidence

- Red-first after adding focused assertions: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed because `refs/heads/*/release/*` matched `refs/heads/plugin/extra/release/stable` and `refs/heads/plugin/release/stable/extra`.
- Upstream focused probe: `cargo test -p gix-refspec one_sided -- --nocapture` in `/home/claude/port-libs/.upstream-cache/gitoxide` passed `4` selected tests.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 773 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
refspec parser and matcher; no network, credential store, provider config,
OAuth/browser state, live remote, or full Cargo workspace runner was used.

## Non-Overlap

This deepens the existing URL/refspec match-LHS cluster with slash-literal
one-sided complex pattern behavior. It does not repeat accepted URL parsing,
empty SSH port normalization, FTP host guards, source-only push writer
normalization, refspec instruction identity, short-hex prefix expansion,
transport/protocol, object database, pack/index, references, sparse-checkout,
pathspec, merge-base, partial-clone, or tree-merge work.
