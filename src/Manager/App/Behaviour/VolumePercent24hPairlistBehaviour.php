<?php

namespace Manager\App\Behaviour;

use Manager\Domain\Instance;
use Manager\Infra\Process\Process;
use Manager\Infra\Filesystem\ManagerFilesystem;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class VolumePercent24hPairlistBehaviour extends AbstractBehaviour
{
    public $cronTtl = 10;
    public $instanceTtl = 30;

    public function getSlug(): string
    {
        return 'volumePercent24hPairlist';
    }

    public function updateFromCron(): void
    {
        parent::updateFromCron();

        $this->data = array_merge($this->data, [
            'pairLists' => $this->scrapDataFromBinance(),
        ]);
    }

    public function updateInstance(Instance $instance): Instance
    {
        parent::updateInstance($instance);

        $pairList = $this->data['pairLists'][$instance->config['stake_currency']] ?? [];
        if ($pairList) {
            $instance->config['exchange']['pair_whitelist'] = $pairList;

            foreach ($instance->config['pairlists'] ?? [] as $k => $pairlistEntry) {
                if (in_array($pairlistEntry['method'], ['StaticPairList', 'VolumePairList'])) {
                    $instance->config['pairlists'][$k] = [
                        'method' => 'StaticPairList',
                    ];
                }
            }
        }

        return $instance;
    }

    private function scrapDataFromBinance(): array
    {
        $processCommand = [
            sprintf('docker run --rm --name trading-bot-behaviour-%s-binance-scrapper', $this->getSlug()),
            sprintf('-e BINANCE_SCRAPPER_TYPE=%s', $this->getSlug()),
            sprintf('-v %s:/app/index.js', '/tmp/freqtrade-manager/resources/scripts/binance-scrapper.js'),
            'alekzonder/puppeteer:latest',
        ];

        $process = Process::processCommandLine(implode(' ', $processCommand), false);
        if (null === $process) {
            return [];
        }

        $pairLists = json_decode($process, true) ?? [];
        $pairLists = array_map(function (array $pairList): array {
            return array_slice(array_unique($pairList ?: []), 0, 50);
        }, $pairLists);

        return $pairLists;
    }
}
