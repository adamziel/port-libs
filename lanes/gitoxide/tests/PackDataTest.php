<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PackBuilder;
use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;

$buildPackFixture = static function (array $objects, string $objectHash = 'sha1'): array {
    $hashBytes = match ($objectHash) {
        'sha1' => 20,
        'sha256' => 32,
        default => throw new RuntimeException("Unsupported test pack hash: {$objectHash}"),
    };
    $encodeEntryHeader = static function (int $typeId, int $size): string {
        $out = '';
        $first = ($typeId << 4) | ($size & 0x0f);
        $size >>= 4;
        while ($size !== 0) {
            $out .= chr($first | 0x80);
            $first = $size & 0x7f;
            $size >>= 7;
        }
        $out .= chr($first);

        return $out;
    };
    $encodeOfsDeltaDistance = static function (int $distance): string {
        $bytes = [$distance & 0x7f];
        $distance >>= 7;
        while ($distance !== 0) {
            $distance--;
            array_unshift($bytes, 0x80 | ($distance & 0x7f));
            $distance >>= 7;
        }

        return implode('', array_map(chr(...), $bytes));
    };
    $buildIndex = static function (array $entries, string $packChecksum) use ($objectHash, $hashBytes): string {
        usort($entries, static fn (array $a, array $b): int => strcmp($a['oid'], $b['oid']));
        $fanout = array_fill(0, 256, 0);
        foreach ($entries as $entry) {
            $fanout[hexdec(substr($entry['oid'], 0, 2))]++;
        }
        $running = 0;
        foreach ($fanout as $index => $count) {
            $running += $count;
            $fanout[$index] = $running;
        }

        $bytes = "\xfftOc" . pack('N', 2);
        foreach ($fanout as $count) {
            $bytes .= pack('N', $count);
        }
        foreach ($entries as $entry) {
            $oid = hex2bin($entry['oid']);
            if ($oid === false || strlen($oid) !== $hashBytes) {
                throw new RuntimeException('Invalid object id in test pack index');
            }
            $bytes .= $oid;
        }
        foreach ($entries as $entry) {
            $bytes .= pack('N', $entry['crc32']);
        }
        foreach ($entries as $entry) {
            $bytes .= pack('N', $entry['offset']);
        }
        $bytes .= hex2bin($packChecksum);

        return $bytes . hex2bin(hash($objectHash, $bytes));
    };

    $pack = 'PACK' . pack('N2', 2, count($objects));
    $entries = [];
    foreach ($objects as $object) {
        $offset = strlen($pack);
        $objectBody = $object['body'];
        $entryPrefix = '';
        if ($object['typeId'] === 6) {
            $base = $entries[$object['baseEntry']] ?? null;
            if ($base === null) {
                throw new RuntimeException('OFS_DELTA test fixture is missing its base entry');
            }
            $entryPrefix = $encodeOfsDeltaDistance($offset - $base['offset']);
        } elseif ($object['typeId'] === 7) {
            if (isset($object['baseOid'])) {
                $entryPrefix = hex2bin($object['baseOid']);
                if ($entryPrefix === false) {
                    throw new RuntimeException('REF_DELTA test fixture has an invalid external base object id');
                }
            } else {
                $base = $entries[$object['baseEntry']] ?? null;
                if ($base === null) {
                    throw new RuntimeException('REF_DELTA test fixture is missing its base entry');
                }
                $entryPrefix = hex2bin($base['oid']);
            }
        }

        $declaredSize = $object['declaredSize'] ?? strlen($objectBody);
        $entryBytes = $encodeEntryHeader($object['typeId'], $declaredSize) . $entryPrefix . gzcompress($objectBody);
        $pack .= $entryBytes;
        $indexType = $object['finalType'] ?? $object['type'];
        $indexBody = $object['finalBody'] ?? $objectBody;
        $entries[] = [
            'type' => $indexType,
            'body' => $indexBody,
            'oid' => (new GitObject($indexType, $indexBody))->oid($objectHash),
            'offset' => $offset,
            'crc32' => hexdec(hash('crc32b', $entryBytes)),
        ];
    }
    $packChecksum = hash($objectHash, $pack);
    $pack .= hex2bin($packChecksum);

    return [$pack, $buildIndex($entries, $packChecksum), $entries, $packChecksum];
};

