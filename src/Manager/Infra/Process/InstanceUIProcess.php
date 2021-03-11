<?php

namespace Manager\Infra\Process;

use Manager\Domain\Instance;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstanceUIProcess
{
    public static function run(Instance $instance): ?string
    {
        $managerConfig = MANAGER_CONFIGURATION;

        $processCommand = [
            sprintf('docker run --name %s --detach --restart=always', $instance->getDockerUIInstanceName()),
            sprintf('-e TRADING_BOT_API_PORT=%d', $instance->parameters['ports']['api']),
            sprintf('-e TRADING_BOT_API_HOST=%s', $managerConfig['hosts']['api']),
            '--volume /etc/localtime:/etc/localtime:ro',
            sprintf('-v /tmp/freqtrade-manager/resources/scripts/ui-instance-entrypoint.sh:/docker-entrypoint.d/100-ui-instance-entrypoint.sh:ro'),
            sprintf('--publish %d:80/tcp', $instance->parameters['ports']['ui']),
            'ph3nol/freqtrade-ui:latest',
        ];

        return trim(Process::processCommandLine(implode(' ', $processCommand)));
    }

    public static function restartInstance(Instance $instance)
    {
        return trim(Process::processCommandLine(
            sprintf('docker restart %s', $instance->getDockerUIInstanceName())
        ));
    }

    public static function stop(Instance $instance): void
    {
        $processCommand = [
            sprintf('docker kill %s', $instance->getDockerUIInstanceName()),
            sprintf('docker rm %s', $instance->getDockerUIInstanceName()),
        ];

        Process::processCommandLine(implode('; ', $processCommand), false);
    }

    public static function isRunning(Instance $instance): bool
    {
        return (bool) Process::processCommandLine(
            sprintf('docker ps -q -f "name=%s"', $instance->getDockerUIInstanceName())
        );
    }
}
