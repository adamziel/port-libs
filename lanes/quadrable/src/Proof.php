<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class Proof
{
    public const ENCODING_HASHED_KEYS = 0;
    public const ENCODING_FULL_KEYS = 1;

    /**
     * @param list<ProofStrand> $strands
     * @param list<ProofCommand> $commands
     */
    public function __construct(
        public readonly array $strands,
        public readonly array $commands
    ) {
    }

    public function encode(int $encodingType = self::ENCODING_HASHED_KEYS): string
    {
        if ($encodingType !== self::ENCODING_HASHED_KEYS && $encodingType !== self::ENCODING_FULL_KEYS) {
            throw new \InvalidArgumentException('unexpected proof encoding type');
        }

        $output = chr($encodingType);

        foreach ($this->strands as $strand) {
            $output .= chr($strand->type);
            $output .= chr($strand->depth);

            if ($strand->type === ProofStrand::LEAF) {
                if ($encodingType === self::ENCODING_HASHED_KEYS) {
                    $output .= self::encodeKeyHash($strand->keyHash);
                } else {
                    if ($strand->key === '') {
                        throw new \RuntimeException('FullKeys specified in proof encoding, but key not available');
                    }
                    $output .= self::encodeVarInt(strlen($strand->key));
                    $output .= $strand->key;
                }

                $output .= self::encodeVarInt(strlen($strand->value));
                $output .= $strand->value;
                continue;
            }

            if ($strand->type === ProofStrand::WITNESS_LEAF) {
                $output .= self::encodeKeyHash($strand->keyHash);
                $output .= self::hashBytes($strand->value);
                continue;
            }

            if ($strand->type === ProofStrand::WITNESS_EMPTY) {
                $output .= self::encodeKeyHash($strand->keyHash);
                continue;
            }

            if ($strand->type === ProofStrand::WITNESS) {
                $output .= self::encodeKeyHash($strand->keyHash);
                $output .= self::hashBytes($strand->value);
                continue;
            }

            throw new \RuntimeException('unrecognized ProofStrand::Type when encoding proof: ' . $strand->type);
        }

        $output .= chr(ProofStrand::INVALID);

        if ($this->strands === []) {
            return $output;
        }

        $currentPosition = count($this->strands) - 1;
        $hashQueue = [];

        $flushHashQueue = function () use (&$hashQueue, &$output): void {
            if ($hashQueue === []) {
                return;
            }
            if (count($hashQueue) > 6) {
                throw new \RuntimeException('proof hash queue overflow');
            }

            $bits = 0;
            foreach ($hashQueue as $index => $command) {
                if ($command->operation === ProofCommand::HASH_PROVIDED) {
                    $bits |= 1 << $index;
                }
            }

            $bits = (($bits << 1) | 1) << (6 - count($hashQueue));
            $output .= chr($bits);

            foreach ($hashQueue as $command) {
                if ($command->operation === ProofCommand::HASH_PROVIDED) {
                    $output .= self::hashBytes($command->hash);
                }
            }

            $hashQueue = [];
        };

        foreach ($this->commands as $command) {
            if ($command->nodeOffset >= count($this->strands)) {
                throw new \RuntimeException('nodeOffset in cmd is out of range');
            }

            while ($command->nodeOffset !== $currentPosition) {
                $flushHashQueue();

                $delta = $command->nodeOffset - $currentPosition;
                if ($delta >= 1 && $delta < 64) {
                    $distance = min($delta, 32);
                    $output .= chr(0b1000_0000 | ($distance - 1));
                    $currentPosition += $distance;
                } elseif ($delta > -64 && $delta <= -1) {
                    $distance = min(abs($delta), 32);
                    $output .= chr(0b1010_0000 | ($distance - 1));
                    $currentPosition -= $distance;
                } else {
                    $logDistance = self::bitLength(abs($delta));
                    if ($delta > 0) {
                        $output .= chr(0b1100_0000 | ($logDistance - 7));
                        $currentPosition += 1 << ($logDistance - 1);
                    } else {
                        $output .= chr(0b1110_0000 | ($logDistance - 7));
                        $currentPosition -= 1 << ($logDistance - 1);
                    }
                }
            }

            if ($command->operation === ProofCommand::MERGE) {
                $flushHashQueue();
                $output .= chr(0);
                continue;
            }

            if ($command->operation !== ProofCommand::HASH_PROVIDED && $command->operation !== ProofCommand::HASH_EMPTY) {
                throw new \RuntimeException('unrecognized ProofCmd op: ' . $command->operation);
            }

            $hashQueue[] = $command;
            if (count($hashQueue) === 6) {
                $flushHashQueue();
            }
        }

        $flushHashQueue();

        return $output;
    }

    public function dumpText(): string
    {
        $output = 'ITEMS (' . count($this->strands) . "):\n";

        foreach ($this->strands as $index => $strand) {
            $strandType = match ($strand->type) {
                ProofStrand::LEAF => 'Leaf',
                ProofStrand::WITNESS_LEAF => 'WitnessLeaf',
                ProofStrand::WITNESS_EMPTY => 'WitnessEmpty',
                default => '?',
            };

            $output .= '  ITEM ' . $index . ': 0x' . $strand->keyHash . ")\n";
            $output .= '    ' . $strandType . '  depth=' . $strand->depth . "\n";

            if ($strand->type === ProofStrand::LEAF) {
                if ($strand->key !== '') {
                    $output .= '    Key: ' . $strand->key . "\n";
                }
                $output .= '    Val: ' . $strand->value . "\n";
            } elseif ($strand->type === ProofStrand::WITNESS_LEAF) {
                $output .= '    Val hash: 0x' . $strand->value . "\n";
            }
        }

        $output .= 'CMDS (' . count($this->commands) . "):\n";

        foreach ($this->commands as $index => $command) {
            $operation = match ($command->operation) {
                ProofCommand::HASH_EMPTY => 'HashEmpty',
                ProofCommand::HASH_PROVIDED => 'HashProvided',
                ProofCommand::MERGE => 'Merge',
                default => '?',
            };

            $output .= '  CMD ' . $index . ': ' . $operation . ' @ ' . $command->nodeOffset . "\n";

            if ($command->operation === ProofCommand::HASH_PROVIDED) {
                $output .= '    Sibling hash: 0x' . $command->hash . "\n";
            }
        }

        return $output;
    }

    public static function decode(string $encoded, ?HashTree $hashTree = null): self
    {
        $offset = 0;
        $hashTree ??= new HashTree();
        $encodingType = self::readByte($encoded, $offset);

        if ($encodingType !== self::ENCODING_HASHED_KEYS && $encodingType !== self::ENCODING_FULL_KEYS) {
            throw new \RuntimeException('unexpected proof encoding type: ' . $encodingType);
        }

        $strands = [];
        while (true) {
            $strandType = self::readByte($encoded, $offset);
            if ($strandType === ProofStrand::INVALID) {
                break;
            }

            $depth = self::readByte($encoded, $offset);

            if ($strandType === ProofStrand::LEAF) {
                if ($encodingType === self::ENCODING_HASHED_KEYS) {
                    $keyHash = self::decodeKeyHash($encoded, $offset);
                    $key = '';
                } else {
                    $keySize = self::decodeVarInt($encoded, $offset);
                    $key = self::readBytes($encoded, $offset, $keySize);
                    $keyHash = $hashTree->keyHash($key);
                }

                $valueSize = self::decodeVarInt($encoded, $offset);
                $strands[] = new ProofStrand($strandType, $depth, $keyHash, self::readBytes($encoded, $offset, $valueSize), $key);
                continue;
            }

            if ($strandType === ProofStrand::WITNESS_LEAF) {
                $strands[] = new ProofStrand($strandType, $depth, self::decodeKeyHash($encoded, $offset), bin2hex(self::readBytes($encoded, $offset, 32)));
                continue;
            }

            if ($strandType === ProofStrand::WITNESS_EMPTY) {
                $strands[] = new ProofStrand($strandType, $depth, self::decodeKeyHash($encoded, $offset));
                continue;
            }

            if ($strandType === ProofStrand::WITNESS) {
                $strands[] = new ProofStrand($strandType, $depth, self::decodeKeyHash($encoded, $offset), bin2hex(self::readBytes($encoded, $offset, 32)));
                continue;
            }

            throw new \RuntimeException('unrecognized ProofStrand::Type when decoding proof: ' . $strandType);
        }

        $commands = [];
        if ($strands === []) {
            return new self($strands, $commands);
        }

        $currentPosition = count($strands) - 1;
        while ($offset < strlen($encoded)) {
            $byte = self::readByte($encoded, $offset);

            if ($byte === 0) {
                $commands[] = new ProofCommand(ProofCommand::MERGE, $currentPosition);
                continue;
            }

            if (($byte & 0b1000_0000) === 0) {
                $started = false;
                for ($i = 0; $i < 7; $i++) {
                    if ($started) {
                        if (($byte & 1) === 1) {
                            $commands[] = new ProofCommand(ProofCommand::HASH_PROVIDED, $currentPosition, bin2hex(self::readBytes($encoded, $offset, 32)));
                        } else {
                            $commands[] = new ProofCommand(ProofCommand::HASH_EMPTY, $currentPosition);
                        }
                    } elseif (($byte & 1) === 1) {
                        $started = true;
                    }

                    $byte >>= 1;
                }
                continue;
            }

            $action = $byte >> 5;
            $distance = $byte & 0b1_1111;
            if ($action === 0b100) {
                $currentPosition += $distance + 1;
            } elseif ($action === 0b101) {
                $currentPosition -= $distance + 1;
            } elseif ($action === 0b110) {
                $currentPosition += 1 << ($distance + 6);
            } elseif ($action === 0b111) {
                $currentPosition -= 1 << ($distance + 6);
            }

            if ($currentPosition < 0 || $currentPosition >= count($strands)) {
                throw new \RuntimeException('jumped outside of proof strands');
            }
        }

        return new self($strands, $commands);
    }

    private static function encodeKeyHash(string $keyHashHex): string
    {
        self::assertHash($keyHashHex);
        $keyHash = (string) hex2bin($keyHashHex);

        $trailingZeros = 0;
        for ($i = 31; $i >= 0; $i--) {
            if ($keyHash[$i] !== "\0") {
                break;
            }
            $trailingZeros++;
        }

        return chr($trailingZeros) . substr($keyHash, 0, 32 - $trailingZeros);
    }

    private static function decodeKeyHash(string $encoded, int &$offset): string
    {
        $trailingZeros = self::readByte($encoded, $offset);
        if ($trailingZeros > 32) {
            throw new \RuntimeException('invalid trailing-zero key hash count');
        }

        return bin2hex(self::readBytes($encoded, $offset, 32 - $trailingZeros) . str_repeat("\0", $trailingZeros));
    }

    private static function encodeVarInt(int $number): string
    {
        if ($number < 0) {
            throw new \InvalidArgumentException('varint cannot encode negative numbers');
        }
        if ($number === 0) {
            return "\0";
        }

        $bytes = [];
        while ($number > 0) {
            array_unshift($bytes, $number & 0x7f);
            $number >>= 7;
        }

        $last = count($bytes) - 1;
        foreach ($bytes as $index => $byte) {
            if ($index !== $last) {
                $byte |= 0x80;
            }
            $bytes[$index] = chr($byte);
        }

        return implode('', $bytes);
    }

    private static function decodeVarInt(string $encoded, int &$offset): int
    {
        $result = 0;
        while (true) {
            if ($offset >= strlen($encoded)) {
                throw new \RuntimeException('premature end of varint');
            }
            $byte = self::readByte($encoded, $offset);
            $result = ($result << 7) | ($byte & 0b0111_1111);
            if (($byte & 0b1000_0000) === 0) {
                return $result;
            }
        }
    }

    private static function readByte(string $encoded, int &$offset): int
    {
        if ($offset >= strlen($encoded)) {
            throw new \RuntimeException('proof ends prematurely');
        }

        return ord($encoded[$offset++]);
    }

    private static function readBytes(string $encoded, int &$offset, int $length): string
    {
        if ($length < 0 || strlen($encoded) - $offset < $length) {
            throw new \RuntimeException('proof ends prematurely');
        }

        $bytes = substr($encoded, $offset, $length);
        $offset += $length;

        return $bytes;
    }

    private static function hashBytes(string $hashHex): string
    {
        self::assertHash($hashHex);

        return (string) hex2bin($hashHex);
    }

    private static function assertHash(string $hashHex): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }
    }

    private static function bitLength(int $number): int
    {
        if ($number <= 0) {
            throw new \InvalidArgumentException('bit length expects a positive integer');
        }

        $bits = 0;
        while ($number > 0) {
            $number >>= 1;
            $bits++;
        }

        return $bits;
    }
}