$encodeDeltaSize = static function (int $size): string {
    $bytes = '';
    do {
        $byte = $size & 0x7f;
        $size >>= 7;
        if ($size !== 0) {
            $byte |= 0x80;
        }
        $bytes .= chr($byte);
    } while ($size !== 0);

    return $bytes;
};

$copyThenInsertDelta = static function (string $base, string $insert) use ($encodeDeltaSize): string {
    if (strlen($base) > 255) {
        throw new RuntimeException('Test helper only encodes one-byte copy sizes');
    }

    return $encodeDeltaSize(strlen($base))
        . $encodeDeltaSize(strlen($base) + strlen($insert))
        . chr(0x80 | 0x10)
        . chr(strlen($base))
        . chr(strlen($insert))
        . $insert;
};

$captureWarnings = static function (callable $callback): array {
    $warnings = [];
    set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });
    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $warnings;
};

$runtimeExceptionMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }

    throw new RuntimeException('Expected RuntimeException was not thrown');
};

$invalidArgumentMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    throw new RuntimeException('Expected InvalidArgumentException was not thrown');
};

$buildRawPack = static function (string $entryBytes): string {
    $prefix = 'PACK' . pack('N2', 2, 1) . $entryBytes;

    return $prefix . hex2bin(hash('sha1', $prefix));
};

$buildThinWordPressBlobs = static function (): array {
    $stableRows = '';
    for ($i = 0; $i < 80; $i++) {
        $stableRows .= hash('sha1', 'wordpress-thin-repair-row-' . $i) . "\n";
    }

    return [
        new GitObject('blob', "wp_posts export\n{$stableRows}post_status=draft\nchecksum=old\n"),
        new GitObject('blob', "wp_posts export\n{$stableRows}post_status=publish\nchecksum=new\n"),
    ];
};

