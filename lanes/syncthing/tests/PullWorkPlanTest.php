<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullWorkPlan;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream handleFile copy and pull accounting' => static function (TestRunner $t): void {
        $current = syncthing_pull_work_file('filex', [0, 2, 0, 0, 5, 0, 0, 8]);
        $target = syncthing_pull_work_file('filex', [1, 2, 3, 4, 5, 6, 7, 8]);

        $plan = PullWorkPlan::forFile($target, $current);
        $progress = $plan->progressAfterLocalWork();

        $t->same([2, 5, 8], syncthing_pull_work_numbers($plan->sameIndexHave));
        $t->same([1, 2, 3, 4, 5, 6, 7, 8], syncthing_pull_work_numbers($plan->remainingBlocks));
        $t->same([], $plan->reusedBlockIndexes);
        $t->same([2, 5, 8], syncthing_pull_work_numbers($plan->copyFromOriginBlocks));
        $t->same([1, 3, 4, 6, 7], syncthing_pull_work_numbers($plan->pullBlocks));
        $t->same([1, 4, 7], $plan->availableBlockIndexesAfterLocalWork());
        $t->same(8, $progress->total);
        $t->same(3, $progress->copiedFromOrigin);
        $t->same(5, $progress->pulling);
    },
    'maps upstream handleFile temporary-file reuse pruning' => static function (TestRunner $t): void {
        $current = syncthing_pull_work_file('file', [0, 2, 0, 0, 5, 0, 0, 8]);
        $target = syncthing_pull_work_file('file', [1, 2, 3, 4, 5, 6, 7, 8]);
        $temporary = syncthing_pull_work_blocks([0, 2, 3, 4, 0, 0, 7, 0]);

        $plan = PullWorkPlan::forFile($target, $current, temporaryBlocks: $temporary);
        $progress = $plan->progressAfterLocalWork();

        $t->same([1, 2, 3, 6], $plan->reusedBlockIndexes);
        $t->same([2, 3, 4, 7], syncthing_pull_work_numbers($plan->reusedBlocks));
        $t->same([1, 5, 6, 8], syncthing_pull_work_numbers($plan->remainingBlocks));
        $t->same([5, 8], syncthing_pull_work_numbers($plan->copyFromOriginBlocks));
        $t->same([1, 6], syncthing_pull_work_numbers($plan->pullBlocks));
        $t->same([1, 2, 3, 6, 4, 7], $plan->availableBlockIndexesAfterLocalWork());
        $t->same(8, $progress->total);
        $t->same(4, $progress->reused);
        $t->same(2, $progress->copiedFromOrigin);
        $t->same(2, $progress->pulling);
    },
    'maps local block-index fallback and copier finder pull list' => static function (TestRunner $t): void {
        $target = syncthing_pull_work_file('file2', [1, 2, 3, 4, 5, 6, 7, 8]);
        $temporary = syncthing_pull_work_blocks([0, 2, 3, 4, 0, 0, 7, 0]);
        $localIndexedBlocks = syncthing_pull_work_blocks([2, 3, 4, 7]);

        $withTemp = PullWorkPlan::forFile($target, temporaryBlocks: $temporary, localBlocks: $localIndexedBlocks);
        $t->same([1, 5, 6, 8], syncthing_pull_work_numbers($withTemp->remainingBlocks));
        $t->same([], syncthing_pull_work_numbers($withTemp->copyFromElsewhereBlocks));
        $t->same([1, 5, 6, 8], syncthing_pull_work_numbers($withTemp->pullBlocks));

        $withoutTemp = PullWorkPlan::forFile($target, localBlocks: $localIndexedBlocks);
        $t->same([2, 3, 4, 7], syncthing_pull_work_numbers($withoutTemp->copyFromElsewhereBlocks));
        $t->same([1, 5, 6, 8], syncthing_pull_work_numbers($withoutTemp->pullBlocks));
        $t->same(4, $withoutTemp->progressAfterLocalWork()->copiedFromElsewhere);
    },
    'maps upstream sparse zero-block handling from TestPullEmptyBlock' => static function (TestRunner $t): void {
        $zeroFile = syncthing_pull_work_file('zeros.bin', [0]);
        $sparsePlan = PullWorkPlan::forFile($zeroFile);
        $t->true($zeroFile->blocks[0]->isAllZeroes());
        $t->same([0], syncthing_pull_work_numbers($sparsePlan->sparseSkippedBlocks));
        $t->same([], syncthing_pull_work_numbers($sparsePlan->pullBlocks));
        $t->same([0], $sparsePlan->availableBlockIndexesAfterLocalWork());
        $t->same(1, $sparsePlan->progressAfterLocalWork()->copiedFromOrigin);

        $nonSparsePlan = PullWorkPlan::forFile($zeroFile, sparseFiles: false);
        $t->same([], syncthing_pull_work_numbers($nonSparsePlan->sparseSkippedBlocks));
        $t->same([0], syncthing_pull_work_numbers($nonSparsePlan->pullBlocks));

        $targetWithReuse = syncthing_pull_work_file('zeros.bin', [0, 2]);
        $temporary = syncthing_pull_work_blocks([9, 2]);
        $reusedPlan = PullWorkPlan::forFile($targetWithReuse, temporaryBlocks: $temporary);
        $t->same([1], $reusedPlan->reusedBlockIndexes);
        $t->same([], syncthing_pull_work_numbers($reusedPlan->sparseSkippedBlocks));
        $t->same([0], syncthing_pull_work_numbers($reusedPlan->pullBlocks));
    },
    'receive-encrypted planning does not reuse temporary file blocks' => static function (TestRunner $t): void {
        $target = syncthing_pull_work_file('private-media.bin', [1, 2, 3]);
        $temporary = syncthing_pull_work_blocks([1, 2, 3]);

        $plain = PullWorkPlan::forFile($target, temporaryBlocks: $temporary);
        $encrypted = PullWorkPlan::forFile($target, temporaryBlocks: $temporary, receiveEncrypted: true);

        $t->same([0, 1, 2], $plain->reusedBlockIndexes);
        $t->same([], $plain->pullBlocks);
        $t->same([], $encrypted->reusedBlockIndexes);
        $t->same([1, 2, 3], syncthing_pull_work_numbers($encrypted->pullBlocks));
    },
    'rejects malformed pull work inputs' => static function (TestRunner $t): void {
        $target = syncthing_pull_work_file('file', [1]);

        $t->throws(InvalidArgumentException::class, static fn () => PullWorkPlan::forFile($target, temporaryBlocks: ['bad']));
        $t->throws(InvalidArgumentException::class, static fn () => new PullWorkPlan(
            file: $target,
            sameIndexHave: [],
            remainingBlocks: [],
            reusedBlocks: [],
            reusedBlockIndexes: [-1],
            sparseSkippedBlocks: [],
            copyFromOriginBlocks: [],
            copyFromElsewhereBlocks: [],
            pullBlocks: [],
            localAvailableBlocks: [],
        ));
    },
];

