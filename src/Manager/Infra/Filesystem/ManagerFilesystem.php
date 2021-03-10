<?php

namespace Manager\Infra\Filesystem;

use Manager\Domain\BehaviourInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class ManagerFilesystem
{
    public static function init()
    {
        $filesystem = new Filesystem();

        try {
            $filesystem->mkdir(MANAGER_DATA_DIRECTORY);
        } catch (IOExceptionInterface $exception) {
            // Already exists.
        }

        try {
            $filesystem->mkdir(MANAGER_DATA_DIRECTORY . '/behaviours');
        } catch (IOExceptionInterface $exception) {
            // Already exists.
        }
    }

    public static function writeBehaviourData(BehaviourInterface $behaviour): void
    {
        $filesystem = new Filesystem();

        $dataContent = json_encode($behaviour->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filesystem->dumpFile(self::getBehaviourDataFilePath($behaviour), $dataContent);
    }

    public static function getBehaviourData(BehaviourInterface $behaviour): array
    {
        $filesystem = new Filesystem();

        $filePath = self::getBehaviourDataFilePath($behaviour);
        if (false === file_exists($filePath)) {
            return [];
        }

        $dataContent = file_get_contents($filePath);

        return json_decode($dataContent, true);
    }

    private static function getBehaviourDataFilePath(BehaviourInterface $behaviour): string
    {
        return sprintf('%s/behaviours/%s.json', MANAGER_DATA_DIRECTORY, $behaviour->getSlug());
    }
}
