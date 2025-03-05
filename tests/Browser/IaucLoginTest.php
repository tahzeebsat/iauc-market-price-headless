<?php

namespace Tests\Browser;

use App\Models\IaucMarketPriceLog;
use App\Models\IaucModel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Facebook\WebDriver\WebDriverBy;

class IaucLoginTest extends DuskTestCase
{
    public function testIaucMarketPrice(): void
    {
        $this->browse(function (Browser $browser) {

            /* Select make and model */
            $scrapping_model = IaucModel::leftJoin('iauc_market_price_logs', 'iauc_models.id', '=', 'iauc_market_price_logs.model_id')
                ->join('iauc_makes', 'iauc_models.iauc_make_id', '=', 'iauc_makes.id')
                ->whereNull('iauc_market_price_logs.id')
                ->select(['iauc_models.id as model_id', 'iauc_makes.name as make_name', 'iauc_makes.sat_name as sat_name', 'iauc_models.name as model_name', 'iauc_models.sat_name as iauc_model'])
                ->first();

            if(!$scrapping_model) {
                Log::info('Model not found for iauc market price scrapping.');
                return;
            }

            $browser->script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})");
            $browser->visit('https://www.iauc.co.jp/');
            $browser->assertSee('適格請求書発行事業者登録完了のお知らせ');
            $browser->click('a[href="service/"]');
            $browser->assertSee('ログイン');
            $browser->click('.login-btn');
            $browser->assertSee('ID/PASSWORDを保存する Remember ID/Password on this computer.');
            $browser->type('.login_id', 'A07237701');
            $browser->type('.login_password', '391112');
            $browser->click('#login_button');

            if ($browser->element('form[action="/service/clear_session"]')) {
                $browser->click('.button-yes');
            }

            $browser->assertSee('A07237701');

            $browser->script("document.getElementById('gmenu_market').removeAttribute('target');");
            $browser->click('#gmenu_market')
                ->waitForLocation('/market/', 30)
                ->element('#annotation_soba');

            $browser->waitFor('.site-button.checkbox_on_all', 30)
                ->click('.site-button.checkbox_on_all');

            $browser->click('.page-next-button');
            $browser->assertSee('※複数選択できます。');

            $browser->click('#toggle_lang');
            $browser->assertSee('※select multiple Make & Model');
            $browser->select('#searchPeriod', '12');

            $make = $scrapping_model->make_name;
            $model = $scrapping_model->model_name;
            $model_id = $scrapping_model->model_id;

            // Select Make dynamically
            $makeElements = $browser->elements('.search-maker-right');
            foreach ($makeElements as $element) {
                if (trim($element->getText()) === $make) {
                    $element->click();
                    break;
                }
            }

            // Select Model dynamically
            $modelElements = $browser->elements('#vehicle.search-page div#box-type ul li div + div span');
            foreach ($modelElements as $element) {
                if (trim($element->getText()) === $model) {
                    $element->click();
                    break;
                }
            }

            Sleep::for(500)->milliseconds();
            $browser->click('#next-bottom');

            $browser->waitFor('#carlist', 30);
            $browser->select('#select_limit', '15');
            Sleep::for(2)->seconds();

            do {
                $browser->waitFor('#carlist', 30);
                $rows = $browser->elements('#carlist tr');
                $cars = [];

                foreach ($rows as $index => $row) {
                    if ($row->getAttribute('id') && strpos($row->getAttribute('class'), 'line-auction') !== false) {
                        $carData = [];

                        try {
                            $nameElement = $row->findElement(WebDriverBy::cssSelector('.col3.open-detail'));
                            $carData['name'] = $nameElement
                                ? mb_convert_kana(trim(preg_replace('/\s+/u', ' ', $nameElement->getText())), "askh")
                                : '';
                            $carData['name'] = preg_replace('/[^\x20-\x7E]/u', '', $carData['name']);
                        } catch (\Exception $e) {
                            $carData['name'] = '';
                        }

                        $nextRow = isset($rows[$index + 1]) ? $rows[$index + 1] : null;
                        if ($nextRow && strpos($nextRow->getAttribute('class'), 'line-auction') !== false) {
                            try {
                                $yearElement = $nextRow->findElement(WebDriverBy::cssSelector('.col5.open-detail p:first-child'));
                                $carData['year'] = $yearElement
                                    ? mb_convert_kana(trim($yearElement->getText()), "askh")
                                    : '';
                            } catch (\Exception $e) {
                                $carData['year'] = '';
                            }

                            try {
                                $priceElement = $nextRow->findElement(WebDriverBy::cssSelector('.col12.open-detail div'));
                                if ($priceElement) {
                                    $priceText = trim($priceElement->getText());
                                    $priceParts = explode("\n", $priceText);
                                    $carData['price'] = isset($priceParts[1]) ? trim($priceParts[1]) : '';
                                } else {
                                    $carData['price'] = '';
                                }
                            } catch (\Exception $e) {
                                $carData['price'] = '';
                            }
                        }

                        if (!empty($carData['name']) && !empty($carData['year']) && !empty($carData['price'])) {
                            $cars[] = $carData;
                        }
                    }
                }

                if (!empty($cars)) {
                    $response = Http::post(config('app.remote_api_url').'/api/iauc/submit-market-price-request', [
                        'data' => $cars,
                        'make' => $make
                    ]);
                }

                $nextButton = $browser->element('#pager-link-next');
                if ($nextButton) {
                    $browser->click('#pager-link-next');

                    // Optionally, wait until the loading element is visible (display:block)
                    $browser->waitUsing(10, 500, function () use ($browser) {
                        $display = $browser->script(
                            'return window.getComputedStyle(document.getElementById("loading")).display;'
                        )[0];
                        return $display === 'block';
                    });

                    // Now wait until the loading element is hidden (display:none)
                    $browser->waitUsing(30, 500, function () use ($browser) {
                        $display = $browser->script(
                            'return window.getComputedStyle(document.getElementById("loading")).display;'
                        )[0];
                        return $display === 'none';
                    });
                }

                IaucMarketPriceLog::updateOrCreate(
                    [
                        'model_id' => $model_id,
                    ],
                    [
                        'status' => 'processing'
                    ]
                );


            } while ($nextButton);

            IaucMarketPriceLog::updateOrCreate(
                [
                    'model_id' => $model_id,
                ],
                [
                    'status' => 'success'
                ]
            );


        });

    }
}
