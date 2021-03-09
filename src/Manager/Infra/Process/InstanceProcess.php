<?php

namespace Manager\Infra\Process;

use Manager\Domain\Instance;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstanceProcess
{
    public static function generateHostRandomAvailablePort(): int
    {
        $process = Process::fromShellCommandline(
            sprintf('sh %s/scripts/generate-random-available-port.sh', MANAGER_PROJECT_DIRECTORY)
        );
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return (int) $process->getOutput();
    }

    public static function getInstanceBinancePairlist(Instance $instance, int $pairsCount = 50): array
    {
        $processCommand = [
            sprintf('docker run --rm --name trading-bot-%s-get-instance-config-pairlist', $instance->slug),
            sprintf('-e TRADING_BOT_INSTANCE_CONFIG_PAIR=%s', $instance->config['stake_currency']),
            sprintf('-v /tmp/freqtrade-manager/scripts/scrap-instance-config-pairlist.js:/app/index.js'),
            'alekzonder/puppeteer:latest',
        ];
        $process = Process::fromShellCommandline(
            implode(' ', $processCommand)
        );
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        $whiteList = json_decode($process->getOutput(), true) ?? [];

        return array_slice(array_unique($whiteList ?: []), 0, $pairsCount);
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
        $process = Process::fromShellCommandline(
            implode(' ', $processCommand)
        );
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return trim($process->getOutput());
    }

    public static function stopInstance(Instance $instance): void
    {
        $processCommand = [
            sprintf('docker kill %s', self::getInstanceCoreContainerName($instance)),
            sprintf('docker rm %s', self::getInstanceCoreContainerName($instance)),
        ];
        $process = Process::fromShellCommandline(
            implode('; ', $processCommand)
        );
        $process->run();
    }

    public static function isInstanceCoreRunning(Instance $instance): bool
    {
        $process = Process::fromShellCommandline(
            sprintf('docker ps -q -f "name=%s"', self::getInstanceCoreContainerName($instance))
        );
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return (bool) $process->getOutput();
    }

    private static function getInstanceCoreContainerName(Instance $instance): string
    {
        return sprintf('trading-bot-%s-core', $instance->slug);
    }
}