return [
    'parses pack data header and verifies checksum' => static function (TestRunner $t) use ($buildPackFixture): void {
        [$packBytes, , , $checksum] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => 'hello pack'],
        ]);
        $pack = PackData::fromBytes($packBytes);
        $t->same(2, $pack->version());
        $t->same(1, $pack->count());
        $t->same($checksum, $pack->checksum());
        $t->same($checksum, $pack->verifyChecksum());
    },
    'parses sha256 pack data and verifies object ids with sha256 pack indexes' => static function (TestRunner $t) use ($buildPackFixture): void {
        [$packBytes, $indexBytes, $entries, $checksum] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => 'WordPress SHA-256 pack object'],
            ['type' => 'blob', 'typeId' => 3, 'body' => 'WordPress SHA-256 media object'],
        ], 'sha256');

        $pack = PackData::fromBytes($packBytes, 'sha256');
        $index = PackIndex::fromBytes($indexBytes, 'sha256');
        $object = $pack->readObject($index, strtoupper($entries[0]['oid']));

        $t->same('sha256', $pack->objectHash());
        $t->same(32, $pack->hashBytes());
        $t->same($checksum, $pack->checksum());
        $t->same($checksum, $pack->verifyChecksum());
        $t->same('sha256', $index->objectHash());
        $t->same($entries[0]['oid'], $object->oid('sha256'));
        $t->same($entries[0]['body'], $object->body);
        $t->same(['type' => 'blob', 'size' => strlen($entries[1]['body']), 'numDeltas' => 0], $pack->readObjectHeader($index, $entries[1]['oid']));
    },
    'reads non-delta blob and commit objects by pack-index offset' => static function (TestRunner $t) use ($buildPackFixture): void {
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'commit', 'typeId' => 1, 'body' => "tree e90926b07092bccb7bf7da445fae6ffdfacf3eae\n\nInitial commit\n"],
            ['type' => 'blob', 'typeId' => 3, 'body' => str_repeat('WordPress pack data ', 10)],
        ]);
        $pack = PackData::fromBytes($packBytes);
        $index = PackIndex::fromBytes($indexBytes);

        foreach ($entries as $entry) {
            $object = $pack->readObject($index, $entry['oid']);
            $t->same($entry['type'], $object->type);
            $t->same($entry['body'], $object->body);
            $t->same($entry['oid'], $object->oid());
        }
    },
    'parses multi-byte entry size headers' => static function (TestRunner $t) use ($buildPackFixture): void {
        [$packBytes, , $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => str_repeat('x', 200)],
        ]);
        $entry = PackData::fromBytes($packBytes)->entryAtOffset($entries[0]['offset']);
        $t->same('blob', $entry->kind);
        $t->same(200, $entry->decompressedSize);
        $t->true($entry->headerSize > 1, 'large entries should use a multi-byte size header');
    },
    'rejects bad pack data and unsupported delta resolution' => static function (TestRunner $t) use ($buildPackFixture): void {
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => 'base'],
        ]);
        $t->throws(InvalidArgumentException::class, static fn () => PackData::fromBytes('not a pack'));
        $t->throws(InvalidArgumentException::class, static fn () => PackData::fromBytes('PACK' . pack('N2', 9, 0) . str_repeat("\0", 20)));

        $badChecksum = substr($packBytes, 0, -1) . "\0";
        $t->throws(RuntimeException::class, static fn () => PackData::fromBytes($badChecksum)->verifyChecksum());

        $pack = PackData::fromBytes($packBytes);
        $wrongIndex = PackIndex::fromBytes(str_replace(hex2bin($pack->checksum()), str_repeat("\0", 20), $indexBytes));
        $t->throws(RuntimeException::class, static fn () => $pack->readObject($wrongIndex, $entries[0]['oid']));

        $deltaPack = 'PACK' . pack('N2', 2, 1) . chr((6 << 4) | 4) . chr(1) . gzcompress('delt') . str_repeat("\0", 20);
        $delta = PackData::fromBytes($deltaPack)->entryAtOffset(12);
        $t->same('ofs-delta', $delta->kind);
        $t->same(1, $delta->baseDistance);
        $t->throws(RuntimeException::class, static fn () => $delta->object());
    },
    'resolves ofs-delta packed blob objects by base offset' => static function (TestRunner $t) use ($buildPackFixture, $copyThenInsertDelta): void {
        $base = 'Hello ';
        $final = 'Hello WordPress';
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => $base],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $copyThenInsertDelta($base, 'WordPress'), 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => $final],
        ]);

        $object = PackData::fromBytes($packBytes)->readObject(PackIndex::fromBytes($indexBytes), $entries[1]['oid']);
        $t->same('blob', $object->type);
        $t->same($final, $object->body);
        $t->same($entries[1]['oid'], $object->oid());
    },
    'resolves ref-delta packed blob objects by base object id' => static function (TestRunner $t) use ($buildPackFixture, $copyThenInsertDelta): void {
        $base = 'WordPress';
        $final = 'WordPress import';
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => $base],
            ['type' => 'ref-delta', 'typeId' => 7, 'body' => $copyThenInsertDelta($base, ' import'), 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => $final],
        ]);

        $object = PackData::fromBytes($packBytes)->readObject(PackIndex::fromBytes($indexBytes), $entries[1]['oid']);
        $t->same('blob', $object->type);
        $t->same($final, $object->body);
    },
    'resolves and repairs thin ref-delta packs with external bases' => static function (TestRunner $t) use ($buildThinWordPressBlobs): void {
        [$base, $target] = $buildThinWordPressBlobs();
        $thin = PackBuilder::buildWithRefDeltas([$target], [$base]);
        $pack = PackData::fromBytes($thin->packBytes());
        $index = PackIndex::fromBytes($thin->indexBytes());

        $t->same(true, $thin->isThin());
        $t->throws(RuntimeException::class, static fn () => $pack->readObject($index, $target->oid()));

        $read = $pack->readObjectWithExternalBases($index, $target->oid(), [$base->oid() => $base]);
        $t->same('blob', $read->type);
        $t->same($target->body, $read->body);
        $t->throws(RuntimeException::class, static fn () => $pack->readObjectWithExternalBases($index, $target->oid(), []));
        $t->throws(InvalidArgumentException::class, static fn () => $pack->readObjectWithExternalBases($index, $target->oid(), [$base->oid() => $target]));

        $repaired = $pack->repairThinPack($index, [$base->oid() => $base]);
        $repairedPack = PackData::fromBytes($repaired->packBytes());
        $repairedIndex = PackIndex::fromBytes($repaired->indexBytes());
        $entries = $repaired->entries();
        $repairedTarget = $repairedPack->readObject($repairedIndex, $target->oid());

        $t->same(false, $repaired->isThin());
        $t->same(true, $repaired->hasDeltaEntries());
        $t->same(2, $repairedPack->count());
        $t->same($base->oid(), $entries[0]['oid']);
        $t->same('whole', $entries[0]['storage']);
        $t->same($target->oid(), $entries[1]['oid']);
        $t->same('ofs-delta', $entries[1]['storage']);
        $t->same($base->oid(), $entries[1]['baseOid']);
        $t->same($target->body, $repairedTarget->body);
    },
    'carries external bases through ofs-delta chains' => static function (TestRunner $t) use ($buildPackFixture, $copyThenInsertDelta): void {
        $externalBase = new GitObject('blob', 'wp_posts baseline');
        $middle = 'wp_posts baseline staged';
        $final = 'wp_posts baseline staged published';
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            [
                'type' => 'ref-delta',
                'typeId' => 7,
                'body' => $copyThenInsertDelta($externalBase->body, ' staged'),
                'baseOid' => $externalBase->oid(),
                'finalType' => 'blob',
                'finalBody' => $middle,
            ],
            [
                'type' => 'ofs-delta',
                'typeId' => 6,
                'body' => $copyThenInsertDelta($middle, ' published'),
                'baseEntry' => 0,
                'finalType' => 'blob',
                'finalBody' => $final,
            ],
        ]);
        $pack = PackData::fromBytes($packBytes);
        $index = PackIndex::fromBytes($indexBytes);

        $t->throws(RuntimeException::class, static fn () => $pack->readObject($index, $entries[1]['oid']));
        $object = $pack->readObjectWithExternalBases($index, $entries[1]['oid'], [$externalBase->oid() => $externalBase]);
        $t->same('blob', $object->type);
        $t->same($final, $object->body);

        $repaired = $pack->repairThinPack($index, [$externalBase->oid() => $externalBase]);
        $repairedPack = PackData::fromBytes($repaired->packBytes());
        $repairedIndex = PackIndex::fromBytes($repaired->indexBytes());
        $t->same(false, $repaired->isThin());
        $t->same(3, $repairedPack->count());
        $t->same($final, $repairedPack->readObject($repairedIndex, $entries[1]['oid'])->body);
    },
    'resolves packed delta headers through in-pack chains' => static function (TestRunner $t) use ($buildPackFixture, $copyThenInsertDelta): void {
        $base = "wp_posts export\n" . str_repeat('stable-row' . "\n", 6) . "status=draft\n";
        $middle = $base . "review=ready\n";
        $final = $middle . "status=publish\n";
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => $base],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $copyThenInsertDelta($base, "review=ready\n"), 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => $middle],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $copyThenInsertDelta($middle, "status=publish\n"), 'baseEntry' => 1, 'finalType' => 'blob', 'finalBody' => $final],
        ]);
        $pack = PackData::fromBytes($packBytes);
        $index = PackIndex::fromBytes($indexBytes);

        $t->same(['type' => 'blob', 'size' => strlen($base), 'numDeltas' => 0], $pack->readObjectHeader($index, $entries[0]['oid']));
        $t->same(['type' => 'blob', 'size' => strlen($middle), 'numDeltas' => 1], $pack->readObjectHeader($index, $entries[1]['oid']));
        $t->same(['type' => 'blob', 'size' => strlen($final), 'numDeltas' => 2], $pack->readObjectHeader($index, $entries[2]['oid']));
        $t->same($final, $pack->readObject($index, $entries[2]['oid'])->body);
    },
    'resolves thin delta headers from external base metadata' => static function (TestRunner $t) use ($buildPackFixture, $copyThenInsertDelta, $encodeDeltaSize, $runtimeExceptionMessage): void {
        $externalBase = new GitObject('blob', 'wp_posts external baseline');
        $final = 'wp_posts external baseline staged';
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            [
                'type' => 'ref-delta',
                'typeId' => 7,
                'body' => $copyThenInsertDelta($externalBase->body, ' staged'),
                'baseOid' => $externalBase->oid(),
                'finalType' => 'blob',
                'finalBody' => $final,
            ],
        ]);
        $pack = PackData::fromBytes($packBytes);
        $index = PackIndex::fromBytes($indexBytes);

        $t->throws(RuntimeException::class, static fn () => $pack->readObjectHeader($index, $entries[0]['oid']));
        $t->same(
            ['type' => 'blob', 'size' => strlen($final), 'numDeltas' => 1],
            $pack->readObjectHeaderWithExternalBases($index, $entries[0]['oid'], [$externalBase->oid() => $externalBase])
        );
        $t->same(
            ['type' => 'blob', 'size' => strlen($final), 'numDeltas' => 4],
            $pack->readObjectHeaderWithExternalBaseResolver(
                $index,
                $entries[0]['oid'],
                static fn (string $oid): ?array => $oid === $externalBase->oid()
                    ? ['type' => 'blob', 'size' => strlen($externalBase->body), 'numDeltas' => 3]
                    : null,
            )
        );

        $largePaddedDelta = $encodeDeltaSize(strlen($externalBase->body)) . $encodeDeltaSize(1) . chr(0x90) . chr(1);
        $largePaddedDelta = str_pad($largePaddedDelta, 33, chr(0));
        [$paddedPackBytes, $paddedIndexBytes, $paddedEntries] = $buildPackFixture([
            [
                'type' => 'ref-delta',
                'typeId' => 7,
                'body' => $largePaddedDelta,
                'declaredSize' => 34,
                'baseOid' => $externalBase->oid(),
                'finalType' => 'blob',
                'finalBody' => substr($externalBase->body, 0, 1),
            ],
        ]);
        $paddedPack = PackData::fromBytes($paddedPackBytes);
        $paddedIndex = PackIndex::fromBytes($paddedIndexBytes);

        $t->same(
            ['type' => 'blob', 'size' => 1, 'numDeltas' => 1],
            $paddedPack->readObjectHeaderWithExternalBases($paddedIndex, $paddedEntries[0]['oid'], [$externalBase->oid() => $externalBase])
        );
        $t->same(
            'Pack entry decompressed size mismatch: expected 34, got 33',
            $runtimeExceptionMessage(static fn () => $paddedPack->readObjectWithExternalBases($paddedIndex, $paddedEntries[0]['oid'], [$externalBase->oid() => $externalBase]))
        );
    },
    'rejects corrupt delta instructions during object resolution' => static function (TestRunner $t) use ($buildPackFixture, $encodeDeltaSize): void {
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => 'base'],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $encodeDeltaSize(4) . $encodeDeltaSize(4) . chr(0), 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => 'base'],
        ]);

        $t->throws(RuntimeException::class, static fn () => PackData::fromBytes($packBytes)->readObject(PackIndex::fromBytes($indexBytes), $entries[1]['oid']));
    },
    'rejects malformed delta declared-size parity without php warnings' => static function (TestRunner $t) use ($buildPackFixture, $encodeDeltaSize, $captureWarnings): void {
        $base = new GitObject('blob', 'A');
        $copyOne = $encodeDeltaSize(1) . $encodeDeltaSize(1) . chr(0x90) . chr(1);

        [$longPackBytes, $longIndexBytes, $longEntries] = $buildPackFixture([
            [
                'type' => 'ref-delta',
                'typeId' => 7,
                'body' => $copyOne . chr(0),
                'declaredSize' => strlen($copyOne),
                'baseOid' => $base->oid(),
                'finalType' => 'blob',
                'finalBody' => 'A',
            ],
        ]);
        $longPack = PackData::fromBytes($longPackBytes);
        $longIndex = PackIndex::fromBytes($longIndexBytes);
        $longWarnings = $captureWarnings(static function () use ($t, $longPack, $longIndex, $longEntries, $base): void {
            $t->throws(RuntimeException::class, static fn () => $longPack->readObjectWithExternalBases($longIndex, $longEntries[0]['oid'], [$base->oid() => $base]));
        });

        [$shortPackBytes, $shortIndexBytes, $shortEntries] = $buildPackFixture([
            [
                'type' => 'ref-delta',
                'typeId' => 7,
                'body' => $copyOne,
                'declaredSize' => strlen($copyOne) + 1,
                'baseOid' => $base->oid(),
                'finalType' => 'blob',
                'finalBody' => 'A',
            ],
        ]);
        $shortPack = PackData::fromBytes($shortPackBytes);
        $shortIndex = PackIndex::fromBytes($shortIndexBytes);
        $shortWarnings = $captureWarnings(static function () use ($t, $shortPack, $shortIndex, $shortEntries, $base): void {
            $t->throws(RuntimeException::class, static fn () => $shortPack->readObjectWithExternalBases($shortIndex, $shortEntries[0]['oid'], [$base->oid() => $base]));
        });

        [$mismatchedBasePackBytes, $mismatchedBaseIndexBytes, $mismatchedBaseEntries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => 'A', 'declaredSize' => 2],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $copyOne, 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => 'A'],
        ]);
        $mismatchedBasePack = PackData::fromBytes($mismatchedBasePackBytes);
        $mismatchedBaseIndex = PackIndex::fromBytes($mismatchedBaseIndexBytes);
        $mismatchedBaseWarnings = $captureWarnings(static function () use ($t, $mismatchedBasePack, $mismatchedBaseIndex, $mismatchedBaseEntries): void {
            $t->throws(RuntimeException::class, static fn () => $mismatchedBasePack->readObject($mismatchedBaseIndex, $mismatchedBaseEntries[1]['oid']));
        });

        $t->same([], $longWarnings);
        $t->same([], $shortWarnings);
        $t->same([], $mismatchedBaseWarnings);
    },
    'rejects oversized delta headers before php integer wraparound' => static function (TestRunner $t) use ($buildPackFixture, $encodeDeltaSize, $runtimeExceptionMessage): void {
        $base = new GitObject('blob', 'A');
        $oversized = str_repeat(chr(0x80), 9) . chr(0x01);

        [$oversizedResultPackBytes, $oversizedResultIndexBytes, $oversizedResultEntries] = $buildPackFixture([
            [
                'type' => 'ref-delta',
                'typeId' => 7,
                'body' => $encodeDeltaSize(1) . $oversized,
                'baseOid' => $base->oid(),
                'finalType' => 'blob',
                'finalBody' => 'B',
            ],
        ]);
        $oversizedResultPack = PackData::fromBytes($oversizedResultPackBytes);
        $oversizedResultIndex = PackIndex::fromBytes($oversizedResultIndexBytes);
        $resultMessage = $runtimeExceptionMessage(static fn () => $oversizedResultPack->readObjectWithExternalBases(
            $oversizedResultIndex,
            $oversizedResultEntries[0]['oid'],
            [$base->oid() => $base],
        ));

        [$oversizedBasePackBytes, $oversizedBaseIndexBytes, $oversizedBaseEntries] = $buildPackFixture([
            [
                'type' => 'ref-delta',
                'typeId' => 7,
                'body' => $oversized . $encodeDeltaSize(1) . chr(0x90) . chr(1),
                'baseOid' => $base->oid(),
                'finalType' => 'blob',
                'finalBody' => 'B',
            ],
        ]);
        $oversizedBasePack = PackData::fromBytes($oversizedBasePackBytes);
        $oversizedBaseIndex = PackIndex::fromBytes($oversizedBaseIndexBytes);
        $baseMessage = $runtimeExceptionMessage(static fn () => $oversizedBasePack->readObjectWithExternalBases(
            $oversizedBaseIndex,
            $oversizedBaseEntries[0]['oid'],
            [$base->oid() => $base],
        ));

        $t->same('Delta header size exceeds platform integer range', $resultMessage);
        $t->same('Delta header size exceeds platform integer range', $baseMessage);
    },
    'rejects delta instructions that overrun the declared result buffer' => static function (TestRunner $t) use ($buildPackFixture, $encodeDeltaSize, $runtimeExceptionMessage): void {
        $base = str_repeat('A', 256);
        $copyTwoIntoOne = $encodeDeltaSize(strlen($base)) . $encodeDeltaSize(1) . chr(0x90) . chr(2);
        [$copyPackBytes, $copyIndexBytes, $copyEntries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => $base],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $copyTwoIntoOne, 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => 'AA'],
        ]);
        $copyMessage = $runtimeExceptionMessage(static fn () => PackData::fromBytes($copyPackBytes)->readObject(
            PackIndex::fromBytes($copyIndexBytes),
            $copyEntries[1]['oid'],
        ));

        $insertTwoIntoOne = $encodeDeltaSize(0) . $encodeDeltaSize(1) . chr(2) . 'BC';
        [$insertPackBytes, $insertIndexBytes, $insertEntries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => ''],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $insertTwoIntoOne, 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => 'BC'],
        ]);
        $insertMessage = $runtimeExceptionMessage(static fn () => PackData::fromBytes($insertPackBytes)->readObject(
            PackIndex::fromBytes($insertIndexBytes),
            $insertEntries[1]['oid'],
        ));

        $shortDelta = $encodeDeltaSize(1) . $encodeDeltaSize(2) . chr(1) . 'x';
        [$shortPackBytes, $shortIndexBytes, $shortEntries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => 'a'],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $shortDelta, 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => 'x'],
        ]);
        $shortMessage = $runtimeExceptionMessage(static fn () => PackData::fromBytes($shortPackBytes)->readObject(
            PackIndex::fromBytes($shortIndexBytes),
            $shortEntries[1]['oid'],
        ));

        $t->same('Delta copy exceeds declared result size', $copyMessage);
        $t->same('Delta insert exceeds declared result size', $insertMessage);
        $t->same('Delta instructions produced fewer bytes than promised', $shortMessage);
    },
    'rejects malformed delta entry metadata before size wraparound' => static function (TestRunner $t) use ($buildRawPack, $invalidArgumentMessage): void {
        $nonCanonicalBlob = chr((3 << 4) | 0x80) . chr(0x00) . gzcompress('');
        $nonCanonicalMessage = $invalidArgumentMessage(static fn () => PackData::fromBytes($buildRawPack($nonCanonicalBlob))->entryAtOffset(12));

        $overflowingBlob = chr((3 << 4) | 0x80) . str_repeat(chr(0xff), 9) . chr(0x7f) . gzcompress('');
        $overflowingSizeMessage = $invalidArgumentMessage(static fn () => PackData::fromBytes($buildRawPack($overflowingBlob))->entryAtOffset(12));

        $overflowingOfsDelta = chr(6 << 4) . str_repeat(chr(0xff), 9) . chr(0x7f) . gzcompress('');
        $overflowingDistanceMessage = $invalidArgumentMessage(static fn () => PackData::fromBytes($buildRawPack($overflowingOfsDelta))->entryAtOffset(12));

        $t->same('Pack entry size header uses a non-canonical encoding', $nonCanonicalMessage);
        $t->same('Pack entry size header overflowed while decoding', $overflowingSizeMessage);
        $t->same('Ofs-delta base distance overflowed while decoding', $overflowingDistanceMessage);
    },
    'wordpress fixture reads compacted commit blob and delta objects without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-pack-data.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-pack-data.php';
        $pack = PackData::fromBytes($fixture['packBytes']);
        $index = PackIndex::fromBytes($fixture['indexBytes']);

        $t->same($fixture['packChecksum'], $pack->verifyChecksum());
        $commit = $pack->readObject($index, $fixture['objects'][0]['oid']);
        $blob = $pack->readObject($index, $fixture['objects'][1]['oid']);
        $deltaBlob = $pack->readObject($index, $fixture['objects'][2]['oid']);
        $t->same('commit', $commit->type);
        $t->contains('Import WordPress content', $commit->body);
        $t->same('blob', $blob->type);
        $t->contains('wp_posts export', $blob->body);
        $t->same('blob', $deltaBlob->type);
        $t->contains('reconstructed packed edit', $deltaBlob->body);
        $t->same(true, $summary['strictDeclaredSizeGuard']);
        $t->same(true, $summary['oversizedDeltaHeaderGuard']);
        $t->same(true, $summary['deltaResultBufferGuard']);
        $t->same(true, $summary['packEntryMetadataGuard']);
        $t->same(['type' => 'blob', 'size' => strlen($deltaBlob->body), 'numDeltas' => 1], $summary['deltaHeaderProbe']);
    },
    'wordpress fixture resolves and repairs thin content packs' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-thin-pack-repair.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-thin-pack-repair.php';
        $thinPack = PackData::fromBytes($fixture['thinPackBytes']);
        $repairedPack = PackData::fromBytes($fixture['repairedPackBytes']);
        $repairedIndex = PackIndex::fromBytes($fixture['repairedIndexBytes']);
        $updatedBlob = $repairedPack->readObject($repairedIndex, $fixture['updatedBlob']);

        $t->same(true, $fixture['thin']);
        $t->same('ref-delta', $fixture['thinEntries'][0]['storage']);
        $t->same(1, $thinPack->count());
        $t->same(false, $fixture['repairedThin']);
        $t->same(true, $fixture['repairedHasDelta']);
        $t->same(2, $repairedPack->count());
        $t->same('ofs-delta', $fixture['repairedEntries'][1]['storage']);
        $t->same($fixture['baseBlob'], $fixture['repairedEntries'][1]['baseOid']);
        $t->same($fixture['resolvedBody'], $updatedBlob->body);
        $t->contains('post_status=publish', $updatedBlob->body);
        $t->same($fixture['repairedThin'], $summary['repairedThin']);
        $t->same($fixture['repairedHasDelta'], $summary['repairedHasDelta']);
        $t->same('ofs-delta', $summary['repairedDeltaStorage']);
    },
];
