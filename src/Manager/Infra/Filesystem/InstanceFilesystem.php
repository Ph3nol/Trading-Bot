<?php

namespace Manager\Infra\Filesystem;

use Manager\Domain\Instance;
use Manager\Infra\Process\InstanceProcess;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class InstanceFilesystem
{
    public static function initInstance(Instance $instance): array
    {
        $filesystemData = [];
        $filesystem = new Filesystem();

        if (false === $filesystem->exists($instance->files['container']['parameters'])) {
            $filesystem->touch($instance->files['container']['parameters']);
        }
        $parametersContent = file_get_contents($instance->files['container']['parameters']);
        $parameters = json_decode($parametersContent, true) ?? [];
        $parameters = array_replace_recursive(self::getDefaultParameters(), $parameters);
        if (null === $parameters['ports']['api']) {
            $parameters['ports']['api'] = InstanceProcess::generateHostRandomAvailablePort();
        }
        if (null === $parameters['ports']['ui']) {
            $parameters['ports']['ui'] = InstanceProcess::generateHostRandomAvailablePort();
        }
        $parametersContent = json_encode($parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filesystem->dumpFile($instance->files['container']['parameters'], $parametersContent);
        $filesystemData['parameters'] = $parameters;

        $filesystem->touch($instance->files['container']['config']);
        $filesystem->touch($instance->files['container']['config_backtest']);

        try {
            $filesystem->mkdir($instance->directories['container']['_base']);
        } catch (IOExceptionInterface $exception) {
            // Already exists.
        }
        self::writeInstanceConfig($instance);
        $filesystem->touch($instance->files['container']['logs']);

        try {
            $filesystem->mkdir($instance->directories['container']['db']);
        } catch (IOExceptionInterface $exception) {
            // Already exists.
        }
        $filesystem->touch($instance->files['container']['db_dry_run']);
        $filesystem->touch($instance->files['container']['db_production']);

        try {
            $filesystem->mkdir($instance->directories['container']['data']);
        } catch (IOExceptionInterface $exception) {
            // Already exists.
        }

        // Temp: in order to by-pass new Freqtrade 2021.4 release Docker user (`ftuser`).
        $filesystem->chmod($instance->directories['container']['_base'], 0777, 0000, true);

        return $filesystemData;
    }

    public static function writeInstanceConfig(Instance $instance): void
    {
        $filesystem = new Filesystem();
        $configContent = json_encode($instance->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filesystem->dumpFile($instance->files['container']['config'], $configContent);
    }

    public static function writeInstanceConfigBacktest(Instance $instance): void
    {
        $filesystem = new Filesystem();
        $configContent = json_encode($instance->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filesystem->dumpFile($instance->files['container']['config_backtest'], $configContent);
    }

    public static function resetInstanceData(Instance $instance): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($instance->directories['container']['_base']);
    }

    public static function removeInstanceBacktestData(Instance $instance): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove(sprintf('%s/backtest_results', $instance->directories['container']['data']));
    }

    public static function removeInstancePlottingData(Instance $instance): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove(sprintf('%s/plot', $instance->directories['container']['data']));
    }

    public static function getInstanceStrategyFileContent(Instance $instance): string
    {
        return file_get_contents(
            sprintf('%s/%s.py', MANAGER_STRATEGIES_DIRECTORY, $instance->strategy)
        );
    }

    public static function getDefaultParameters(): array
    {
        return [
            'ports' => [
                'api' => null,
                'ui' => null,
            ],
        ];
    }
}
