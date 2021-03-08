<?php

namespace Manager\Infra\Filesystem;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class ManagerFilesystem
{
    public static function init(): void
    {
        $filesystem = new Filesystem();

        try {
            $filesystem->mkdir('/tmp/manager/scripts');
        } catch (IOExceptionInterface $exception) {
            // Already exists.
        }

        $filesystem->mirror('/app/scripts', '/tmp/manager/scripts');
    }
}
