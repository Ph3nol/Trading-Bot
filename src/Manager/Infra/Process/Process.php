<?php

namespace Manager\Infra\Process;

use Symfony\Component\Process\Process as BaseProcess;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
final class Process
{
    public static function processCommandLine(string $commandLine, bool $withException = true): ?string
    {
        $process = BaseProcess::fromShellCommandline($commandLine);
        $process->run();

        if (!$process->isSuccessful() && $withException) {
            throw new ProcessFailedException($process);
        }

        if (!$process->isSuccessful()) {
            return null;
        }

        return $process->getOutput();
    }
}
