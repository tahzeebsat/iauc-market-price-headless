<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Facebook\WebDriver\WebDriverBy;

class IaucLoginTest extends DuskTestCase
{
    public function testLogin(): void
    {
        $this->browse(function (Browser $browser) {
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

            $make = 'TOYOTA';

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
                if (trim($element->getText()) === "86") {
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

                Log::info('Scraped Car Data:', $cars);

                if (!empty($cars)) {
                    $response = Http::post('http://sat.local/api/iauc/submit-market-price-request', [
                        'data' => $cars,
                        'make' => $make
                    ]);
                    Log::info('API Response:', $response->json());
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

            } while ($nextButton);


        });

    }
}
