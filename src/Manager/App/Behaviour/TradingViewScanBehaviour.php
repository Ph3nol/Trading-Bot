<?php

namespace Manager\App\Behaviour;

use Manager\Domain\Instance;
use Manager\Infra\Process\Process;
use Manager\Infra\Filesystem\ManagerFilesystem;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class TradingViewScanBehaviour extends AbstractBehaviour
{
    public $cronTtl = 10;
    public $instanceTtl = 30;

    private static $allowedPairs = ['USDT', 'BTC', 'ETH', 'USD', 'EUR', 'BNB', 'USDC', 'BUSD'];

    public function getSlug(): string
    {
        return 'tradingViewScan';
    }

    public function updateCron(): void
    {
        parent::updateCron();

        $sortType = [
            'performance' => '{"filter":[{"left":"change","operation":"nempty"}],"options":{"active_symbols_only":true,"lang":"fr"},"symbols":{"query":{"types":[]},"tickers":[]},"columns":["base_currency_logoid","currency_logoid","name","exchange"],"sort":{"sortBy":"change|5m","sortOrder":"desc"},"range":[0,2000]}',
            'recommendation' => '{"filter":[{"left":"change","operation":"nempty"}],"options":{"active_symbols_only":true,"lang":"fr"},"symbols":{"query":{"types":[]},"tickers":[]},"columns":["base_currency_logoid","currency_logoid","name","exchange"],"sort":{"sortBy":"Recommend.Other|5m","sortOrder":"desc"},"range":[0,2000]}',
        ];

        $pairLists = [];
        foreach ($sortType as $type => $requestPayload) {
            $pairLists[$type] = $this->scrapPairlistsFromTW($requestPayload);
        }

        $this->data = array_merge($this->data, [
            'pairLists' => $pairLists,
        ]);
    }

    public function updateInstance(Instance $instance): Instance
    {
        parent::updateInstance($instance);

        $instanceBehaviourConfig = $instance->getBehaviourConfig($this);
        $sortType = $instanceBehaviourConfig['sortType'] ?? 'performance';
        $pairsCount = $instanceBehaviourConfig['pairsCount'] ?? 40;

        $exchangeKey = strtoupper($instance->config['exchange']['name']);
        $pairList = $this->data['pairLists'][$sortType][$exchangeKey][$instance->config['stake_currency']] ?? [];
        if ($pairList) {
            $instance->updateStaticPairList(
                array_slice(array_unique($pairList), 0, $pairsCount + 1)
            );
        }

        return $instance;
    }

    public function scrapPairlistsFromTW(string $requestPayload): array
    {
        /**
         * https://fr.tradingview.com/crypto-screener/
         */
        $client = new \GuzzleHttp\Client(
            [
                'base_uri' => 'https://scanner.tradingview.com',
            ]
        );
        $response = $client->request('POST', '/crypto/scan', [
            'body' => $requestPayload,
            'headers' => [
                'authority' => 'scanner.tradingview.com',
                'origin' => 'https://fr.tradingview.com',
                'content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.192 Safari/537.36',
            ],
        ]);

        $pairLists = [];
        $scanData = json_decode((string) $response->getBody(), true);
        foreach ($scanData['data'] as $data) {
            if (false !== strpos($data['d'][2], '_PREMIUM')) {
                continue;
            }

            $pair = $data['d'][2];
            foreach (self::$allowedPairs as $allowedPair) {
                if ($allowedPair === substr($pair, -strlen($allowedPair))) {
                    $pairLists[$data['d'][3]][$allowedPair][] = str_replace($allowedPair, '/'.$allowedPair, $pair);
                    continue 2;
                }
            }
        }

        foreach ($pairLists as $exchange => $pairList) {
            $pairLists[$exchange] = array_map(function (array $pairList): array {
                return array_slice(array_unique($pairList ?: []), 0, 100);
            }, $pairLists[$exchange]);
        }

        return $pairLists;
    }
}
