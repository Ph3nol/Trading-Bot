<?php

namespace Manager\Infra\Process;

use Manager\Domain\Instance;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class ManagerProcess
{
    public static function getDockerStatus(): string
    {
        $commandLine = 'docker ps --format \'{{ .ID }};;;{{ .Names }};;;{{.Image}};;;{{ .Status }}\'';
        $dockerStatusOutput = trim(Process::processCommandLine($commandLine, false));

        return $dockerStatusOutput;
    }
}
