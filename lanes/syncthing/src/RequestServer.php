<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class RequestServer
{
    private const UNIX_TEMP_PREFIX = '.syncthing.';
    private const WINDOWS_TEMP_PREFIX = '~syncthing~';
    private const TEMP_SUFFIX = '.tmp';
    private const MAX_TEMP_FILENAME_LENGTH = 160 - 11 - 4;

    /**
     * @var array<string, true>
     */
    private array $sharedDeviceIds = [];

    private string $rootPath;

    /**
     * @param list<string> $sharedDeviceIds
     */
    public function __construct(
        private readonly string $folder,
        string $rootPath,
        array $sharedDeviceIds = [],
        private readonly bool $receiveEncrypted = false,
        private readonly ?IgnoreMatcher $ignoreMatcher = null,
    ) {
        if ($this->folder === '') {
            throw new \InvalidArgumentException('Folder ID must not be empty');
        }

        $realRoot = realpath($rootPath);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \InvalidArgumentException('Request root path must be an existing directory');
        }
        $this->rootPath = rtrim($realRoot, DIRECTORY_SEPARATOR);

        foreach ($sharedDeviceIds as $deviceId) {
            if (!is_string($deviceId) || $deviceId === '') {
                throw new \InvalidArgumentException('Shared device IDs must be non-empty strings');
            }
            $this->sharedDeviceIds[$deviceId] = true;
        }
    }

    public static function temporaryName(string $name, string $prefix = self::UNIX_TEMP_PREFIX): string
    {
        if ($prefix === '') {
            throw new \InvalidArgumentException('Temporary file prefix must not be empty');
        }

        $lastSlash = strrpos($name, '/');
        $dir = $lastSlash === false ? '' : substr($name, 0, $lastSlash);
        $base = $lastSlash === false ? $name : substr($name, $lastSlash + 1);
        $tempBase = strlen($base) > self::MAX_TEMP_FILENAME_LENGTH
            ? $prefix . hash('sha256', $base) . self::TEMP_SUFFIX
            : $prefix . $base . self::TEMP_SUFFIX;

        return $dir === '' ? $tempBase : $dir . '/' . $tempBase;
    }

    public static function isTemporaryName(string $name): bool
    {
        $unixSlash = strrpos($name, '/');
        $windowsSlash = strrpos($name, '\\');
        $lastSlash = max($unixSlash === false ? -1 : $unixSlash, $windowsSlash === false ? -1 : $windowsSlash);
        $base = $lastSlash < 0 ? $name : substr($name, $lastSlash + 1);

        return str_starts_with($base, self::UNIX_TEMP_PREFIX) || str_starts_with($base, self::WINDOWS_TEMP_PREFIX);
    }

    public static function isInternalName(string $name): bool
    {
        foreach (['.stfolder', '.stignore', '.stversions'] as $internal) {
            if ($name === $internal || str_starts_with($name, $internal . '/')) {
                return true;
            }
        }

        return false;
    }

    public function serve(string $deviceId, Request $request): RequestServingResult
    {
        if ($request->size < 0 || $request->offset < 0) {
            return $this->error($request->id, Response::CODE_INVALID_FILE, 'negative range');
        }

        if ($request->folder !== $this->folder) {
            return $this->error($request->id, Response::CODE_GENERIC, 'unknown folder');
        }

        if ($this->sharedDeviceIds !== [] && !isset($this->sharedDeviceIds[$deviceId])) {
            return $this->error($request->id, Response::CODE_GENERIC, 'unshared device');
        }

        try {
            ProtocolValidation::checkRequest($request);
        } catch (\InvalidArgumentException $exception) {
            if (str_starts_with($exception->getMessage(), 'request size ')) {
                return $this->error($request->id, Response::CODE_INVALID_FILE, 'invalid request size');
            }

            return $this->error($request->id, Response::CODE_GENERIC, 'invalid filename');
        }

        if (self::isInternalName($request->name)) {
            return $this->error($request->id, Response::CODE_INVALID_FILE, 'internal filename');
        }

        if ($this->ignoreMatcher !== null && $this->ignoreMatcher->match($request->name)->isIgnored()) {
            return $this->error($request->id, Response::CODE_INVALID_FILE, 'ignored filename');
        }

        if ($this->traversesSymlink($request->name)) {
            return $this->error($request->id, Response::CODE_NO_SUCH_FILE, 'symlink traversal');
        }

        if ($request->fromTemporary) {
            $temporary = $this->readRegularSlice(self::temporaryName($request->name), $request->offset, $request->size);
            if ($temporary['status'] === 'ok' && $this->validHash($temporary['data'], $request->hashHex)) {
                return new RequestServingResult(
                    new Response($request->id, $temporary['data'], Response::CODE_NO_ERROR),
                    RequestServingResult::SOURCE_TEMPORARY,
                );
            }
            if ($temporary['status'] === 'not_regular') {
                return $this->error($request->id, Response::CODE_NO_SUCH_FILE, 'temporary file missing');
            }
        }

        $final = $this->readRegularSlice($request->name, $request->offset, $request->size, allowShortRead: true);
        if ($final['status'] === 'not_regular') {
            return $this->error($request->id, Response::CODE_NO_SUCH_FILE, 'file missing');
        }
        if ($final['status'] === 'read_error') {
            return $this->error($request->id, Response::CODE_GENERIC, 'read failed');
        }

        if (!$this->receiveEncrypted && !$this->validHash($final['data'], $request->hashHex)) {
            return $this->error($request->id, Response::CODE_NO_SUCH_FILE, 'hash mismatch');
        }

        return new RequestServingResult(
            new Response($request->id, $final['data'], Response::CODE_NO_ERROR),
            RequestServingResult::SOURCE_FINAL,
        );
    }

    private function error(int $id, int $code, string $reason): RequestServingResult
    {
        return new RequestServingResult(new Response($id, '', $code), RequestServingResult::SOURCE_NONE, $reason);
    }

    private function validHash(string $data, string $hashHex): bool
    {
        if ($hashHex === '') {
            return true;
        }

        return hash_equals($hashHex, hash('sha256', $data));
    }

    /**
     * @return array{status:'ok'|'not_regular'|'read_error', data:string}
     */
    private function readRegularSlice(string $name, int $offset, int $size, bool $allowShortRead = false): array
    {
        $path = $this->absolutePath($name);
        if (is_link($path) || !is_file($path)) {
            return ['status' => 'not_regular', 'data' => ''];
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return ['status' => 'read_error', 'data' => ''];
        }

        try {
            if ($offset > 0 && fseek($handle, $offset) !== 0) {
                return ['status' => 'read_error', 'data' => ''];
            }

            $data = $size === 0 ? '' : fread($handle, $size);
            if (!is_string($data)) {
                return ['status' => 'read_error', 'data' => ''];
            }

            if (!$allowShortRead && strlen($data) !== $size) {
                return ['status' => 'read_error', 'data' => $data];
            }

            return ['status' => 'ok', 'data' => $data];
        } finally {
            fclose($handle);
        }
    }

    private function absolutePath(string $name): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    }

    private function traversesSymlink(string $name): bool
    {
        $parts = explode('/', $name);
        array_pop($parts);

        $path = $this->rootPath;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $path .= DIRECTORY_SEPARATOR . $part;
            if (is_link($path)) {
                return true;
            }
        }

        return false;
    }
}
