# Essential Dependency Follow-up Review - 2026-05-24T11:06:09Z

Scope: read-only review of the applied support tracker follow-up for
`git-wire-protocol-core`, `quadrable-proof-transport-codec-core`, and
Difftastic-first tightening of `tree-sitter-grammar-subset`.

## Inputs Read

- `dependency-backlog.json`
- `progress.md`
- `audits/essential-dependency-audit-20260524T105746Z.md`
- `audits/support-library-essential-audit-followup-20260524T110535Z.md`
- `lanes/gitoxide/lane-status.json`
- `lanes/quadrable/lane-status.json`
- `lanes/difftastic/lane-status.json`

Safety boundary observed: no process environments, credential stores, provider
configs, OAuth/browser auth state, cloud remotes, secret-bearing inputs, live
service tests, root tests, dashboard regeneration, staging, commit, or push were
inspected or run.

## Decision

1. Proposed dependency rows: **fail for one remaining tracker correction**.
   - `git-wire-protocol-core` exists once, is inactive (`candidate`), has the
     expected Gitoxide-only need, bounded native pkt-line/protocol scope,
     correct activation gate, explicit non-goals, Git spec/Gitoxide upstream
     denominators, PHP evidence expectations, malformed packet/error coverage,
     and live-service/credential/secret exclusions.
   - `quadrable-proof-transport-codec-core` exists once, is inactive
     (`candidate`), has the expected Quadrable-only need, bounded proof/sync
     transport scope, correct activation gate, explicit non-goals, Quadrable
     upstream denominators, PHP evidence expectations, malformed codec/root
     coverage, and live-service exclusions. It does **not** explicitly exclude
     secret-bearing inputs, credentials, or secret-bearing configs.
2. `tree-sitter-grammar-subset`: **pass**. The row is Difftastic-first:
   `neededBy` is `["difftastic"]`, the activation gate is
   `difftastic-concrete-grammar-structural-next`, esbuild and LightningCSS are
   kept in future reuse notes only, and the row remains inactive (`candidate`).
3. Backlog JSON and duplicate IDs: **pass**. `jq empty dependency-backlog.json`
   passed, all three reviewed IDs exist exactly once, and the duplicate-ID
   query returned no rows.

## Validation Actually Run

- `jq empty dependency-backlog.json`
- `jq '.items[] | select(.id == "git-wire-protocol-core")' dependency-backlog.json`
- `jq '.items[] | select(.id == "quadrable-proof-transport-codec-core")' dependency-backlog.json`
- `jq '.items[] | select(.id == "tree-sitter-grammar-subset")' dependency-backlog.json`
- `jq -r '.items[].id' dependency-backlog.json | sort | uniq -d`
- `jq '{updated, count:(.items|length), statuses:(.items|group_by(.status)|map({status:.[0].status,count:length})), active:(.items|map(select(.status=="active"))|length)}' dependency-backlog.json`
- `jq -e '([.items[] | select(.id=="git-wire-protocol-core")] | length) == 1 and ([.items[] | select(.id=="quadrable-proof-transport-codec-core")] | length) == 1 and ([.items[] | select(.id=="tree-sitter-grammar-subset")] | length) == 1' dependency-backlog.json`
- `jq -e '.items[] | select(.id=="tree-sitter-grammar-subset") | (.neededBy == ["difftastic"] and .status != "active" and (.reuseNotes | test("esbuild") and test("LightningCSS")))' dependency-backlog.json`
- `jq -e '.items[] | select(.id=="quadrable-proof-transport-codec-core") | ((.scopeBoundary + " " + .testExpectation) | test("secret|credential|credential-bearing|secret-bearing"; "i"))' dependency-backlog.json`

## Remaining Tracker Corrections

- Add an explicit secret/credential exclusion to
  `quadrable-proof-transport-codec-core`, preferably in both `scopeBoundary`
  and `testExpectation`, for secret-bearing transport captures, credential
  material, and secret-bearing configs. Do not otherwise broaden the row or
  activate it.
