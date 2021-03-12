<?php

namespace Manager\App;

use Manager\Domain\Instance;
use Manager\Infra\Process\InstanceProcess;
use Manager\Infra\Process\InstanceUIProcess;
use Manager\Infra\Filesystem\InstanceFilesystem;
use Manager\Domain\Exception\StrategyNotFoundException;

class InstanceHandler
{
    private $instance;

    public function __construct(Instance $instance)
    {
        $this->instance = $instance;
    }

    public static function init(Instance $instance)
    {
        $strategyFilePath = sprintf('%s/strategies/%s.py', MANAGER_DIRECTORY, $instance->strategy);
        if (false === file_exists($strategyFilePath)) {
            throw new StrategyNotFoundException($instance->strategy);
        }

        $data = InstanceFilesystem::initInstance($instance);
        $instance->mergeParameters($data['parameters']);

        $handler = new static($instance);
        $handler->updateConfigApiServiceCors();

        return $handler;
    }

    public function updateConfigApiServiceCors(): self
    {
        $managerConfig = MANAGER_CONFIGURATION;

        $corsEntries = [];
        $managerConfig['cors_domains'][] = $managerConfig['hosts']['ui'];
        foreach ($managerConfig['cors_domains'] as $corsDomain) {
            $corsEntries[] = sprintf(
                'http://%s:%d',
                $corsDomain,
                $this->instance->parameters['ports']['ui']
            );
        }

        $this->instance->config['api_server']['CORS_origins'] = array_unique($corsEntries);
        InstanceFilesystem::writeInstanceConfig($this->instance);

        return $this;
    }

    public function trade(bool $withUI = true): array
    {
        $dockerIds = [
            'core' => InstanceProcess::runInstanceTrading($this->instance),
        ];
        $this->instance->declareAsRunning();

        if (true === $withUI && false === $this->instance->isUIRunning()) {
            $dockerIds['ui'] = InstanceUIProcess::run($this->instance);
            $this->instance->declareUIAsRunning();
        }

        if (false === $withUI && true === $this->instance->isUIRunning()) {
            InstanceUIProcess::stop($this->instance);
            $this->instance->declareUIAsStopped();
        }

        return $dockerIds;
    }

    public function stop(bool $withUI = true): void
    {
        InstanceProcess::stopInstance($this->instance);
        $this->instance->declareAsStopped();

        if ($withUI && true === $this->instance->isUIRunning()) {
            InstanceUIProcess::stop($this->instance);
            $this->instance->declareUIAsStopped();
        }
    }

    public function restart(bool $withUI = true): array
    {
        $dockerIds = [
            'core' => InstanceProcess::restartInstance($this->instance),
        ];
        $this->instance->declareAsRunning();

        if (true === $withUI && true === $this->instance->isUIRunning()) {
            $dockerIds['ui'] = InstanceUIProcess::restart($this->instance);
            $this->instance->declareUIAsRunning();
        }

        return $dockerIds;
    }

    public function reset(): void
    {
        InstanceFilesystem::resetInstanceData($this->instance);
    }

    public function backtestDownloadData(int $daysCount = 5): void
    {
        InstanceProcess::backtestDownloadDataForInstance($this->instance, $daysCount);
    }

    public function backtest(float $fee = 0.001): string
    {
        return InstanceProcess::backtestInstance($this->instance, $fee);
    }
}
