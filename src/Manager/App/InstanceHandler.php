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

    public function getInstance(): Instance
    {
        return $this->instance;
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
        $timeframes = [];
        $timeframes[] = $this->instance->config['timeframe'] ?? null;
        $timeframes = array_merge($timeframes, $this->extractTimeframesFromInstanceStrategy());
        $timeframes = array_unique(array_filter($timeframes));
        if (!$timeframes) {
            $timeframes[] = '5m';
        }

        InstanceProcess::backtestDownloadDataForInstance($this->instance, $daysCount, $timeframes);
    }

    public function removeBacktestData(): void
    {
        InstanceFilesystem::removeInstanceBacktestData($this->instance);
    }

    public function backtest(float $fee = 0.001): string
    {
        return InstanceProcess::backtestInstance($this->instance, $fee);
    }

    public function removePlottingData(): void
    {
        InstanceFilesystem::removeInstancePlottingData($this->instance);
    }

    public function plot(array $pairs = []): void
    {
        InstanceProcess::plotInstance($this->instance, $pairs);
    }

    public function getPairsList(): array
    {
        $pairsListOutput = InstanceProcess::getPairsList($this->instance);
        if (null === $pairsListOutput) {
            return [];
        }

        $pairsListOutput = explode("\n", $pairsListOutput);
        array_shift($pairsListOutput);
        $pairListOutput = str_replace('\'', '"', $pairsListOutput[0]);
        $pairsList = json_decode($pairListOutput, true);

        return $pairsList;
    }

    private function extractTimeframesFromInstanceStrategy(): array
    {
        $strategyContent = InstanceFilesystem::getInstanceStrategyFileContent($this->instance);
        $timeframesRegex = '/(timeframe|informative_timeframe)?[ ]=?[ ][\'|"](1m|3m|5m|15m|30m|1h|2h|4h|6h|8h|12h|1d|3d|1w|2w|1M|1y)[\'|"]$/im';
        preg_match_all($timeframesRegex, $strategyContent, $matches);

        return $matches[2] ?? [];
    }
}
