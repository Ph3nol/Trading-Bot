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
        $instance->setParameters($data['parameters']);

        if (InstanceProcess::isInstanceCoreRunning($instance)) {
            $instance->declareAsRunning();
        }

        if (InstanceUIProcess::isRunning($instance)) {
            $instance->declareUIAsRunning();
        }

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

    public function trade(bool $withUI = true): array
    {
        $dockerIds = [
            'core' => InstanceProcess::runInstanceTrading($this->instance),
        ];
        $this->instance->declareAsRunning();

        if (false === $this->instance->isUIRunning() && true === $withUI) {
            $dockerIds['ui'] = InstanceUIProcess::run($this->instance);
            $this->instance->declareUIAsRunning();
        }

        if (true === $this->instance->isUIRunning() && false === $withUI) {
            InstanceUIProcess::stop($this->instance);
            $this->instance->declareUIAsStopped();
        }

        return $dockerIds;
    }

    public function stop(): void
    {
        InstanceProcess::stopInstance($this->instance);
        $this->instance->declareAsStopped();

        if (true === $this->instance->isUIRunning()) {
            InstanceUIProcess::stop($this->instance);
            $this->instance->declareUIAsStopped();
        }
    }

    public function reset(): void
    {
        InstanceFilesystem::resetInstanceData($this->instance);
    }
}
