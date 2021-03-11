const puppeteer = require('puppeteer');

if (!process.env.BINANCE_SCRAPPER_TYPE) {
    console.log([])

    return
}

const volumePercent24hCases = {
    USDT: {
        fiatClick: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[1]/div[1]/div/button[4]',
        pairButton: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[2]/div/div/div[3]',
        variation24hFilterButton: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[3]/div/div[1]/div[4]/div/div',
        pairListColumn: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[3]/div/div[2]/div/div/div/self::div/div/div[2]/div[1]'
    },
    EUR: {
        fiatClick: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[1]/div[1]/div/button[4]',
        pairButton: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[2]/div/div/div[9]',
        variation24hFilterButton: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[3]/div/div[1]/div[4]',
        pairListColumn: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[3]/div/div[2]/div/div/div/self::div/div/div[2]/div[1]'
    },
    BTC: {
        fiatClick: false,
        pairButton: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[1]/div[1]/div/button[2]',
        variation24hFilterButton: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[2]/div/div[1]/div[4]',
        pairListColumn: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[2]/div/div[2]/div/div/div/self::div/div/div[2]/div[1]'
    },
    BNB: {
        fiatClick: false,
        pairButton: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[1]/div[1]/div/button[1]',
        variation24hFilterButton: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[2]/div/div[1]/div[4]',
        pairListColumn: '//*[@id="__APP"]/div[1]/main/div/div[2]/div/div/div[2]/div[2]/div/div[2]/div/div/div/self::div/div/div[2]/div[1]'
    }
};

const getScrappedVolumePercent24hPairlist = async function (page) {
    let volumePercent24hResults = {};

    for (const currency in volumePercent24hCases) {
        // await page.goto('https://www.binance.com/fr/markets', { waitUntil: 'networkidle2' })
        await page.goto('https://www.binance.com/fr/markets')
        // await page.screenshot({ path: '/screens/0_markets_home.jpg' })

        const scrapPayloadXPaths = volumePercent24hCases[currency]

        if (scrapPayloadXPaths.fiatClick) {
            // console.debug('Fiats button click...')
            await page.waitForXPath(scrapPayloadXPaths.fiatClick)
            fiatsButton = await page.$x(scrapPayloadXPaths.fiatClick)
            await fiatsButton[0].click()
            // await page.screenshot({ path: '/screens/1_markets_fiat_click.jpg' })
        }

        if (scrapPayloadXPaths.pairButton) {
            // console.debug('Pair filter button click...')
            await page.waitForXPath(scrapPayloadXPaths.pairButton)
            pairButton = await page.$x(scrapPayloadXPaths.pairButton)
            await pairButton[0].click()
            // await page.screenshot({ path: '/screens/2_markets_pairs.jpg' })
        }

        // console.debug('24h variation % filter click...')
        await page.waitForXPath(scrapPayloadXPaths.variation24hFilterButton)
        variation24hButton = await page.$x(scrapPayloadXPaths.variation24hFilterButton)
        await variation24hButton[0].click()
        await variation24hButton[0].click()
        // await page.screenshot({ path: '/screens/3_variation_24h.jpg' })

        // console.debug('Scraping pairs...')
        let pairsList = []
        await page.waitForXPath(scrapPayloadXPaths.pairListColumn)
        // await page.screenshot({ path: '/screens/4_scrap.jpg' })
        let pairsRows = await page.$x(scrapPayloadXPaths.pairListColumn)
        for (let pairIndex in pairsRows) {
            pairsList.push(
                await page.evaluate(element => element.textContent, pairsRows[pairIndex])
            )
        }

        volumePercent24hResults[currency] = pairsList
    }

    return volumePercent24hResults
};

(async () => {
    const browser = await puppeteer.launch({
        executablePath: process.env.CHROME_BIN || null,
        args: ['--no-sandbox', '--headless', '--disable-gpu']
    })

    // console.debug('Binance markets access...')
    const page = await browser.newPage()
    await page.setViewport({ width: 1366, height: 3000 })
    await page.setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36')

    switch (process.env.BINANCE_SCRAPPER_TYPE) {
        case 'volumePercent24hPairlist':
            try {
                console.log(JSON.stringify(
                    await getScrappedVolumePercent24hPairlist(page)
                ))
            } catch (e) {
                console.log([])
            }
        break
        default:
            console.log([])
        break
    }

    await browser.close()
})();
