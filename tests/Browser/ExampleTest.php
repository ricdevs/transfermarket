<?php

namespace Tests\Browser;

use App\Exports\PlayerExport;
use Laravel\Dusk\Browser;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Database\Eloquent\Collection;
use Tests\DuskTestCase;
use Maatwebsite\Excel\Facades\Excel;

class Player {
    public $teamId = 0;
    public $teamName = '';
    public $name = '';
    public $position = '';
    public $age = 0;
    public $nacionality = '';
    public $height = '';
    public $foot = '';
    public $contract_start = '';
    public $contract_end = '';
    public $market_value = 0;
}

class ExampleTest extends DuskTestCase
{

    public $rootUrl = 'https://www.transfermarkt.es';
    public $mainUrl = 'https://www.transfermarkt.es/wettbewerbe/europa';
    
    private $selectedLeagues = [
        'https://www.transfermarkt.es/premier-league/startseite/wettbewerb/GB1',
        'https://www.transfermarkt.es/laliga/startseite/wettbewerb/ES1',
        'https://www.transfermarkt.es/serie-a/startseite/wettbewerb/IT1',
        'https://www.transfermarkt.es/bundesliga/startseite/wettbewerb/L1',
        'https://www.transfermarkt.es/ligue-1/startseite/wettbewerb/FR1',
        'https://www.transfermarkt.es/liga-portugal/startseite/wettbewerb/PO1',
        'https://www.transfermarkt.es/eredivisie/startseite/wettbewerb/NL1', // -----> row 3637
        'https://www.transfermarkt.es/champions-league/startseite/pokalwettbewerb/CL', //Champions League
        //https://www.transfermarkt.es/uefa-champions-league/teilnehmer/pokalwettbewerb/CL/saison_id/2022 //Corrected CL
        'https://www.transfermarkt.es/campeonato-brasileiro-serie-a/startseite/wettbewerb/BRA1', // Brasil
        'https://www.transfermarkt.es/liga-profesional-de-futbol/startseite/wettbewerb/AR1N' // Argentina
    ];

    public $teamId = 1;

    public $leagueUrls = [];

    public $players = [];

    /**
     * A basic browser test example.
     *
     * @return void
     */
    public function testBasicExample()
    {
        ini_set('memory_limit', '1G');
        set_time_limit(0);

        $this->boot();
    }

    public function boot()
    {
        $this->browse(function (Browser $browser) {
            sleep(3);

            $this->closeAdBlockTab($browser);

            $browser->visit($this->mainUrl);

            // $this->extractLeagueUrls($browser);
            $this->hasCookieModal($browser);
            foreach ($this->selectedLeagues as $league) {
                $teamUrls = $this->extractLeagueTeams($browser, $league);
                foreach ($teamUrls as $teamUrl) {
                    $browser->visit($this->rootUrl . $teamUrl);
                    $this->hasCookieModal($browser);

                    $teamName = $this->getTeamName($browser);

                    $playerRows = $this->getPlayerRows($browser);
                    foreach ($playerRows as $playerRow) {
                        $players = $this->extractPlayerInfo($playerRow, $teamName);

                        array_push($this->players, $players);
                    }

                    $this->teamId++;
                    error_log("The " . strval(sizeof($playerRows)) . " players from " . $teamName . " have been extracted");
                }
                
                error_log("The entire " . $league . " league has been extracted");
                $this->exportPlayerData();
            }

            error_log("Finished");
        });
    }

    // public function extractLeagueUrls($browser) {
    //     $leagueElements = $browser->driver->findElements(WebDriverBy::xpath('//tr/td[1]/a'));
    //     foreach ($leagueElements as $link) {
    //         array_push($this->leagueUrls, $link->getAttribute('href'));
    //     }
    // }

    public function closeAdBlockTab($browser)
    {
        $openedWindows = collect($browser->driver->getWindowHandles());
        if (sizeof($openedWindows) > 1) {
            $browser->driver->switchTo()->window($openedWindows->first());
            
            return;
        }
    }

    public function hasCookieModal($browser)
    {
        sleep(1);
        $iframe = $browser->driver->findElements(WebDriverBy::xpath('//iframe[@title="SP Consent Message"]'));
        if (sizeOf($iframe) > 0) {
            $browser->withinFrame('iframe[title="SP Consent Message"]', function($browser){
                $browser->driver->findElement(WebDriverBy::xpath('//button[@title="Aceptar"]'))->click();
            });
        }
    }

    public function extractLeagueTeams($browser, $league) {
        $browser->visit($league);
        $this->hasCookieModal($browser);

        $teamUrls = [];
        
        $teamElems = $browser->driver->findElements(WebDriverBy::xpath('//div[@id="yw1"]//table[@class="items"]//tr/td[2]/a'));
        foreach ($teamElems as $link) {
            array_push($teamUrls, $link->getAttribute('href'));
        }

        $teamUrls = collect($teamUrls)->filter(function ($url, $key) {
            return $url != '#';
        })->all();

        return $teamUrls;
    }

    public function getTeamName($browser)
    {
        $teamName = '';

        $teamName = $browser->driver->findElement(WebDriverBy::xpath('//header[@class="data-header"]//h1'))->getText();

        return $teamName;
    }

    public function getPlayerRows($browser) {
        $rows = [];

        sleep(2);
        $browser->driver->findElement(WebDriverBy::xpath('//div[@class="tm-tabs"]/a[2]'))->click();

        $rows = $browser->driver->findElements(WebDriverBy::xpath('//table[@class="items"]/tbody/tr'));

        return $rows;
    }

    public function extractPlayerInfo($playerRow, $teamName) {
        $player = new Player();

        $player->teamId = $this->teamId;
        $player->teamName = $teamName;

        $player->name = $playerRow->findElement(WebDriverBy::xpath('./td[@class="posrela"]//td/a'))->getText();
        $player->position = $playerRow->findElement(WebDriverBy::xpath('./td[@class="posrela"]//table[@class="inline-table"]//tr[last()]/td'))->getText();

        $player->age = $playerRow->findElement(WebDriverBy::xpath('./td[@class="posrela"]/following-sibling::td[1]'))->getText();

        $nationality = $playerRow->findElement(WebDriverBy::xpath('./td[@class="posrela"]/following-sibling::td[2]'));
        $player->nacionality = $nationality->findElement(WebDriverBy::xpath('./img'))->getAttribute('title');

        $player->height = $playerRow->findElement(WebDriverBy::xpath('./td[@class="posrela"]/following-sibling::td[3]'))->getText();
        $player->foot = $playerRow->findElement(WebDriverBy::xpath('./td[@class="posrela"]/following-sibling::td[4]'))->getText();
        $player->contract_start = $playerRow->findElement(WebDriverBy::xpath('./td[@class="posrela"]/following-sibling::td[5]'))->getText();
        $player->contract_end = $playerRow->findElement(WebDriverBy::xpath('./td[@class="posrela"]/following-sibling::td[7]'))->getText();
        $player->market_value = $playerRow->findElement(WebDriverBy::xpath('./td[@class="posrela"]/following-sibling::td[8]'))->getText();

        return $player;
    }

    public function exportPlayerData() {
        $data = $this->players;

        $export = new PlayerExport($data);

        return Excel::store($export, 'leaguedata.xlsx');
    }
}
