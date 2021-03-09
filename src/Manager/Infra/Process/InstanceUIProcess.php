<?php

namespace Manager\Infra\Process;

use Manager\Domain\Instance;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstanceUIProcess
{
    public static function run(Instance $instance): ?string
    {
        $managerConfig = MANAGER_CONFIGURATION;

        $processCommand = [
            sprintf('docker run --name %s --detach --restart=always', self::getContainerName($instance)),
            sprintf('-e TRADING_BOT_API_PORT=%d', $instance->parameters['ports']['api']),
            sprintf('-e TRADING_BOT_API_HOST=%s', $managerConfig['hosts']['api']),
            '--volume /etc/localtime:/etc/localtime:ro',
            sprintf('-v /tmp/freqtrade-manager/scripts/ui-instance-entrypoint.sh:/docker-entrypoint.d/100-ui-instance-entrypoint.sh:ro'),
            sprintf('--publish %d:80/tcp', $instance->parameters['ports']['ui']),
            'ph3nol/freqtrade-ui:latest',
        ];
        $process = Process::fromShellCommandline(
            implode(' ', $processCommand)
        );
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }

    public static function stop(Instance $instance): void
    {
        $processCommand = [
            sprintf('docker kill %s', self::getContainerName($instance)),
            sprintf('docker rm %s', self::getContainerName($instance)),
        ];
        $process = Process::fromShellCommandline(
            implode('; ', $processCommand)
        );
        $process->run();
    }

    public static function isRunning(Instance $instance): bool
    {
        $process = Process::fromShellCommandline(
            sprintf('docker ps -q -f "name=%s"', self::getContainerName($instance))
        );
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return (bool) $process->getOutput();
    }

    private static function getContainerName(Instance $instance): string
    {
        return sprintf('trading-bot-%s-ui', $instance->slug);
    }
}
