<?php

namespace Manager\Infra\Process;

use Manager\Domain\Instance;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstanceProcess
{
    public static function generateHostRandomAvailablePort(): int
    {
        return (int) Process::processCommandLine(
            sprintf('sh %s/resources/scripts/generate-random-available-port.sh', MANAGER_PROJECT_DIRECTORY)
        );
    }

    public static function runInstanceTrading(Instance $instance)
    {
        $processCommand = [
            sprintf('docker run --name %s --detach --restart=always', $instance->getDockerCoreInstanceName()),
            '--volume /etc/localtime:/etc/localtime:ro',
            sprintf('--volume %s:/freqtrade/config.json:ro', $instance->files['host']['config']),
            sprintf('--volume %s/strategies/%s.py:/freqtrade/strategy.py:ro', HOST_MANAGER_DIRECTORY, $instance->strategy),
            sprintf('--volume %s:/freqtrade/freqtrade.log:rw', $instance->files['host']['logs']),
            sprintf('--volume %s:/freqtrade/user_data:rw', $instance->directories['host']['data']),
            sprintf('--volume %s:/freqtrade/tradesv3.dryrun.sqlite:rw', $instance->files['host']['db_dry_run']),
            sprintf('--volume %s:/freqtrade/tradesv3.sqlite:rw', $instance->files['host']['db_production']),
            sprintf('--publish %d:8080/tcp', $instance->parameters['ports']['api']),
            'ph3nol/freqtrade:latest',
            'trade --config /freqtrade/config.json',
            '--logfile /freqtrade/freqtrade.log',
            '--strategy-path /freqtrade',
            sprintf('--strategy %s', $instance->strategy),
        ];

        return trim(Process::processCommandLine(implode(' ', $processCommand)));
    }

    public static function restartInstance(Instance $instance)
    {
        return trim(Process::processCommandLine(
            sprintf('docker restart %s', $instance->getDockerCoreInstanceName())
        ));
    }

    public static function stopInstance(Instance $instance): void
    {
        $processCommand = [
            sprintf('docker kill %s', $instance->getDockerCoreInstanceName()),
            sprintf('docker rm %s', $instance->getDockerCoreInstanceName()),
        ];

        Process::processCommandLine(implode('; ', $processCommand), false);
    }

    public static function isInstanceCoreRunning(Instance $instance): bool
    {
        return (bool) Process::processCommandLine(
            sprintf('docker ps -q -f "name=%s"', $instance->getDockerCoreInstanceName())
        );
    }

    public static function backtestDownloadDataForInstance(Instance $instance, int $daysCount = 5, array $timeframes): void
    {
        $processCommand = [
            sprintf('docker run --rm --name %s-download-data', $instance->getDockerCoreInstanceName()),
            '--volume /etc/localtime:/etc/localtime:ro',
            sprintf('--volume %s:/freqtrade/config.json:ro', $instance->files['host']['config_backtest']),
            sprintf('--volume %s:/freqtrade/user_data:rw', $instance->directories['host']['data']),
            'ph3nol/freqtrade:latest',
            'download-data',
            sprintf('-t %s', implode(' ', $timeframes)),
            sprintf('--exchange %s', $instance->config['exchange']['name']),
            sprintf('--days=%d', $daysCount),
        ];

        Process::processCommandLine(implode(' ', $processCommand));
    }

    public static function backtestInstance(Instance $instance, float $fee = 0.001): string
    {
        $processCommand = [
            sprintf('docker run --rm --name %s-backtest', $instance->getDockerCoreInstanceName()),
            '--volume /etc/localtime:/etc/localtime:ro',
            sprintf('--volume %s:/freqtrade/config.json:ro', $instance->files['host']['config_backtest']),
            sprintf('--volume %s/%s.py:/freqtrade/strategy.py:ro', HOST_MANAGER_STRATEGIES_DIRECTORY, $instance->strategy),
            sprintf('--volume %s:/freqtrade/freqtrade.log:rw', $instance->files['host']['logs']),
            sprintf('--volume %s:/freqtrade/user_data:rw', $instance->directories['host']['data']),
            'ph3nol/freqtrade:latest',
            'backtesting --config /freqtrade/config.json',
            sprintf('--fee %s', $fee),
            '--enable-protections',
            '--export trades',
            '--strategy-path /freqtrade',
            sprintf('--strategy %s', $instance->strategy),
        ];

        return Process::processCommandLine(implode(' ', $processCommand), true, true);
    }

    public static function plotInstance(Instance $instance, array $pairs = []): void
    {
        $processCommand = [
            sprintf('docker run --rm --name %s-plotting', $instance->getDockerCoreInstanceName()),
            '--volume /etc/localtime:/etc/localtime:ro',
            sprintf('--volume %s:/freqtrade/config.json:ro', $instance->files['host']['config_backtest']),
            sprintf('--volume %s/strategies/%s.py:/freqtrade/strategy.py:ro', HOST_MANAGER_DIRECTORY, $instance->strategy),
            sprintf('--volume %s:/freqtrade/freqtrade.log:rw', $instance->files['host']['logs']),
            sprintf('--volume %s:/freqtrade/user_data:rw', $instance->directories['host']['data']),
            'ph3nol/freqtrade:latest',
            'plot-dataframe --config /freqtrade/config.json',
            '--strategy-path /freqtrade',
            sprintf('--strategy %s', $instance->strategy),
        ];

        if ($pairs) {
            $processCommand[] = sprintf('-p %s', implode(' ', $pairs));
        }

        Process::processCommandLine(implode(' ', $processCommand), true, true);
    }

    public static function getPairsList(Instance $instance): ?string
    {
        $processCommand = [
            sprintf('docker run --rm --name %s-test-pairlist', $instance->getDockerCoreInstanceName()),
            '--volume /etc/localtime:/etc/localtime:ro',
            sprintf('--volume %s:/freqtrade/config.json:ro', $instance->files['host']['config_backtest']),
            sprintf('--volume %s:/freqtrade/user_data:rw', $instance->directories['host']['data']),
            'ph3nol/freqtrade:latest',
            'test-pairlist',
        ];

        return Process::processCommandLine(implode(' ', $processCommand), false) ? : null;
    }
}
