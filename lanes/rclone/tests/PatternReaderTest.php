<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\PatternReader;

$readAll = static function (PatternReader $reader, int $chunkSize = 64): string {
    $bytes = '';
    while (($chunk = $reader->read($chunkSize)) !== '') {
        $bytes .= $chunk;
    }

    return $bytes;
};

return [
    'pattern reader maps upstream zero length and ten byte streams' => static function (TestRunner $t) use ($readAll): void {
        $empty = new PatternReader(0);
        $t->same('', $readAll($empty));
        $t->same('', $empty->read(1));

        $reader = new PatternReader(10);
        $t->same("\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09", $readAll($reader));
        $t->same('', $reader->read(1));
    },
    'pattern reader maps upstream modulo byte pattern and seek behavior' => static function (TestRunner $t) use ($readAll): void {
        $reader = new PatternReader(1024);
        $bytes = $readAll($reader, 128);

        $t->same(1024, strlen($bytes));
        for ($i = 0; $i < strlen($bytes); $i++) {
            $t->same(chr($i % 251), $bytes[$i]);
        }

        $t->same(1, $reader->seek(1, SEEK_SET));
        $t->same(substr($bytes, 1, 10), $reader->read(10));
        $t->same(20, $reader->seek(9, SEEK_CUR));
        $t->same(substr($bytes, 20, 10), $reader->read(10));
        $t->same(1000, $reader->seek(-24, SEEK_END));
        $t->same(substr($bytes, 1000, 10), $reader->read(10));
    },
    'pattern reader reports upstream seek errors while allowing past end positions' => static function (TestRunner $t): void {
        $reader = new PatternReader(10);

        $t->same(99, $reader->seek(99, SEEK_SET));
        $t->same('', $reader->read(1));

        try {
            $reader->seek(1, 400);
            throw new RuntimeException('Expected invalid whence error');
        } catch (InvalidArgumentException $throwable) {
            $t->same(PatternReader::ERR_INVALID_WHENCE, $throwable->getMessage());
        }

        try {
            $reader->seek(-1, SEEK_SET);
            throw new RuntimeException('Expected negative position error');
        } catch (RuntimeException $throwable) {
            $t->same(PatternReader::ERR_NEGATIVE_POSITION, $throwable->getMessage());
        }
    },
    'pattern reader generates deterministic wordpress artifact bodies' => static function (TestRunner $t) use ($readAll): void {
        $bytes = $readAll(new PatternReader(260), 37);
        $provider = new MemoryProvider();
        $provider->put('wp-content/uploads/generated/pattern.bin', $bytes);

        $t->same(260, strlen($bytes));
        $t->same('f8f9fa000102030405060708', bin2hex(substr($bytes, 248, 12)));
        $t->same('313c0b25eb82ee41f7b269751202713e', hash('md5', $provider->get('wp-content/uploads/generated/pattern.bin')));
    },
];
