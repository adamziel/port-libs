<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitPacketLine;

$assertThrowsMessage = static function (TestRunner $t, string $expectedMessage, callable $callback): void {
    try {
        $callback();
    } catch (Throwable $throwable) {
        $t->same($expectedMessage, $throwable->getMessage());
        return;
    }

    throw new RuntimeException('Expected exception was not thrown');
};

$flushStop = [GitPacketLine::KIND_FLUSH];
$packet = static fn (string $data): string => GitPacketLine::encodeData($data);
$exhaustReader = static function ($reader): int {
    $count = 0;
    while ($reader->readLine() !== null) {
        $count++;
    }

    return $count;
};

return [
    'upstream gix-packetline write each_write_results_in_one_line' => static function (TestRunner $t): void {
        $t->same('0009hello000aworld!', GitPacketLine::encodeWrites(['hello', 'world!']));
    },
    'upstream gix-packetline write write_text_and_write_binary' => static function (TestRunner $t): void {
        $buf = GitPacketLine::encodeWrite('hello', true) . GitPacketLine::encodeWrite('world');

        $t->same("000ahello\n0009world", $buf);
    },
    'upstream gix-packetline write huge_writes_are_split_into_lines' => static function (TestRunner $t): void {
        $buf = GitPacketLine::encodeWrite(str_repeat("\0", GitPacketLine::MAX_DATA_LENGTH * 2));

        $t->same(GitPacketLine::MAX_LINE_LENGTH * 2, strlen($buf));
        $t->same('fff0', substr($buf, 0, 4));
        $t->same('fff0', substr($buf, GitPacketLine::MAX_LINE_LENGTH, 4));
    },
    'upstream gix-packetline write empty_writes_fail_with_error' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            "empty packet lines are not permitted as '0004' is invalid",
            static fn () => GitPacketLine::encodeWrite('')
        );
    },
    'upstream gix-packetline read streaming_peek_iter peek_follows_read_line_delimiter_logic' => static function (TestRunner $t) use ($flushStop): void {
        $reader = GitPacketLine::reader('0005a00000005b', $flushStop);

        $t->same(GitPacketLine::dataLine('a'), $reader->peekLine());
        $t->same(GitPacketLine::dataLine('a'), $reader->peekLine());
        $t->same(GitPacketLine::dataLine('a'), $reader->readLine());
        $t->same(null, $reader->peekLine());
        $t->same(GitPacketLine::flushLine(), $reader->stoppedAt());
        $t->same(null, $reader->peekLine());

        $reader->reset();
        $t->same(GitPacketLine::dataLine('b'), $reader->peekLine());
    },
    'upstream gix-packetline read streaming_peek_iter peek_follows_read_line_err_logic' => static function (TestRunner $t) use ($assertThrowsMessage, $flushStop): void {
        $reader = GitPacketLine::reader('0005a0009ERR e0000', $flushStop);
        $reader->failOnErrLines();

        $t->same(GitPacketLine::dataLine('a'), $reader->peekLine());
        $t->same(GitPacketLine::dataLine('a'), $reader->readLine());
        $assertThrowsMessage($t, 'e', static fn () => $reader->peekLine());
        $t->same(null, $reader->peekLine());
        $t->same(null, $reader->stoppedAt());

        $reader->reset();
        $t->same(null, $reader->peekLine());
        $t->same(GitPacketLine::flushLine(), $reader->stoppedAt());
    },
    'upstream gix-packetline read streaming_peek_iter peek_eof_is_none' => static function (TestRunner $t) use ($flushStop): void {
        $reader = GitPacketLine::reader('0005a0009ERR e0000', $flushStop);
        $reader->failOnErrLines(false);

        $t->same(GitPacketLine::dataLine('a'), $reader->peekLine());
        $t->same(GitPacketLine::dataLine('a'), $reader->readLine());
        $t->same(GitPacketLine::dataLine('ERR e'), $reader->peekLine());
        $t->same(GitPacketLine::dataLine('ERR e'), $reader->readLine());
        $t->same(null, $reader->peekLine());
        $t->same(GitPacketLine::flushLine(), $reader->stoppedAt());
    },
    'upstream gix-packetline read streaming_peek_iter peek_non_data' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $reader = GitPacketLine::reader('000000010002', [GitPacketLine::KIND_RESPONSE_END]);

        $t->same(GitPacketLine::flushLine(), $reader->readLine());
        $t->same(GitPacketLine::delimiterLine(), $reader->readLine());
        $reader->resetWith([GitPacketLine::KIND_FLUSH]);
        $t->same(GitPacketLine::responseEndLine(), $reader->readLine());
        $assertThrowsMessage($t, 'Unexpected EOF', static fn () => $reader->peekLine());
        $assertThrowsMessage($t, 'Unexpected EOF', static fn () => $reader->peekLine());
        $t->same(null, $reader->stoppedAt());
    },
    'upstream gix-packetline read streaming_peek_iter fail_on_err_lines' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $input = '0001' . GitPacketLine::encodeError('e') . '0002';
        $reader = GitPacketLine::reader($input);

        $t->same(GitPacketLine::delimiterLine(), $reader->readLine());
        $t->same(GitPacketLine::dataLine('ERR e'), $reader->readLine());

        $reader = GitPacketLine::reader($input);
        $reader->failOnErrLines();
        $t->same(GitPacketLine::delimiterLine(), $reader->readLine());
        $assertThrowsMessage($t, 'e', static fn () => $reader->readLine());
        $t->same(null, $reader->readLine());

        $reader->replace($input);
        $t->same(GitPacketLine::delimiterLine(), $reader->readLine());
        $t->same(GitPacketLine::dataLine('ERR e'), $reader->readLine());
    },
    'upstream gix-packetline read streaming_peek_iter oversized_packet_lengths_are_reported_instead_of_panicking' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $reader = GitPacketLine::reader("ffff\n");

        $assertThrowsMessage(
            $t,
            'The data received claims to be larger than the maximum allowed size: got 65535, exceeds 65516',
            static fn () => $reader->readLine()
        );
    },
    'upstream gix-packetline read streaming_peek_iter large fixture peek and advancement accounting' => static function (TestRunner $t) use ($assertThrowsMessage, $exhaustReader, $flushStop): void {
        $fixture = dirname(__DIR__, 3) . '/.upstream-cache/gitoxide/gix-packetline/tests/fixtures/v1/fetch/01-many-refs.response';
        $bytes = (string) file_get_contents($fixture);
        $firstLine = GitPacketLine::dataLine("7814e8a05a59c0cf5fb186661d1551c75d1299b5 HEAD\0multi_ack thin-pack side-band side-band-64k ofs-delta shallow deepen-since deepen-not deepen-relative no-progress include-tag multi_ack_detailed symref=HEAD:refs/heads/master object-format=sha1 agent=git/2.28.0\n");

        $reader = GitPacketLine::reader($bytes, $flushStop);
        $t->same($firstLine, $reader->peekLine());
        $t->same($firstLine, $reader->peekLine());
        $t->same($firstLine, $reader->readLine());
        $t->same(
            GitPacketLine::dataLine("7814e8a05a59c0cf5fb186661d1551c75d1299b5 refs/heads/master\n"),
            $reader->readLine()
        );
        $t->same(
            GitPacketLine::dataLine("7814e8a05a59c0cf5fb186661d1551c75d1299b5 refs/remotes/origin/HEAD\n"),
            $reader->peekLine()
        );
        $t->same(1559, $exhaustReader($reader));
        $t->same(GitPacketLine::flushLine(), $reader->stoppedAt());

        $reader = GitPacketLine::reader($bytes . $bytes, $flushStop);
        $t->same($firstLine, $reader->readLine());
        $t->same(1560, $exhaustReader($reader));
        $reader->reset();
        $t->same(1561, $exhaustReader($reader));
        $reader->reset();
        $assertThrowsMessage($t, 'Unexpected EOF', static fn () => $reader->readLine());
    },
    'upstream gix-packetline read streaming_peek_iter read_from_file_and_reader_advancement' => static function (TestRunner $t) use ($assertThrowsMessage, $flushStop, $packet): void {
        $part = $packet('first') . GitPacketLine::encodeFlush();
        $reader = GitPacketLine::reader($part . $part, $flushStop);

        $t->same(GitPacketLine::dataLine('first'), $reader->readLine());
        $t->same(null, $reader->readLine());
        $t->same(GitPacketLine::flushLine(), $reader->stoppedAt());

        $reader->reset();
        $t->same(GitPacketLine::dataLine('first'), $reader->readLine());
        $t->same(null, $reader->readLine());

        $reader->reset();
        $assertThrowsMessage($t, 'Unexpected EOF', static fn () => $reader->readLine());
    },
    'upstream gix-packetline read sideband read_line_trait_method_reads_one_packet_line_at_a_time' => static function (TestRunner $t) use ($flushStop, $packet): void {
        $reader = GitPacketLine::reader(
            $packet("one\n")
            . $packet("two\n")
            . GitPacketLine::encodeFlush()
            . $packet("NAK\n")
            . GitPacketLine::encodeBand(GitPacketLine::CHANNEL_PROGRESS, "progress\n")
            . GitPacketLine::encodeBand(GitPacketLine::CHANNEL_DATA, '&')
            . GitPacketLine::encodeFlush(),
            $flushStop
        );

        $t->same("one\n", $reader->readDataLine());
        $t->same("two\n", $reader->readDataLine());
        $t->same(null, $reader->readDataLine());
        $t->same(null, $reader->readDataLine());
        $t->same(GitPacketLine::flushLine(), $reader->stoppedAt());

        $reader->reset();
        $t->same("NAK\n", $reader->readDataLine());

        $progress = [];
        $data = $reader->readAllDataWithSidebands(static function (bool $isError, string $message) use (&$progress): bool {
            $progress[] = [$isError, $message];
            return true;
        });

        $t->same('&', $data);
        $t->same([[false, "progress\n"]], $progress);
    },
    'upstream gix-packetline read sideband readline_reads_one_packet_line_at_a_time' => static function (TestRunner $t) use ($flushStop, $packet): void {
        $reader = GitPacketLine::reader(
            $packet("one\n") . $packet("two\n") . GitPacketLine::encodeFlush() . $packet("NAK\n"),
            $flushStop
        );

        $t->same("one\n", $reader->readDataLine());
        $t->same("two\n", $reader->readDataLine());
        $t->same(null, $reader->readDataLine());

        $reader->reset();
        $t->same("NAK\n", $reader->readDataLine());
    },
    'upstream gix-packetline read sideband peek_past_an_actual_eof_is_an_error' => static function (TestRunner $t) use ($assertThrowsMessage, $packet): void {
        $reader = GitPacketLine::reader($packet('ERR e'));

        $t->same('ERR e', $reader->peekDataLine());
        $t->same('ERR e', $reader->readDataLine());
        $assertThrowsMessage($t, 'Unexpected EOF', static fn () => $reader->peekDataLine());
    },
    'upstream gix-packetline read sideband peek_past_a_delimiter_is_no_error' => static function (TestRunner $t) use ($flushStop, $packet): void {
        $reader = GitPacketLine::reader($packet('hello') . GitPacketLine::encodeFlush(), $flushStop);

        $t->same('hello', $reader->peekDataLine());
        $t->same('hello', $reader->readDataLine());
        $t->same(null, $reader->peekDataLine());
    },
    'upstream gix-packetline read sideband handling_of_err_lines' => static function (TestRunner $t) use ($assertThrowsMessage, $flushStop): void {
        $reader = GitPacketLine::reader(
            GitPacketLine::encodeError('e') . GitPacketLine::encodeError('x') . GitPacketLine::encodeFlush()
        );
        $reader->failOnErrLines();

        $assertThrowsMessage($t, 'e', static fn () => $reader->readDataLine());
        $t->same(null, $reader->readDataLine());

        $reader->resetWith($flushStop);
        $assertThrowsMessage($t, 'x', static fn () => $reader->readDataLine());
        $t->same(null, $reader->stoppedAt());
    },
    'upstream gix-packetline read sideband progress_extraction_without_pack_runtime' => static function (TestRunner $t): void {
        $reader = GitPacketLine::reader(
            GitPacketLine::encodeBand(GitPacketLine::CHANNEL_PROGRESS, 'Counting objects')
            . GitPacketLine::encodeBand(GitPacketLine::CHANNEL_DATA, 'PACK')
            . GitPacketLine::encodeBand(GitPacketLine::CHANNEL_ERROR, 'remote warning')
            . GitPacketLine::encodeFlush(),
            [GitPacketLine::KIND_FLUSH]
        );

        $progress = [];
        $data = $reader->readAllDataWithSidebands(static function (bool $isError, string $message) use (&$progress): bool {
            $progress[] = [$isError, $message];
            return true;
        });

        $t->same('PACK', $data);
        $t->same([[false, 'Counting objects'], [true, 'remote warning']], $progress);
    },
];
