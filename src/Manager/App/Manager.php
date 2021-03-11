<?php

namespace Manager\App;

use Manager\Domain\Instance;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Manager\Infra\Process\ManagerProcess;
use Manager\Infra\Filesystem\ManagerFilesystem;
use Manager\Infra\Filesystem\InstanceFilesystem;
use Manager\Domain\Exception\InstanceNotFoundException;
use Manager\Domain\Exception\InstanceNotFoundConfigFileException;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class Manager
{
    const COMMON_INSTANCE_KEY = '_all';

    private $instances = [];
    private $parameters = [];
    private $behaviours = [];
    private $dockerStatus = [];

    public function __construct(array $managerData)
    {
        ManagerFilesystem::init();
        $this->parameters = $managerData['parameters'];
        define('MANAGER_CONFIGURATION', $this->parameters);
        $this->initDockerStatus();
        $this->initBehaviours();

        $this->populateInstances($managerData['instances'] ?? []);
    }

    public static function fromFile(string $filePath): self
    {
        $managerData = Yaml::parseFile($filePath);

        return new static($managerData);
    }

    public function setInstances(array $instances): self
    {
        $this->instances = $instances;

        return $this;
    }

    public function getInstances(): array
    {
        return $this->instances;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getBehaviours(): array
    {
        return $this->behaviours;
    }

    public function findRequiredInstanceFromSlug(string $slug): Instance
    {
        foreach ($this->getInstances() as $instance) {
            if ($slug === $instance->slug) {
                return $instance;
            }
        }

        throw new InstanceNotFoundException($slug);
    }

    private function populateInstances(array $instancesPayloads): self
    {
        $commonPayload = [];
        if (array_key_exists(self::COMMON_INSTANCE_KEY, $instancesPayloads)) {
            $commonPayload = $instancesPayloads[self::COMMON_INSTANCE_KEY];
            unset($instancesPayloads[self::COMMON_INSTANCE_KEY]);
        }

        foreach ($instancesPayloads as $instanceSlug => $instancePayload) {
            $instanceConfig = array_replace_recursive(
                $this->getBaseConfigurationDataFromInstancePayload($instancePayload),
                $commonPayload['config'] ?? [],
                $instancePayload['config'] ?? []
            );

            $instanceBehaviours = $instancePayload['behaviours'] ?? [];
            $instanceBehaviours = array_map(function ($behaviourData): array {
                return $behaviourData ?? [];
            }, $instanceBehaviours);

            $instance = Instance::create(
                $instanceSlug,
                $instancePayload['strategy'],
                $instanceConfig,
                $instanceBehaviours
            );

            $instance->config['bot_name'] = sprintf('TB.%s', (string) $instance);

            $this->applyDockerStatusToInstance($instance);
            $this->applyBehavioursToInstance($instance);

            InstanceFilesystem::writeInstanceConfig($instance);

            $this->instances[$instance->slug] = $instance;
        }

        return $this;
    }

    private function getBaseConfigurationDataFromInstancePayload(array $instancePayload): array
    {
        $configFilePath = MANAGER_DIRECTORY . '/configs/' . $instancePayload['config_file'];
        if (false === file_exists($configFilePath)) {
            throw new InstanceNotFoundConfigFileException($instancePayload['config_file']);
        }

        $configContent = file_get_contents($configFilePath);

        return json_decode($configContent, true);
    }

    private function initBehaviours(): self
    {
        $finder = new Finder();
        $finder->files()
            ->in(MANAGER_PROJECT_DIRECTORY . '/src/Manager/App/Behaviour')
            ->name('*.php')
            ->notName('AbstractBehaviour.php');

        if (false === $finder->hasResults()) {
            return $this;
        }

        foreach ($finder as $file) {
            $behaviourFqcn = sprintf(
                'Manager\\App\\Behaviour\\%s',
                pathinfo($file->getRelativePathname(), PATHINFO_FILENAME)
            );

            $behaviour = new $behaviourFqcn;
            $this->behaviours[$behaviour->getSlug()] = $behaviour;
        }

        return $this;
    }

    private function applyBehavioursToInstance(Instance $instance): self
    {
        foreach ($instance->behaviours as $behaviourSlug => $behaviourConfig) {
            if (false === array_key_exists($behaviourSlug, $this->behaviours)) {
                continue;
            }

            $behaviour = $this->behaviours[$behaviourSlug];
            $behaviour->updateInstance($instance);
            $behaviour->write();
        }

        return $this;
    }

    private function initDockerStatus(): self
    {
        $this->dockerStatus = [];
        $dockerStatus = ManagerProcess::getDockerStatus();

        if ((bool) $dockerStatus) {
            $dockerStatusEntries = explode("\n", $dockerStatus);
            $dockerStatusEntries = array_map(function (string $statusEntry): array {
                $data = explode(';;;', $statusEntry);
                $isRunning = 0 === strpos($data[3], 'Up');
                $uptime = $isRunning ? $data[3] : null;
                if ($uptime) {
                    $uptime = strtolower($uptime);
                    $uptime = str_replace(
                        ['up', 'less than', 'about', 'an hour', 'a minute', 'a day', 'hours', ' minutes', ' seconds', ' days', ' hour', ' minute', ' second', ' day'],
                        ['', '-', '~', '1h', '1m', '1d', 'h', 'm', 's', 'd','h', 'm', 's', 'd'],
                        $uptime
                    );
                    $uptime = trim($uptime);
                }

                return [
                    'id' => $data[0],
                    'name' => $data[1],
                    'image' => $data[2],
                    'is_running' => $isRunning,
                    'uptime' => $uptime,
                ];
            }, $dockerStatusEntries);

            foreach ($dockerStatusEntries as $statusEntry) {
                $this->dockerStatus[$statusEntry['name']] = $statusEntry;
            }
        }

        return $this;
    }

    private function applyDockerStatusToInstance(Instance $instance): self
    {
        if (true === $this->dockerStatus[$instance->getDockerCoreInstanceName()]['is_running'] ?? false) {
            $instance->declareAsRunning(
                $this->dockerStatus[$instance->getDockerCoreInstanceName()]['uptime']
            );
        }

        if (true === $this->dockerStatus[$instance->getDockerUIInstanceName()]['is_running'] ?? false) {
            $instance->declareUIAsRunning(
                $this->dockerStatus[$instance->getDockerUIInstanceName()]['uptime']
            );
        }

        return $this;
    }
}
