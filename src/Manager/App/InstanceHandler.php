<?php

namespace Manager\App;

use Manager\Domain\Instance;
use Manager\Infra\Process\InstanceProcess;
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
        $instance->setParameters($data['parameters']);

        if (InstanceProcess::isInstanceCoreRunning($instance)) {
            $instance->declareCoreAsRunning();
        }

        if (InstanceProcess::isInstanceUIRunning($instance)) {
            $instance->declareUIAsRunning();
        }

        $handler = new static($instance);
        $handler->updateConfigApiServiceCors();

        return $handler;
    }

    public function updateConfigApiServiceCors(): self
    {
        $this->instance->config['api_server']['CORS_origins'] = [
            sprintf('http://ui.%s:%d', MANAGER_PROJECT_DOMAIN, $this->instance->parameters['ports']['ui']),
        ];
        InstanceFilesystem::writeInstanceConfig($this->instance);

        return $this;
    }

    public function updateConfigPairlist(): self
    {
        switch ($this->instance->config['exchange']['name'] ?? null) {
            case 'binance':
                $pairList = InstanceProcess::getInstanceBinancePairlist($this->instance);
                break;

            default:
                $pairList = [];
        }

        if ($pairList) {
            $this->instance->config['exchange']['pair_whitelist'] = $pairList;
            InstanceFilesystem::writeInstanceConfig($this->instance);
        }

        return $this;
    }

    public function trade(): array
    {
        $dockerIds = InstanceProcess::runInstanceTrading($this->instance);
        $this->instance->declareCoreAsRunning();

        return $dockerIds;
    }

    public function stop(): void
    {
        InstanceProcess::stopInstance($this->instance);
        $this->instance->declareCoreAsStopped();
    }
}
