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
    public $cronTtl = 1;
    public $instanceTtl = 15;

    private static $allowedPairs = ['USDT', 'BTC', 'ETH', 'USD', 'EUR', 'BNB', 'USDC', 'BUSD'];

    public function getSlug(): string
    {
        return 'tradingViewScan';
    }

    public function updateCron(): void
    {
        parent::updateCron();

        $pairLists = [];
        $searchTypes = $this->getSortTypesPayloads();
        foreach ($searchTypes as $type => $requestPayload) {
            $requestPayload = json_encode(json_decode(trim($requestPayload), true));
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
        $searchType = $instanceBehaviourConfig['searchType'] ?? '5mChangePercent';
        $pairsCount = $instanceBehaviourConfig['pairsCount'] ?? 40;

        $exchangeKey = strtoupper($instance->config['exchange']['name']);
        $pairList = $this->data['pairLists'][$searchType][$exchangeKey][$instance->config['stake_currency']] ?? [];
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

        $pairList = [];
        $scanData = json_decode((string) $response->getBody(), true);
        foreach ($scanData['data'] as $data) {
            if (false !== strpos($data['d'][0], '_PREMIUM')) {
                continue;
            }

            $pair = $data['d'][0];
            $exchange = $data['d'][1];
            foreach (self::$allowedPairs as $allowedPair) {
                if ($allowedPair === substr($pair, -strlen($allowedPair))) {
                    $pairList[$exchange][$allowedPair][] = str_replace($allowedPair, '/'.$allowedPair, $pair);
                    continue 2;
                }
            }
        }

        return $pairList;
    }

    private function getSortTypesPayloads(): array
    {
        return [
            '1mChangePercent' => <<<EOF
                {
                    "filter": [
                        {
                            "left": "change",
                            "operation": "nempty"
                        },
                        {
                            "left": "change",
                            "operation": "greater",
                            "right": 0
                        }
                    ],
                    "options": {
                        "active_symbols_only": true,
                        "lang": "fr"
                    },
                    "columns": [
                        "name",
                        "exchange",
                        "change"
                    ],
                    "sort": {
                        "sortBy": "change|1m",
                        "sortOrder": "desc"
                    },
                    "range": [
                        0,
                        5000
                    ]
                }
            EOF,
            '5mChangePercent' => <<<EOF
                {
                    "filter": [
                        {
                            "left": "change",
                            "operation": "nempty"
                        },
                        {
                            "left": "change",
                            "operation": "greater",
                            "right": 0
                        }
                    ],
                    "options": {
                        "active_symbols_only": true,
                        "lang": "fr"
                    },
                    "columns": [
                        "name",
                        "exchange",
                        "change"
                    ],
                    "sort": {
                        "sortBy": "change|5m",
                        "sortOrder": "desc"
                    },
                    "range": [
                        0,
                        5000
                    ]
                }
            EOF,
        ];
    }
}
