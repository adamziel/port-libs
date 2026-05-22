<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Response
{
    public const CODE_NO_ERROR = 0;
    public const CODE_GENERIC = 1;
    public const CODE_NO_SUCH_FILE = 2;
    public const CODE_INVALID_FILE = 3;

    public function __construct(
        public readonly int $id = 0,
        public readonly string $data = '',
        public readonly int $code = self::CODE_NO_ERROR,
    ) {
        if ($this->id < 0) {
            throw new \InvalidArgumentException('Response ID must not be negative');
        }
        if (!in_array($this->code, [
            self::CODE_NO_ERROR,
            self::CODE_GENERIC,
            self::CODE_NO_SUCH_FILE,
            self::CODE_INVALID_FILE,
        ], true)) {
            throw new \InvalidArgumentException('Unknown response error code');
        }
    }
}