/**
 * @param list<int> $numbers
 *
 * @return list<Block>
 */
function syncthing_pull_work_blocks(array $numbers): array
{
    return array_map(static fn (int $number): Block => syncthing_pull_work_block($number), $numbers);
}

function syncthing_pull_work_file(string $name, array $blockNumbers): FileInfo
{
    $blocks = syncthing_pull_work_blocks($blockNumbers);

    return new FileInfo(
        name: $name,
        version: VersionVector::fromCounters([101 => 1]),
        size: count($blocks) * BlockList::MIN_BLOCK_SIZE,
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        blocks: $blocks,
    );
}

function syncthing_pull_work_block(int $number): Block
{
    if ($number < 0) {
        throw new InvalidArgumentException('Block fixture number must not be negative');
    }

    $offset = $number === 0 ? 0 : ($number - 1) * BlockList::MIN_BLOCK_SIZE;
    $hash = $number === 0
        ? hash('sha256', str_repeat("\0", BlockList::MIN_BLOCK_SIZE))
        : str_pad(dechex($number), 64, '0', STR_PAD_LEFT);

    return new Block($offset, BlockList::MIN_BLOCK_SIZE, $hash);
}

/**
 * @param list<Block> $blocks
 *
 * @return list<int>
 */
function syncthing_pull_work_numbers(array $blocks): array
{
    $numbers = [];
    foreach ($blocks as $block) {
        if ($block->isAllZeroes()) {
            $numbers[] = 0;
            continue;
        }

        $numbers[] = intval(ltrim($block->hashHex, '0') ?: '0', 16);
    }

    return $numbers;
}
