<?php

namespace Manager\Infra\Process;

use Symfony\Component\Process\Process as BaseProcess;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
final class Process
{
    public static function processCommandLine(string $commandLine, bool $withException = true, bool $unlimited = false): ?string
    {
        $process = BaseProcess::fromShellCommandline($commandLine);

        if ($unlimited) {
            $process->setTimeout(0);
        }

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            return null;
        }

        if (!$process->isSuccessful() && $withException) {
            throw new ProcessFailedException($process);
        }

        if (!$process->isSuccessful()) {
            return null;
        }

        return $process->getOutput();
    }
}
