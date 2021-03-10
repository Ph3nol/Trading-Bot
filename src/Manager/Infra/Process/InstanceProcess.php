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
            sprintf('docker run --name %s --detach --restart=always', self::getInstanceCoreContainerName($instance)),
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

    public static function stopInstance(Instance $instance): void
    {
        $processCommand = [
            sprintf('docker kill %s', self::getInstanceCoreContainerName($instance)),
            sprintf('docker rm %s', self::getInstanceCoreContainerName($instance)),
        ];

        Process::processCommandLine(implode('; ', $processCommand), false);
    }

    public static function isInstanceCoreRunning(Instance $instance): bool
    {
        return (bool) Process::processCommandLine(
            sprintf('docker ps -q -f "name=%s"', self::getInstanceCoreContainerName($instance))
        );
    }

    private static function getInstanceCoreContainerName(Instance $instance): string
    {
        return sprintf('trading-bot-%s-core', $instance->slug);
    }
}
