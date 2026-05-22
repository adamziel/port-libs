<?php

declare(strict_types=1);

use PortLibs\Dolt\CommitGraph;

$upstreamHasAncestorGraph = static function (): array {
    return [
        ['commit_hash' => 'main1', 'parents' => []],
        ['commit_hash' => 'main2', 'parents' => ['main1']],
        ['commit_hash' => 'bone1', 'parents' => ['main1']],
        ['commit_hash' => 'bone2', 'parents' => ['bone1'], 'refs' => ['refs/heads/bone']],
        ['commit_hash' => 'btwo1', 'parents' => ['main1'], 'refs' => ['refs/heads/btwo', 'refs/tags/tag_btwo1']],
        ['commit_hash' => 'main3', 'parents' => ['main2'], 'refs' => ['refs/heads/main']],
        ['commit_hash' => 'onetwo1', 'parents' => ['bone2', 'btwo1'], 'refs' => ['refs/heads/onetwo']],
    ];
};

return [
    'dolt ancestor spec parser maps upstream instruction syntax' => static function (TestRunner $t): void {
        $t->same([], CommitGraph::parseAncestorSpec(''));
        $t->same([0], CommitGraph::parseAncestorSpec('^'));
        $t->same([0], CommitGraph::parseAncestorSpec('^1'));
        $t->same([0], CommitGraph::parseAncestorSpec('~'));
        $t->same([0], CommitGraph::parseAncestorSpec('~1'));
        $t->same(array_fill(0, 10, 0), CommitGraph::parseAncestorSpec('~10'));
        $t->same([1, 0, 0, 0, 1], CommitGraph::parseAncestorSpec('^2~3^2'));

        $split = CommitGraph::splitAncestorSpec('HEAD~3^^');
        $t->same('HEAD', $split['commit_spec']);
        $t->same('~3^^', $split['ancestor_spec']);
        $t->same([0, 0, 0, 0, 0], $split['instructions']);

        $t->throws(InvalidArgumentException::class, static fn () => CommitGraph::parseAncestorSpec('invalid'));
        $t->throws(InvalidArgumentException::class, static fn () => CommitGraph::parseAncestorSpec('^0'));
        $t->throws(InvalidArgumentException::class, static fn () => CommitGraph::parseAncestorSpec('^3'));
        $t->throws(InvalidArgumentException::class, static fn () => CommitGraph::parseAncestorSpec('^10'));
        $t->throws(InvalidArgumentException::class, static fn () => CommitGraph::splitAncestorSpec('branch^invalid'));
    },
    'dolt has ancestor follows upstream branch tag head and merge closure cases' => static function (TestRunner $t) use ($upstreamHasAncestorGraph): void {
        $graph = new CommitGraph();
        $commits = $upstreamHasAncestorGraph();
        $head = 'bone2';

        $matrix = [
            'main' => ['main1' => true, 'main2' => true, 'bone1' => false, 'bone2' => false, 'btwo1' => false, 'onetwo1' => false, 'HEAD' => false],
            'bone' => ['main1' => true, 'main2' => false, 'bone1' => true, 'bone2' => true, 'btwo1' => false, 'onetwo1' => false, 'HEAD' => true],
            'btwo' => ['main1' => true, 'main2' => false, 'bone1' => false, 'bone2' => false, 'btwo1' => true, 'onetwo1' => false, 'HEAD' => false],
            'onetwo' => ['main1' => true, 'main2' => false, 'bone1' => true, 'bone2' => true, 'btwo1' => true, 'onetwo1' => true, 'HEAD' => true],
        ];

        foreach ($matrix as $reference => $checks) {
            foreach ($checks as $ancestor => $expected) {
                $t->same($expected, $graph->hasAncestor($commits, $reference, $ancestor, $head), "{$reference} -> {$ancestor}");
            }
        }

        $t->same([false, false, true, true, false, false], [
            $graph->hasAncestor($commits, 'HEAD', 'tag_btwo1', $head),
            $graph->hasAncestor($commits, 'bone2', 'tag_btwo1', $head),
            $graph->hasAncestor($commits, 'onetwo1', 'tag_btwo1', $head),
            $graph->hasAncestor($commits, 'btwo1', 'tag_btwo1', $head),
            $graph->hasAncestor($commits, 'main2', 'tag_btwo1', $head),
            $graph->hasAncestor($commits, 'main1', 'tag_btwo1', $head),
        ]);
        $t->same([false, false, false, true, false, true], [
            $graph->hasAncestor($commits, 'tag_btwo1', 'HEAD', $head),
            $graph->hasAncestor($commits, 'tag_btwo1', 'bone2', $head),
            $graph->hasAncestor($commits, 'tag_btwo1', 'onetwo1', $head),
            $graph->hasAncestor($commits, 'tag_btwo1', 'btwo1', $head),
            $graph->hasAncestor($commits, 'tag_btwo1', 'main2', $head),
            $graph->hasAncestor($commits, 'tag_btwo1', 'main1', $head),
        ]);
    },
    'dolt has ancestor resolves parent suffixes and validates refs' => static function (TestRunner $t) use ($upstreamHasAncestorGraph): void {
        $graph = new CommitGraph();
        $commits = $upstreamHasAncestorGraph();

        $t->same('bone2', $graph->resolve($commits, 'onetwo^1'));
        $t->same('btwo1', $graph->resolve($commits, 'onetwo^2'));
        $t->same('main1', $graph->resolve($commits, 'main~2'));
        $t->same(true, $graph->hasAncestor($commits, 'onetwo^2', 'main1'));
        $t->same(false, $graph->hasAncestor($commits, 'main~2', 'btwo1'));

        $ambiguous = $commits;
        $ambiguous[0]['refs'] = ['refs/tags/shared'];
        $ambiguous[1]['refs'] = ['refs/heads/shared'];

        $t->throws(RuntimeException::class, static fn () => $graph->resolve($commits, 'main^2'));
        $t->throws(RuntimeException::class, static fn () => $graph->resolve($commits, 'missing'));
        $t->throws(InvalidArgumentException::class, static fn () => $graph->resolve($commits, 'HEAD'));
        $t->throws(RuntimeException::class, static fn () => $graph->resolve($ambiguous, 'shared'));
    },
    'wordpress has ancestor fixture identifies reviewed import ancestry' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-has-ancestor-review.php';
        $graph = new CommitGraph();
        $example = require __DIR__ . '/../examples/wordpress-has-ancestor-review.php';

        $actual = [];
        foreach ($fixture['checks'] as $check) {
            $actual[] = [
                'reference' => $check['reference'],
                'ancestor' => $check['ancestor'],
                'has_ancestor' => $graph->hasAncestor(
                    $fixture['commits'],
                    $check['reference'],
                    $check['ancestor'],
                    $fixture['headHash'],
                ),
            ];
        }

        $t->same(array_map(static fn (array $check): array => [
            'reference' => $check['reference'],
            'ancestor' => $check['ancestor'],
            'has_ancestor' => $check['expected'],
        ], $fixture['checks']), $actual);
        $t->same($fixture['expectedResolvedSpecs'], $example['resolved']);
        $t->same($actual, $example['checks']);
    },
];
