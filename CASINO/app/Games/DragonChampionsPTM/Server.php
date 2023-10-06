<?php 
namespace VanguardLTE\Games\DragonChampionsPTM
{
    set_time_limit(5);
    class Server
    {
        public function get($request, $game)
        {
            function get_($request, $game)
            {
                \DB::transaction(function() use ($request, $game)
                {
                    try
                    {
                        $userId = \Auth::id();
                        if( $userId == null ) 
                        {
                            $response = '{"responseEvent":"error","responseType":"","serverResponse":"invalid login"}';
                            exit( $response );
                        }
                        $slotSettings = new SlotSettings($game, $userId);
                        if( !$slotSettings->is_active() ) 
                        {
                            $response = '{"responseEvent":"error","responseType":"","serverResponse":"Game is disabled"}';
                            exit( $response );
                        }
                        $postData = json_decode(trim(file_get_contents('php://input')), true);
                        $balanceInCents = sprintf('%01.2f', $slotSettings->GetBalance()) * 100;
                        $result_tmp = [];
                        if( isset($postData['umid']) ) 
                        {
                            $umid = $postData['umid'];
                            if( isset($postData['ID']) ) 
                            {
                                $umid = $postData['ID'];
                            }
                        }
                        else
                        {
                            if( isset($postData['ID']) ) 
                            {
                                $result_tmp[] = '3:::{"ID":18}';
                                $result_tmp[] = '3:::{"data":{"typeBalance":0,"currency":"' . $slotSettings->slotCurrency . '","balanceInCents":' . $balanceInCents . ',"deltaBalanceInCents":1},"ID":40085}';
                            }
                            $umid = 0;
                        }
                        if( isset($postData['spinType']) ) 
                        {
                            $result_tmp = [];
                            if( $postData['spinType'] == 'regular' ) 
                            {
                                $umid = '0';
                                $postData['slotEvent'] = 'bet';
                                $bonusMpl = 1;
                                $slotSettings->SetGameData('DragonChampionsPTMBonusWin', 0);
                                $slotSettings->SetGameData('DragonChampionsPTMFreeGames', 0);
                                $slotSettings->SetGameData('DragonChampionsPTMCurrentFreeGame', 0);
                                $slotSettings->SetGameData('DragonChampionsPTMTotalWin', 0);
                                $slotSettings->SetGameData('DragonChampionsPTMFreeBalance', 0);
                                $slotSettings->SetGameData('DragonChampionsPTMFreeStartWin', 0);
                            }
                            else if( $postData['spinType'] == 'free' ) 
                            {
                                $umid = '0';
                                $postData['slotEvent'] = 'freespin';
                                $slotSettings->SetGameData('DragonChampionsPTMCurrentFreeGame', $slotSettings->GetGameData('DragonChampionsPTMCurrentFreeGame') + 1);
                                $bonusMpl = 1;
                            }
                            $postData['bet'] = $postData['bet'] / 100;
                            $lines = 40;
                            $betLine = $postData['bet'] / $lines;
                            if( $postData['slotEvent'] == 'bet' || $postData['slotEvent'] == 'freespin' || $postData['slotEvent'] == 'respin' ) 
                            {
                                if( $lines <= 0 || $betLine <= 0.0001 ) 
                                {
                                    $response = '{"responseEvent":"error","responseType":"' . $postData['slotEvent'] . '","serverResponse":"invalid bet state"}';
                                    exit( $response );
                                }
                                if( $slotSettings->GetBalance() < ($lines * $betLine) ) 
                                {
                                    $response = '{"responseEvent":"error","responseType":"' . $postData['slotEvent'] . '","serverResponse":"invalid balance"}';
                                    exit( $response );
                                }
                                if( $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') < $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') && $postData['slotEvent'] == 'freespin' ) 
                                {
                                    $response = '{"responseEvent":"error","responseType":"' . $postData['slotEvent'] . '","serverResponse":"invalid bonus state"}';
                                    exit( $response );
                                }
                            }
                            if( $postData['slotEvent'] != 'freespin' ) 
                            {
                                if( !isset($postData['slotEvent']) ) 
                                {
                                    $postData['slotEvent'] = 'bet';
                                }
                                $bankSum = $postData['bet'] / 100 * $slotSettings->GetPercent();
                                $slotSettings->SetBank((isset($postData['slotEvent']) ? $postData['slotEvent'] : ''), $bankSum, $postData['slotEvent']);
                                $slotSettings->UpdateJackpots($postData['bet']);
                                $slotSettings->SetBalance(-1 * $postData['bet'], $postData['slotEvent']);
                            }
                            $winTypeTmp = $slotSettings->GetSpinSettings($postData['slotEvent'], $postData['bet'], $lines);
                            $winType = $winTypeTmp[0];
                            $spinWinLimit = $winTypeTmp[1];
                            for( $i = 0; $i <= 2000; $i++ ) 
                            {
                                $totalWin = 0;
                                $lineWins = [];
                                $wild = '11';
                                $scatter = '12';
                                $reels = $slotSettings->GetReelStrips($winType, $postData['slotEvent']);
                                for( $r = 1; $r <= 6; $r++ ) 
                                {
                                    for( $s = 0; $s <= 3; $s++ ) 
                                    {
                                        if( $reels['reel' . $r][$s] == '13' ) 
                                        {
                                            $reels['reel' . $r][$s] = '10';
                                        }
                                    }
                                }
                                $dbgstr = '';
                                for( $j = 0; $j < count($slotSettings->SymbolGame); $j++ ) 
                                {
                                    $csym = $slotSettings->SymbolGame[$j];
                                    $wsym = [
                                        0, 
                                        0, 
                                        0, 
                                        0, 
                                        0, 
                                        0
                                    ];
                                    $wildsym = [
                                        0, 
                                        0, 
                                        0, 
                                        0, 
                                        0, 
                                        0
                                    ];
                                    $cntsym = [
                                        0, 
                                        0, 
                                        0, 
                                        0, 
                                        0, 
                                        0
                                    ];
                                    $cWin = 0;
                                    $sMpl = 1;
                                    $offsetMpl = 1;
                                    $offsetMpl0 = 1;
                                    if( $csym == $scatter || !isset($slotSettings->Paytable['SYM_' . $csym]) ) 
                                    {
                                    }
                                    else
                                    {
                                        for( $r = 1; $r <= 6; $r++ ) 
                                        {
                                            for( $s = 0; $s <= 3; $s++ ) 
                                            {
                                                if( $reels['reel' . $r][$s] == $csym || $reels['reel' . $r][$s] == $wild ) 
                                                {
                                                    $wsym[$r - 1] = 1;
                                                    $cntsym[$r - 1]++;
                                                }
                                                if( $reels['reel' . $r][$s] == $wild ) 
                                                {
                                                    $wildsym[$r - 1] = 1;
                                                }
                                            }
                                        }
                                        if( $wsym[0] > 0 && $wsym[1] > 0 ) 
                                        {
                                            $sMpl = 1;
                                            $offsetMpl = 1;
                                            $offsetMpl0 = 1;
                                            for( $r = 1; $r <= 2; $r++ ) 
                                            {
                                                if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $sMpl = $sMpl * $cntsym[$r - 1];
                                                }
                                                if( $wildsym[$r - 1] > 0 && $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * 1;
                                                    $offsetMpl0 = $offsetMpl0 * ($cntsym[$r - 1] - 1);
                                                }
                                                else if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * $cntsym[$r - 1];
                                                    $offsetMpl0 = $offsetMpl0 * $cntsym[$r - 1];
                                                }
                                            }
                                            $cWin = $slotSettings->Paytable['SYM_' . $csym][2] * $betLine * $sMpl;
                                            if( ($wildsym[0] > 0 || $wildsym[1] > 0) && $bonusMpl > 1 ) 
                                            {
                                                $cWin0 = $slotSettings->Paytable['SYM_' . $csym][2] * $betLine * $offsetMpl * $bonusMpl;
                                                $cWin = $slotSettings->Paytable['SYM_' . $csym][2] * $betLine * $offsetMpl0;
                                                $cWin += $cWin0;
                                            }
                                        }
                                        if( $wsym[0] > 0 && $wsym[1] > 0 && $wsym[2] > 0 ) 
                                        {
                                            $sMpl = 1;
                                            $offsetMpl = 1;
                                            $offsetMpl0 = 1;
                                            for( $r = 1; $r <= 3; $r++ ) 
                                            {
                                                if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $sMpl = $sMpl * $cntsym[$r - 1];
                                                }
                                                if( $wildsym[$r - 1] > 0 && $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * 1;
                                                    $offsetMpl0 = $offsetMpl0 * ($cntsym[$r - 1] - 1);
                                                }
                                                else if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * $cntsym[$r - 1];
                                                    $offsetMpl0 = $offsetMpl0 * $cntsym[$r - 1];
                                                }
                                            }
                                            $tWin = $slotSettings->Paytable['SYM_' . $csym][3] * $betLine * $sMpl;
                                            if( ($wildsym[0] > 0 || $wildsym[1] > 0 || $wildsym[2] > 0) && $bonusMpl > 1 ) 
                                            {
                                                $tWin0 = $slotSettings->Paytable['SYM_' . $csym][3] * $betLine * $offsetMpl * $bonusMpl;
                                                $tWin = $slotSettings->Paytable['SYM_' . $csym][3] * $betLine * $offsetMpl0;
                                                $tWin += $tWin0;
                                            }
                                            if( $cWin < $tWin ) 
                                            {
                                                $cWin = $tWin;
                                            }
                                        }
                                        if( $wsym[0] > 0 && $wsym[1] > 0 && $wsym[2] > 0 && $wsym[3] > 0 ) 
                                        {
                                            $sMpl = 1;
                                            $offsetMpl = 1;
                                            $offsetMpl0 = 1;
                                            for( $r = 1; $r <= 4; $r++ ) 
                                            {
                                                if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $sMpl = $sMpl * $cntsym[$r - 1];
                                                }
                                                if( $wildsym[$r - 1] > 0 && $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * 1;
                                                    $offsetMpl0 = $offsetMpl0 * ($cntsym[$r - 1] - 1);
                                                }
                                                else if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * $cntsym[$r - 1];
                                                    $offsetMpl0 = $offsetMpl0 * $cntsym[$r - 1];
                                                }
                                            }
                                            $tWin = $slotSettings->Paytable['SYM_' . $csym][4] * $betLine * $sMpl;
                                            if( ($wildsym[0] > 0 || $wildsym[1] > 0 || $wildsym[2] > 0 || $wildsym[3] > 0) && $bonusMpl > 1 ) 
                                            {
                                                $tWin0 = $slotSettings->Paytable['SYM_' . $csym][4] * $betLine * $offsetMpl * $bonusMpl;
                                                $tWin = $slotSettings->Paytable['SYM_' . $csym][4] * $betLine * $offsetMpl0;
                                                $tWin += $tWin0;
                                            }
                                            if( $cWin < $tWin ) 
                                            {
                                                $cWin = $tWin;
                                            }
                                        }
                                        if( $wsym[0] > 0 && $wsym[1] > 0 && $wsym[2] > 0 && $wsym[3] > 0 && $wsym[4] > 0 ) 
                                        {
                                            $sMpl = 1;
                                            $offsetMpl = 1;
                                            $offsetMpl0 = 1;
                                            for( $r = 1; $r <= 5; $r++ ) 
                                            {
                                                if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $sMpl = $sMpl * $cntsym[$r - 1];
                                                }
                                                if( $wildsym[$r - 1] > 0 && $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * 1;
                                                    $offsetMpl0 = $offsetMpl0 * ($cntsym[$r - 1] - 1);
                                                }
                                                else if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * $cntsym[$r - 1];
                                                    $offsetMpl0 = $offsetMpl0 * $cntsym[$r - 1];
                                                }
                                            }
                                            $tWin = $slotSettings->Paytable['SYM_' . $csym][5] * $betLine * $sMpl;
                                            if( ($wildsym[0] > 0 || $wildsym[1] > 0 || $wildsym[2] > 0 || $wildsym[3] > 0 || $wildsym[4] > 0) && $bonusMpl > 1 ) 
                                            {
                                                $tWin0 = $slotSettings->Paytable['SYM_' . $csym][5] * $betLine * $offsetMpl * $bonusMpl;
                                                $tWin = $slotSettings->Paytable['SYM_' . $csym][5] * $betLine * $offsetMpl0;
                                                $tWin += $tWin0;
                                            }
                                            if( $cWin < $tWin ) 
                                            {
                                                $cWin = $tWin;
                                            }
                                        }
                                        if( $wsym[0] > 0 && $wsym[1] > 0 && $wsym[2] > 0 && $wsym[3] > 0 && $wsym[4] > 0 && $wsym[5] > 0 ) 
                                        {
                                            $sMpl = 1;
                                            $offsetMpl = 1;
                                            $offsetMpl0 = 1;
                                            for( $r = 1; $r <= 6; $r++ ) 
                                            {
                                                if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $sMpl = $sMpl * $cntsym[$r - 1];
                                                }
                                                if( $wildsym[$r - 1] > 0 && $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * 1;
                                                    $offsetMpl0 = $offsetMpl0 * ($cntsym[$r - 1] - 1);
                                                }
                                                else if( $cntsym[$r - 1] > 0 ) 
                                                {
                                                    $offsetMpl = $offsetMpl * $cntsym[$r - 1];
                                                    $offsetMpl0 = $offsetMpl0 * $cntsym[$r - 1];
                                                }
                                            }
                                            $tWin = $slotSettings->Paytable['SYM_' . $csym][6] * $betLine * $sMpl;
                                            if( ($wildsym[0] > 0 || $wildsym[1] > 0 || $wildsym[2] > 0 || $wildsym[3] > 0 || $wildsym[4] > 0 || $wildsym[5] > 0) && $bonusMpl > 1 ) 
                                            {
                                                $tWin0 = $slotSettings->Paytable['SYM_' . $csym][6] * $betLine * $offsetMpl * $bonusMpl;
                                                $tWin = $slotSettings->Paytable['SYM_' . $csym][6] * $betLine * $offsetMpl0;
                                                $tWin += $tWin0;
                                            }
                                            if( $cWin < $tWin ) 
                                            {
                                                $cWin = $tWin;
                                            }
                                        }
                                        $dbgstr .= ('_' . $sMpl . '|' . $offsetMpl . '|' . $offsetMpl0);
                                        $totalWin += $cWin;
                                    }
                                }
                                $scattersWin = 0;
                                $scattersStr = '{';
                                $scattersCount = 0;
                                $wCount = 0;
                                for( $r = 1; $r <= 6; $r++ ) 
                                {
                                    for( $p = 0; $p <= 3; $p++ ) 
                                    {
                                        if( $reels['reel' . $r][$p] == $scatter ) 
                                        {
                                            $scattersCount++;
                                            $scattersStr .= ('"winReel' . $r . '":[' . $p . ',"' . $scatter . '"],');
                                        }
                                    }
                                }
                                $scattersWin = $slotSettings->Paytable['SYM_' . $scatter][$scattersCount] * $betLine * $lines * $bonusMpl;
                                if( $scattersCount >= 3 && $slotSettings->slotBonus ) 
                                {
                                    $scattersStr .= '"scattersType":"bonus",';
                                }
                                else if( $scattersWin > 0 ) 
                                {
                                    $scattersStr .= '"scattersType":"win",';
                                }
                                else
                                {
                                    $scattersStr .= '"scattersType":"none",';
                                }
                                $scattersStr .= ('"scattersWin":' . $scattersWin . '}');
                                $totalWin += $scattersWin;
                                if( $slotSettings->MaxWin < ($totalWin * $slotSettings->CurrentDenom) ) 
                                {
                                }
                                else
                                {
                                    if( $i > 1000 ) 
                                    {
                                        $winType = 'none';
                                    }
                                    if( $i > 1500 ) 
                                    {
                                        $response = '{"responseEvent":"error","responseType":"' . $postData['slotEvent'] . '","serverResponse":"' . $totalWin . ' Bad Reel Strip"}';
                                        exit( $response );
                                    }
                                    $minWin = $slotSettings->GetRandomPay();
                                    if( $i > 700 ) 
                                    {
                                        $minWin = 0;
                                    }
                                    if( $slotSettings->increaseRTP && $winType == 'win' && $totalWin < ($minWin * $postData['bet']) ) 
                                    {
                                    }
                                    else
                                    {
                                        if( $i > 1500 ) 
                                        {
                                            $response = '{"responseEvent":"error","responseType":"' . $postData['slotEvent'] . '","serverResponse":"Bad Reel Strip"}';
                                            exit( $response );
                                        }
                                        if( ($scattersCount >= 3 || $scattersCount >= 2 && $postData['slotEvent'] == 'freespin') && $winType != 'bonus' ) 
                                        {
                                        }
                                        else if( $totalWin <= $spinWinLimit && $winType == 'bonus' ) 
                                        {
                                            $cBank = $slotSettings->GetBank((isset($postData['slotEvent']) ? $postData['slotEvent'] : ''));
                                            if( $cBank < $spinWinLimit ) 
                                            {
                                                $spinWinLimit = $cBank;
                                            }
                                            else
                                            {
                                                break;
                                            }
                                        }
                                        else if( $totalWin > 0 && $totalWin <= $spinWinLimit && $winType == 'win' ) 
                                        {
                                            $cBank = $slotSettings->GetBank((isset($postData['slotEvent']) ? $postData['slotEvent'] : ''));
                                            if( $cBank < $spinWinLimit ) 
                                            {
                                                $spinWinLimit = $cBank;
                                            }
                                            else
                                            {
                                                break;
                                            }
                                        }
                                        else if( $totalWin == 0 && $winType == 'none' ) 
                                        {
                                            break;
                                        }
                                    }
                                }
                            }
                            if( $totalWin > 0 ) 
                            {
                                $slotSettings->SetBank((isset($postData['slotEvent']) ? $postData['slotEvent'] : ''), -1 * $totalWin);
                                $slotSettings->SetBalance($totalWin);
                            }
                            $reportWin = $totalWin;
                            if( $postData['slotEvent'] == 'freespin' ) 
                            {
                                $slotSettings->SetGameData('DragonChampionsPTMBonusWin', $slotSettings->GetGameData('DragonChampionsPTMBonusWin') + $totalWin);
                                $slotSettings->SetGameData('DragonChampionsPTMTotalWin', $totalWin);
                                $sID = 48820;
                                $rI = 7;
                                $sType = 'FREE';
                                $gType = 1;
                            }
                            else
                            {
                                $sID = 48820;
                                $rI = 0;
                                $slotSettings->SetGameData('DragonChampionsPTMTotalWin', $totalWin);
                                $sType = 'REGULAR';
                                $gType = 0;
                            }
                            if( $scattersCount >= 2 ) 
                            {
                                if( $slotSettings->GetGameData('DragonChampionsPTMFreeGames') > 0 ) 
                                {
                                    $slotSettings->SetGameData('DragonChampionsPTMFreeGames', $slotSettings->GetGameData('DragonChampionsPTMFreeGames') + $slotSettings->slotFreeCountAdd[$scattersCount]);
                                }
                                else if( $scattersCount >= 3 ) 
                                {
                                    $slotSettings->SetGameData('DragonChampionsPTMFreeStartWin', $totalWin);
                                    $slotSettings->SetGameData('DragonChampionsPTMBonusWin', 0);
                                    $slotSettings->SetGameData('DragonChampionsPTMFreeGames', $slotSettings->slotFreeCount[$scattersCount]);
                                }
                            }
                            $balanceInCents = sprintf('%01.2f', $slotSettings->GetBalance()) * 100;
                            $result_tmp[] = '3:::{"data":{"balance":' . $balanceInCents . ',"results":[' . implode(',', $reels['rp']) . '],"spinType":"' . $sType . '","gameType":' . $gType . ',"reelset":' . $rI . ',"symbols":[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],"specialSymbolId":10,"flags":[false],"windowId":"GDSYTV"},"ID":' . $sID . ',"umid":102}';
                            $result_tmp[] = '3:::{"data":{"typeBalance":0,"currency":"' . $slotSettings->slotCurrency . '","balanceInCents":' . $balanceInCents . ',"deltaBalanceInCents":1},"ID":40085}';
                            $jsSpin = '' . json_encode($reels) . '';
                            $jsJack = '' . json_encode($slotSettings->Jackpots) . '';
                            $winString = implode(',', $lineWins);
                            $response = '{"responseEvent":"spin","responseType":"' . $postData['slotEvent'] . '","serverResponse":{"linesArr":[4096],"slotLines":' . $lines . ',"slotBet":' . $betLine . ',"totalFreeGames":' . $slotSettings->GetGameData('DragonChampionsPTMFreeGames') . ',"currentFreeGames":' . $slotSettings->GetGameData('DragonChampionsPTMCurrentFreeGame') . ',"Balance":' . $slotSettings->GetBalance() . ',"afterBalance":' . $slotSettings->GetBalance() . ',"bonusWin":' . $slotSettings->GetGameData('DragonChampionsPTMBonusWin') . ',"freeStartWin":' . $slotSettings->GetGameData('DragonChampionsPTMFreeStartWin') . ',"totalWin":' . $totalWin . ',"winLines":[' . $winString . '],"bonusInfo":' . $scattersStr . ',"Jackpots":' . $jsJack . ',"reelsSymbols":' . $jsSpin . '}}';
                            $slotSettings->SaveLogReport($response, $betLine, $lines, $reportWin, $postData['slotEvent']);
                        }
                        switch( $umid ) 
                        {
                            case '31031':
                                $result_tmp[] = '3:::{"data":{"urlList":[{"urlType":"mobile_login","url":"https://login.loc/register","priority":1},{"urlType":"mobile_support","url":"https://ww2.loc/support","priority":1},{"urlType":"playerprofile","url":"","priority":1},{"urlType":"playerprofile","url":"","priority":10},{"urlType":"gambling_commission","url":"","priority":1},{"urlType":"cashier","url":"","priority":1},{"urlType":"cashier","url":"","priority":1}]},"ID":100}';
                                break;
                            case '10001':
                                $result_tmp[] = '3:::{"data":{"typeBalance":2,"balanceInCents":0},"ID":40083,"umid":3}';
                                $result_tmp[] = '3:::{"data":{"typeBalance":0,"currency":"' . $slotSettings->slotCurrency . '","balanceInCents":' . $balanceInCents . ',"deltaBalanceInCents":0},"ID":40083,"umid":4}';
                                $result_tmp[] = '3:::{"data":{"commandId":13218,"params":["0","null"]},"ID":50001,"umid":5}';
                                $result_tmp[] = '3:::{"token":{"secretKey":"","currency":"USD","balance":0,"loginTime":""},"ID":10002,"umid":7}';
                                break;
                            case '40294':
                                $result_tmp[] = '3:::{"nicknameInfo":{"nickname":""},"ID":10022,"umid":8}';
                                $result_tmp[] = '3:::{"data":{"commandId":10713,"params":["0","ba","bj","ct","gc","grel","hb","po","ro","sc","tr"]},"ID":50001,"umid":9}';
                                $result_tmp[] = '3:::{"data":{"commandId":11666,"params":["0","0","0"]},"ID":50001,"umid":11}';
                                $result_tmp[] = '3:::{"data":{"commandId":13981,"params":["0","1"]},"ID":50001,"umid":12}';
                                $result_tmp[] = '3:::{"data":{"commandId":14080,"params":["0","0"]},"ID":50001,"umid":14}';
                                $result_tmp[] = '3:::{"data":{"keyValueCount":5,"elementsPerKey":1,"params":["10","1","11","500","12","1","13","0","14","0"]},"ID":40716,"umid":15}';
                                $result_tmp[] = '3:::{"data":{"typeBalance":0,"currency":"' . $slotSettings->slotCurrency . '","balanceInCents":' . $balanceInCents . ',"deltaBalanceInCents":0},"ID":40083,"umid":16}';
                                $result_tmp[] = '3:::{"balanceInfo":{"clientType":"casino","totalBalance":' . $balanceInCents . ',"currency":"' . $slotSettings->slotCurrency . '","balanceChange":' . $balanceInCents . '},"ID":10006,"umid":17}';
                                $result_tmp[] = '3:::{"data":{},"ID":40292,"umid":18}';
                                break;
                            case '10010':
                                $result_tmp[] = '3:::{"data":{"urls":{"casino-cashier-myaccount":[],"regulation_pt_self_exclusion":[],"link_legal_aams":[],"regulation_pt_player_protection":[],"mobile_cashier":[],"mobile_bank":[],"mobile_bonus_terms":[],"mobile_help":[],"link_responsible":[],"cashier":[{"url":"","priority":1},{"url":"","priority":1}],"gambling_commission":[{"url":"","priority":1},{"url":"","priority":1}],"desktop_help":[],"chat_token":[],"mobile_login_error":[],"mobile_error":[],"mobile_login":[{"url":"","priority":1}],"playerprofile":[{"url":"","priority":1},{"url":"","priority":10}],"link_legal_half":[],"ngmdesktop_quick_deposit":[],"external_login_form":[],"mobile_main_promotions":[],"mobile_lobby":[],"mobile_promotion":[],{"url":"","priority":1},{"url":"","priority":10}],"mobile_withdraw":[],"mobile_funds_trans":[],"mobile_quick_deposit":[],"mobile_history":[],"mobile_deposit_limit":[],"minigames_help":[],"link_legal_18":[],"mobile_responsible":[],"mobile_share":[],"mobile_lobby_error":[],"mobile_mobile_comp_points":[],"mobile_support":[{"url":"","priority":1}],"mobile_chat":[],"mobile_logout":[],"mobile_deposit":[],"invite_friend":[]}},"ID":10011,"umid":19}';
                                $result_tmp[] = '3:::{"data":{"brokenGames":[],"windowId":"SuJLru"},"ID":40037,"umid":20}';
                                break;
                            case '40066':
                                $gameBets = $slotSettings->Bet;
                                for( $i = 0; $i < count($gameBets); $i++ ) 
                                {
                                    $gameBets[$i] = $gameBets[$i] * 100;
                                }
                                $result_tmp[] = '3:::{"data":{"funNoticeGames":0,"funNoticePayouts":0,"gameGroup":"drgch","minBet":0,"maxBet":0,"minPosBet":0,"maxPosBet":50000,"coinSizes":[' . implode(',', $gameBets) . ']},"ID":40025,"umid":21}';
                                break;
                            case '40036':
                                $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', 0);
                                $slotSettings->SetGameData($slotSettings->slotId . 'FreeGames', 0);
                                $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', 0);
                                $slotSettings->SetGameData($slotSettings->slotId . 'TotalWin', 0);
                                $slotSettings->SetGameData($slotSettings->slotId . 'FreeBalance', 0);
                                $lastEvent = $slotSettings->GetHistory();
                                $slotSettings->SetGameData($slotSettings->slotId . 'brokenGames', '');
                                if( $lastEvent != 'NULL' ) 
                                {
                                    $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', $lastEvent->serverResponse->bonusWin);
                                    $slotSettings->SetGameData($slotSettings->slotId . 'FreeStartWin', $lastEvent->serverResponse->freeStartWin);
                                    $slotSettings->SetGameData($slotSettings->slotId . 'Reels', $lastEvent->serverResponse->reelsSymbols->rp);
                                    $slotSettings->SetGameData($slotSettings->slotId . 'FreeGames', $lastEvent->serverResponse->totalFreeGames);
                                    $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', $lastEvent->serverResponse->currentFreeGames);
                                    $slotSettings->SetGameData($slotSettings->slotId . 'TotalWin', $lastEvent->serverResponse->totalWin);
                                    $slotSettings->SetGameData($slotSettings->slotId . 'FreeBalance', $lastEvent->serverResponse->Balance);
                                    $slotSettings->SetGameData($slotSettings->slotId . 'LinesArr', $lastEvent->serverResponse->linesArr);
                                    $slotSettings->SetGameData($slotSettings->slotId . 'Bet', $lastEvent->serverResponse->slotBet);
                                    if( $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') < $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') && $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') > 0 ) 
                                    {
                                        $slotSettings->SetGameData($slotSettings->slotId . 'brokenGames', 'drgch');
                                    }
                                }
                                $result_tmp[] = '3:::{"data":{"brokenGames":["' . $slotSettings->GetGameData($slotSettings->slotId . 'brokenGames') . '"],"windowId":"SuJLru"},"ID":40037,"umid":22}';
                                break;
                            case '40020':
                                $result_tmp[] = '3:::{"data":{"typeBalance":2,"balanceInCents":0},"ID":40085}';
                                $result_tmp[] = '3:::{"data":{"typeBalance":1,"balanceInCents":0},"ID":40085}';
                                $result_tmp[] = '3:::{"data":{"typeBalance":0,"currency":"' . $slotSettings->slotCurrency . '","balanceInCents":' . $balanceInCents . ',"deltaBalanceInCents":0},"ID":40085}';
                                $result_tmp[] = '3:::{"data":{"credit":' . $balanceInCents . ',"windowId":"SuJLru"},"ID":40026,"umid":28}';
                                break;
                            case '40030':
                                $result_tmp[] = '3:::{"data":{"typeBalance":2,"balanceInCents":0},"ID":40085}';
                                $result_tmp[] = '3:::{"data":{"typeBalance":1,"balanceInCents":0},"ID":40085}';
                                $result_tmp[] = '3:::{"data":{"typeBalance":0,"currency":"' . $slotSettings->slotCurrency . '","balanceInCents":' . $balanceInCents . ',"deltaBalanceInCents":0},"ID":40085}';
                                $result_tmp[] = '3:::{"data":{"credit":' . $balanceInCents . ',"windowId":"SuJLru"},"ID":40026,"umid":28}';
                                if( $slotSettings->GetGameData($slotSettings->slotId . 'brokenGames') != '' ) 
                                {
                                    $bonusOpt = '';
                                    $result_tmp[] = '3:::{"data":{"freeSpinsData":{"numFreeSpins":' . ($slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') - $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame')) . ',"coinsize":' . ($slotSettings->GetGameData($slotSettings->slotId . 'Bet') * 100) . ',"rows":[4096],"gamewin":' . ($slotSettings->GetGameData($slotSettings->slotId . 'FreeStartWin') * 100) . ',"freespinwin":' . ($slotSettings->GetGameData($slotSettings->slotId . 'BonusWin') * 100) . ',"freespinTriggerReels":[8,19,9,16,19,7],"mode":0,"startBonus":0},"gameType":0,"reelset":2,"symbols":[4,7,2,5,12,8,8,3,10,1,9,2,6,10,6,12,1,6,12,1,10,10,10,3],"specialSymbol":10,"fsTriggerReelset":2,"totalFreespinsPlayed":' . $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') . ',"reelinfo":[8,19,9,16,19,7],"windowId":"xmTTcE"},"ID":48823,"umid":28}';
                                }
                                break;
                            case '48300':
                                $result_tmp[] = '3:::{"balanceInfo":{"clientType":"casino","totalBalance":' . $balanceInCents . ',"currency":"' . $slotSettings->slotCurrency . '","balanceChange":0},"ID":10006,"umid":30}';
                                $result_tmp[] = '3:::{"data":{"waitingLogins":[],"waitingAlerts":[],"waitingDialogs":[],"waitingDialogMessages":[],"waitingToasterMessages":[]},"ID":48301,"umid":31}';
                                break;
                        }
                        $response = implode('------', $result_tmp);
                        $slotSettings->SaveGameData();
                        $slotSettings->SaveGameDataStatic();
                        echo $response;
                    }
                    catch( \Exception $e ) 
                    {
                        if( isset($slotSettings) ) 
                        {
                            $slotSettings->InternalErrorSilent($e);
                        }
                        else
                        {
                            $strLog = '';
                            $strLog .= "\n";
                            $strLog .= ('{"responseEvent":"error","responseType":"' . $e . '","serverResponse":"InternalError","request":' . json_encode($_REQUEST) . ',"requestRaw":' . file_get_contents('php://input') . '}');
                            $strLog .= "\n";
                            $strLog .= ' ############################################### ';
                            $strLog .= "\n";
                            $slg = '';
                            if( file_exists(storage_path('logs/') . 'GameInternal.log') ) 
                            {
                                $slg = file_get_contents(storage_path('logs/') . 'GameInternal.log');
                            }
                            file_put_contents(storage_path('logs/') . 'GameInternal.log', $slg . $strLog);
                        }
                    }
                }, 5);
            }
            get_($request, $game);
        }
    }

}
