<?php

namespace Manager\Domain;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class Instance
{
    const STATUS_STOPPED = 'stopped';
    const STATUS_RUNNING = 'running';

    public UuidInterface $uuid;
    public string $status = self::STATUS_STOPPED;
    public string $uiStatus = self::STATUS_STOPPED;
    public string $slug;
    public string $label;
    public string $strategy;
    public array $config;

    public array $parameters = [];
    public array $directories = [];
    public array $files = [];

    public function __construct(
        UuidInterface $uuid,
        string $slug,
        string $label,
        string $strategy,
        array $config
    ) {
        $this->uuid = $uuid;
        $this->slug = $slug;
        $this->label = $label;
        $this->strategy = $strategy;
        $this->config = $config;

        $this->initDirectoriesAndFiles();
    }

    public static function create(string $slug = null, string $strategy, array $config): self
    {
        $uuid = Uuid::uuid4();
        $label = $slug ? strtoupper($slug) : (string) $uuid;

        return new static($uuid, $slug, $label, $strategy, $config);
    }

    public function __toString(): string
    {
        return $this->label;
    }

    public function isProduction(): bool
    {
        return ! (($this->config['dry_run'] ?? false) === true);
    }

    public function isApiEnabled(): bool
    {
        return ($this->config['api_server']['enabled'] ?? false) === true;
    }

    public function isEdgeEnabled(): bool
    {
        return ($this->config['edge']['enabled'] ?? false) === true;
    }

    public function isTelegramEnabled(): bool
    {
        return ($this->config['telegram']['enabled'] ?? false) === true;
    }

    public function isForceBuyEnabled(): bool
    {
        return ($this->config['forcebuy_enable'] ?? false) === true;
    }

    public function declareAsRunning(): self
    {
        $this->status = self::STATUS_RUNNING;

        return $this;
    }

    public function declareAsStopped(): self
    {
        $this->status = self::STATUS_STOPPED;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isRunning(): bool
    {
        return self::STATUS_RUNNING === $this->getStatus();
    }

    public function declareUIAsRunning(): self
    {
        $this->uiStatus = self::STATUS_RUNNING;

        return $this;
    }

    public function declareUIAsStopped(): self
    {
        $this->uiStatus = self::STATUS_STOPPED;

        return $this;
    }

    public function getUIStatus(): string
    {
        return $this->uiStatus;
    }

    public function isUIRunning(): bool
    {
        return self::STATUS_RUNNING === $this->getUIStatus();
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    private function initDirectoriesAndFiles(): void
    {
        $baseDirectory = MANAGER_INSTANCES_DIRECTORY . '/' . $this->slug;
        $hostBaseDirectory = HOST_MANAGER_INSTANCES_DIRECTORY . '/' . $this->slug;

        $this->directories = [
            'container' => [
                '_base' => $baseDirectory,
                'db' => $baseDirectory . '/db',
                'data' => $baseDirectory . '/data',
            ],
            'host' => [
                '_base' => $hostBaseDirectory,
                'db' => $hostBaseDirectory . '/db',
                'data' => $hostBaseDirectory . '/data',
            ],
        ];

        $this->files = [
            'container' => [
                'parameters' => $this->directories['container']['_base'] . '/parameters.json',
                'config' => $this->directories['container']['_base'] . '/config.ready.json',
                'logs' => $this->directories['container']['_base'] . '/instance.log',
                'db_dry_run' => $this->directories['container']['db'] . '/tradesv3.dryrun.sqlite',
                'db_production' => $this->directories['container']['db'] . '/tradesv3.sqlite',
            ],
            'host' => [
                'parameters' => $this->directories['host']['_base'] . '/parameters.json',
                'config' => $this->directories['host']['_base'] . '/config.ready.json',
                'logs' => $this->directories['host']['_base'] . '/instance.log',
                'db_dry_run' => $this->directories['host']['db'] . '/tradesv3.dryrun.sqlite',
                'db_production' => $this->directories['host']['db'] . '/tradesv3.sqlite',
            ],
        ];
    }
}
