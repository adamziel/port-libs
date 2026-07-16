<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BepSessionHandlers
{
    private readonly ?\Closure $requestHandler;
    private readonly ?\Closure $indexHandler;
    private readonly ?\Closure $indexUpdateHandler;
    private readonly ?\Closure $downloadProgressHandler;

    /**
     * @param null|callable(Request): (RequestServingResult|Response|string|null) $requestHandler
     * @param null|callable(Index): mixed $indexHandler
     * @param null|callable(IndexUpdate): mixed $indexUpdateHandler
     * @param null|callable(DownloadProgress): mixed $downloadProgressHandler
     */
    public function __construct(
        ?callable $requestHandler = null,
        ?callable $indexHandler = null,
        ?callable $indexUpdateHandler = null,
        ?callable $downloadProgressHandler = null,
    ) {
        $this->requestHandler = $requestHandler === null ? null : \Closure::fromCallable($requestHandler);
        $this->indexHandler = $indexHandler === null ? null : \Closure::fromCallable($indexHandler);
        $this->indexUpdateHandler = $indexUpdateHandler === null ? null : \Closure::fromCallable($indexUpdateHandler);
        $this->downloadProgressHandler = $downloadProgressHandler === null ? null : \Closure::fromCallable($downloadProgressHandler);
    }

    /**
     * @param callable(Request): (RequestServingResult|Response|string|null) $handler
     */
    public static function request(callable $handler): self
    {
        return new self(requestHandler: $handler);
    }

    /**
     * @param null|callable(Index): mixed $index
     * @param null|callable(IndexUpdate): mixed $indexUpdate
     * @param null|callable(DownloadProgress): mixed $downloadProgress
     */
    public static function model(
        ?callable $index = null,
        ?callable $indexUpdate = null,
        ?callable $downloadProgress = null,
    ): self {
        return new self(
            indexHandler: $index,
            indexUpdateHandler: $indexUpdate,
            downloadProgressHandler: $downloadProgress,
        );
    }

    public function requestHandler(): ?\Closure
    {
        return $this->requestHandler;
    }

    public function indexHandler(): ?\Closure
    {
        return $this->indexHandler;
    }

    public function indexUpdateHandler(): ?\Closure
    {
        return $this->indexUpdateHandler;
    }

    public function downloadProgressHandler(): ?\Closure
    {
        return $this->downloadProgressHandler;
    }

    /**
     * @param null|callable(Request): (RequestServingResult|Response|string|null) $handler
     */
    public function withRequestHandler(?callable $handler): self
    {
        return new self(
            requestHandler: $handler,
            indexHandler: $this->indexHandler,
            indexUpdateHandler: $this->indexUpdateHandler,
            downloadProgressHandler: $this->downloadProgressHandler,
        );
    }

    /**
     * @param null|callable(Index): mixed $handler
     */
    public function withIndexHandler(?callable $handler): self
    {
        return new self(
            requestHandler: $this->requestHandler,
            indexHandler: $handler,
            indexUpdateHandler: $this->indexUpdateHandler,
            downloadProgressHandler: $this->downloadProgressHandler,
        );
    }

    /**
     * @param null|callable(IndexUpdate): mixed $handler
     */
    public function withIndexUpdateHandler(?callable $handler): self
    {
        return new self(
            requestHandler: $this->requestHandler,
            indexHandler: $this->indexHandler,
            indexUpdateHandler: $handler,
            downloadProgressHandler: $this->downloadProgressHandler,
        );
    }

    /**
     * @param null|callable(DownloadProgress): mixed $handler
     */
    public function withDownloadProgressHandler(?callable $handler): self
    {
        return new self(
            requestHandler: $this->requestHandler,
            indexHandler: $this->indexHandler,
            indexUpdateHandler: $this->indexUpdateHandler,
            downloadProgressHandler: $handler,
        );
    }

    public function mergedWith(self $overrides): self
    {
        return new self(
            requestHandler: $overrides->requestHandler ?? $this->requestHandler,
            indexHandler: $overrides->indexHandler ?? $this->indexHandler,
            indexUpdateHandler: $overrides->indexUpdateHandler ?? $this->indexUpdateHandler,
            downloadProgressHandler: $overrides->downloadProgressHandler ?? $this->downloadProgressHandler,
        );
    }
}
