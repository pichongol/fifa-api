<?php

namespace FUTApi;

//Custom Providers
use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Cookie\FileCookieJar;

class Core {

    use Config, Hashor;

    private $login_info = [];

    private $clientHeaders = [];

    private $cookies_files = null;

    private $client = null;

    private $time = 0;

    private $credits = 0;

    private $game_sku, $sku, $sku_b = null;

    private $clientVersion = 0;

    private $questionId, $questionAttempts = 0;

    private $pin = null;

    private $debug = false;

    private $__usermassinfo, $duplicates = [];

    private $access_token, $token_type = null;

    public function __construct($email, $password, $secret_answer, $platform, $code = null, $totp = null, $sms = false, $emulate = false, $debug = false, $cookies = false) {
        $this->credits = 0;
        $this->questionId = 0;
        $this->cookies_files = ($cookies == false ? dirname(__FILE__)."/cookies/".base64_encode($email) : $cookies);
        $this->clientHeaders = [];
        $this->__usermassinfo = null;
        $this->debug = $debug;
        $this->login_info = [
            'email' => $email,
            'password' => $password,
            'secret_answer' => $secret_answer,
            'platform' => $platform,
            'backup_code' => $code,
            'totp' => $totp,
            'sms' => $sms,
            'emulate' => $emulate
        ];
        $this->client = new Client([
            'cookies' => new FileCookieJar($this->cookies_files, true),
            'http_errors' => false,
            'headers' => $this->clientHeaders,
            'timeout' => 10
        ]);
        switch(strtolower($emulate)) {
            case 'and':
                throw new FutError("Emulate feature is currently disabled due to latest changes in login process", 0, null, [
                    "reason" => "disabled_feature"
                ]);
                break;
            case 'ios':
                throw new FutError("Emulate feature is currently disabled due to latest changes in login process", 0, null, [
                    "reason" => "disabled_feature"
                ]);
                break;
            default:
                $this->clientHeaders = $this->headers['web'];
                $this->sku = 'FUT18WEB';
                $this->clientVersion = 1;
                break;
        }
        switch(strtolower($platform)) {
            case 'pc':
                $this->game_sku = 'FFA18PCC';
                break;
            case 'xbox':
                $this->game_sku = 'FFA18XBO';
                break;
            case 'xbox360':
                $this->game_sku = 'FFA18XBX';
                break;
            case 'ps3':
                $this->game_sku = 'FFA18PS3';
                break;
            case 'ps4':
                $this->game_sku = 'FFA18PS4';
                break;
            default:
                throw new FutError("Wrong platform. (Valid ones are pc/xbox/xbox360/ps3/ps4)", 0, null, [
                    "reason" => "invalid_platform"
                ]);
                break;
        }
    }

    public function login() {
        return $this->__login__(
            $this->login_info['email'],
            $this->login_info['password'],
            $this->login_info['secret_answer'],
            $this->login_info['platform'],
            $this->login_info['backup_code'],
            $this->login_info['totp'],
            $this->login_info['sms'],
            $this->login_info['emulate']
        );
    }

    public function setSession($persona, $nucleus, $phishing, $session, $dob) {
        $this->clientHeaders['Easw-Session-Data-Nucleus-Id'] = $nucleus;
        $this->clientHeaders['X-UT-SID'] = $session;
        $this->clientHeaders['X-UT-PHISHING-TOKEN'] = $phishing;
        //init host
        $this->fut_host = $this->fut_host[strtolower($this->login_info['platform'])];
        //init pin
        $this->pin = new Pin($session, $nucleus, $persona, $dob, strtolower($this->login_info['platform']));
    }

    public function __login__($email, $passwd, $secret_answer, $platform, $code = null, $totp = null, $sms = null, $emulate = null) {
        $secret_answer_hash = $this->getHash($secret_answer);
        $this->sku_b = 'FFT18';
        $params = [
            'prompt' => 'login',
            'accessToken' => '',
            'client_id' => $this->client_id,
            'response_type' => 'token',
            'display' => 'web2/login',
            'locale' => 'en_US',
            'redirect_uri' => 'https://www.easports.com/fifa/ultimate-team/web-app/auth.html',
            'scope' => 'basic.identity offline signin'
        ];
        $this->clientHeaders['Referer'] = 'https://www.easports.com/fifa/ultimate-team/web-app/';
        $this->client->get("https://accounts.ea.com/connect/auth", [
            'query' => $params,
            'headers' => $this->clientHeaders,
            'on_stats' => function (TransferStats $stats) use (&$url) {
                $url = (string)$stats->getEffectiveUri();
            }
        ]);
        if($url !== 'https://www.easports.com/fifa/ultimate-team/web-app/auth.html') {
            $this->clientHeaders['Referer'] = $url;
            $data = [
                'email' => $email,
                'password' => $passwd,
                'country' => 'US',
                'phoneNumber' => '',
                'passwordForPhone' => '',
                'gCaptchaResponse' => '',
                'isPhoneNumberLogin' => 'false',
                'isIncompletePhone' => '',
                '_rememberMe' => 'on',
                'rememberMe' => 'on',
                '_eventId' => 'submit'
            ];
            $response = (string)$this->client->post($url, [
                'form_params' => $data,
                'headers' => $this->clientHeaders,
                'on_stats' => function (TransferStats $stats) use (&$url) {
                    $url = $stats->getEffectiveUri();
                }
            ])->getBody();

            if (strpos($response,"'successfulLogin': false") !== false) {
                throw new FutError("Your email or password is incorrect.", 0, null, [
                    "reason" => "user_or_pass"
                ]);
            }
            if (strpos($response,"var redirectUri") !== false) {
                $response = $this->client->get($url."&_eventId=end", [
                    'on_stats' => function (TransferStats $stats) use (&$url) {
                        $url = $stats->getEffectiveUri();
                    },
                    'headers' => $this->clientHeaders
                ])->getBody();
            }
            if (strpos($response,"Login Verification") !== false) {
                if($totp) {
                    $params = [
                        'codeType' => 'APP',
                        '_eventId' => 'submit'
                    ];
                }
                if($sms) {
                    $params = [
                        'codeType' => 'SMS',
                        '_eventId' => 'submit'
                    ];
                } else {
                    $params = [
                        'codeType' => 'EMAIL',
                        '_eventId' => 'submit'
                    ];
                }
                $response = $this->client->request('POST', $url, [
                    'form_params' => $params,
                    'headers' => $this->clientHeaders,
                    'on_stats' => function (TransferStats $stats) use (&$url) {
                        $url = $stats->getEffectiveUri();
                    }
                ])->getBody();
            }
            if (strpos($response,"Enter your security code") !== false) {
                if(is_null($code)) {
                    throw new FutError("You must provide a backup code.", 0, null, [
                        "reason" => "backup_code"
                    ]);
                }
                $this->clientHeaders['Referer'] = $url;
                $response = (string)$this->client->request('POST', str_replace("s3","s4",$url), [
                    'form_params' => [
                        'oneTimeCode' => $code,
                        '_trustThisDevice' => 'on',
                        'trustThisDevice' => 'on',
                        '_eventId' => 'submit'
                    ],
                    'headers' => $this->clientHeaders,
                    'on_stats' => function (TransferStats $stats) use (&$url) {
                        $url = $stats->getEffectiveUri();
                    }
                ])->getBody();
                if (strpos($response,'Incorrect code entered') !== false || strpos($response,'Please enter a valid security code') !== false) {
                    throw new FutError("You provided an incorrect backup code.", 0, null, [
                        "reason" => "backup_code"
                    ]);
                }
                if (strpos($response,'Set Up an App Authenticator') !== false) {
                    $response = $this->client->request('POST', str_replace("s3","s4",$url), [
                        'form_params' => [
                            'appDevice' => 'IPHONE',
                            '_eventId' => 'cancel'
                        ],
                        'headers' => $this->clientHeaders,
                        'on_stats' => function (TransferStats $stats) use (&$url) {
                            $url = $stats->getEffectiveUri();
                        }
                    ])->getBody();
                }
            }
        }
        preg_match('/https:\/\/www.easports.com\/fifa\/ultimate-team\/web-app\/auth.html#access_token=(.+?)&token_type=(.+?)&expires_in=[0-9]+/', $url, $matches);
        $this->access_token = $matches[1];
        $this->token_type = $matches[2];
        $this->client->get("https://www.easports.com/fifa/ultimate-team/web-app/");
        $this->clientHeaders['Referer'] = 'https://www.easports.com/fifa/ultimate-team/web-app/';
        $this->clientHeaders['Accept'] = 'application/json';
        $this->clientHeaders['Authorization'] = $this->token_type.' '.$this->access_token;
        $response = json_decode($this->client->get("https://gateway.ea.com/proxy/identity/pids/me", [
            'headers' => $this->clientHeaders
        ])->getBody(), true);
        $nucleus_id = $response['pid']['externalRefValue'];
        $dob = $response['pid']['dob'];
        unset($this->clientHeaders['Authorization']);
        $this->clientHeaders['Easw-Session-Data-Nucleus-Id'] = $nucleus_id;
        $this->time = (int)(time() * 1000);

        //shards
        try {
            $this->client->get("https://".$this->auth_url."/ut/shards/v2", [
                'query' => [
                    '_' => $this->time
                ],
                'headers' => $this->clientHeaders,
                'timeout' => 5
            ])->getBody();
        } catch(\GuzzleHttp\Exception\ConnectException $e) {
            $response = (string)$e->getResponse();
            if($response == "") {
                throw new FutError("EA servers appear to be offline.", 0, null, [
                    "reason" => "servers_down"
                ]);
            }
        } catch(\GuzzleHttp\Exception\RequestException $e) {
            $response = (string)$e->getResponse();
            if($response == "") {
                throw new FutError("EA servers appear to be offline.", 0, null, [
                    "reason" => "servers_down"
                ]);
            }
        }

        $this->time += 1;
        $this->fut_host = $this->fut_host[$platform];

        //personas
        try {
            $response = json_decode($this->client->get("https://" . $this->fut_host . "/ut/game/fifa18/user/accountinfo", [
                'query' => [
                    'filterConsoleLogin' => 'true',
                    'sku' => $this->sku,
                    'returningUserGameYear' => '2017',
                    '_' => $this->time
                ],
                'timeout' => 5,
                'headers' => $this->clientHeaders
            ])->getBody(), true);
        } catch(\GuzzleHttp\Exception\ConnectException $e) {
            if($response == "") {
                throw new FutError("EA servers appear to be offline.", 0, null, [
                    "reason" => "servers_down"
                ]);
            }
        }
        foreach($response['userAccountInfo']['personas'] as $persona) {
            foreach($persona['userClubList'] as $club) {
                if(array_key_exists('skuAccessList', $club)) {
                    if(isset($club['skuAccessList'][$this->game_sku])) {
                        $this->persona_id = $persona['personaId'];
                    }
                }
            }
        }
        if(!isset($this->persona_id)) {
            throw new FutError("Error during login process (no persona found).", 0, null, [
                "reason" => "no_club"
            ]);
        }

        //authorization
        unset($this->clientHeaders['Easw-Session-Data-Nucleus-Id']);
        $this->clientHeaders['Origin'] = 'http://www.easports.com';
        $response = json_decode($this->client->get("https://accounts.ea.com/connect/auth", [
            'query' => [
                'client_id' => 'FOS-SERVER',
                'redirect_uri' => 'nucleus:rest',
                'response_type' => 'code',
                'access_token' => $this->access_token
            ],
            'headers' => $this->clientHeaders
        ])->getBody(), true);
        $auth_code = $response['code'];

        $this->clientHeaders['Content-Type'] = "application/json";
        $response = $this->client->request('POST', "https://".$this->fut_host."/ut/auth", [
            'body' => json_encode(array(
                'isReadOnly' => false,
                'sku' => $this->sku,
                'clientVersion' => $this->clientVersion,
                'nucleusPersonaId' => $this->persona_id,
                'gameSku' => $this->game_sku,
                'locale' => 'en-US',
                'method' => 'authcode',
                'priorityLevel' => 4,
                'identification' => [
                    'authCode' => $auth_code,
                    'redirectUrl' => 'nucleus:rest'
                ]
            )),
            'query' => [
                'sku_b' => $this->sku_b,
                '' => (int)time() * 1000
            ],
            'headers' => $this->clientHeaders
        ]);
        if($response->getStatusCode() == 401) {
            throw new FutError("Account is logged in elsewhere.", 0, null, [
                "reason" => "multiple_sessions"
            ]);
        }
        if($response->getStatusCode() == 500) {
            throw new FutError("Servers are probably temporary down..", 0, null, [
                "reason" => "servers_down"
            ]);
        }
        $response = json_decode($response->getBody(), true);
        if(isset($response['reason'])) {
            switch($response['reason']) {
                case "multiple session":
                case "max sessions":
                    throw new FutError("Account is logged in elsewhere.", 0, null, [
                        "reason" => "multiple_sessions"
                    ]);
                    break;
                case "doLogin: doLogin failed":
                    throw new FutError("Account failed to auth.", 0, null, [
                        "reason" => "auth_failed"
                    ]);
                    break;
                default:
                    throw new FutError($response['reason'], 0, null, [
                        "reason" => $response['reason']
                    ]);
                    break;
            }
        }
        $this->clientHeaders['X-UT-SID'] = $sid = $response['sid'];

        //init pin
        $this->pin = new Pin($sid, $nucleus_id, $this->persona_id, $dob, $platform);
        $events = $this->pin->event('login', 'success');
        $this->pin->send($events);

        //validate (secret question)
        $this->clientHeaders['Easw-Session-Data-Nucleus-Id'] = $nucleus_id;
        $response = json_decode($this->client->request('GET', "https://".$this->fut_host."/ut/game/fifa18/phishing/question", [
            'query' => [
                '' => $this->time
            ],
            'headers' => $this->clientHeaders
        ])->getBody(), true);
        $this->time += 1;
        if(isset($response['string'])) {
            switch(trim($response['string'])) {
                case "Fun Captcha Triggered":
                    throw new FutError('Your account has received a captcha', 0, null, [
                        "reason" => 'captcha'
                    ]);
                    break;
                case "Account Locked":
                    throw new FutError('Your account is locked, webapp answer entered incorrectly.', 0, null, [
                        "reason" => 'account_locked'
                    ]);
                    break;
                case "No Content":
                    throw new FutError('Question is not setup.', 0, null, [
                        "reason" => 'no_question',
                    ]);
                    break;
            }
        }

        //set questionID & attemptsRemaining
        $this->questionAttempts = $response['attempts'];
        $this->questionId = $response['question'];

        //submit (secret question)
        $response = json_decode($this->client->request('POST', "https://".$this->fut_host."/ut/game/fifa18/phishing/validate", [
            'query' => [
                'answer' => $secret_answer_hash
            ],
            'headers' => $this->clientHeaders
        ])->getBody(), true);
        if($response['string'] !== 'OK') {
            throw new FutError('WebApp Security Answer is wrong..', 0, null, [
                "reason" => 'webapp_answer',
                "question_id" => $this->questionId,
                "question_text" => $this->questions[$this->questionId],
                "question_attempts" => ($this->questionAttempts - 1)
            ]);
        }
        $this->clientHeaders['X-UT-PHISHING-TOKEN'] = $response['token'];

        //userinfo
        $this->__usermassinfo = json_decode($this->client->request('GET', "https://".$this->fut_host."/ut/game/fifa18/usermassinfo", [
            'query' => [
                '' => $this->time
            ],
            'headers' => $this->clientHeaders
        ])->getBody(), true);
        $this->time += 1;

        $piles = $this->pileSize();
        $this->tradepile_size = $piles['tradepile'];
        $this->watchlist_size = $piles['watchlist'];

        // pinEvents - Home Screen
        $events = $this->pin->event('page_view', 'Hub - Home');
        $this->pin->send($events);

        // pinEvents - boot_end
        $events = [
            $this->pin->event('connection'),
            $this->pin->event('boot_end', false, false, false, 'normal')
        ];
        $this->pin->send($events);

        // credits
        $this->keepalive();

        // return info
        return [
            'email' => $email,
            'mass_info' => $this->__usermassinfo,
            'credits' => $this->credits,
            'auth' => [
                'access_token' => $this->access_token,
                'token_type' => $this->token_type,
                'nucleus_id' => $nucleus_id,
                'persona_id' => $this->persona_id,
                'phishing_token' => $response['token'],
                'session_id' => $sid,
                'dob' => $dob
            ]
        ];
    }

    public function logout() {
        $this->request('GET', 'https://www.easports.com/signout', [], ['ct' => $this->time]);
        $this->request('GET', 'https://accounts.ea.com/connect/clearsid', [], ['ct' => $this->time]);
        $this->request('GET', 'https://beta.www.origin.com/views/logout.html', [], ['ct' => $this->time]);
        $this->request('GET', 'https://help.ea.com/community/logout/', [], ['ct' => $this->time]);
    }

    public function searchDefinition($asset_id, $start = 0, $count = 46) {
        $params = [
            'defId' => $this->baseId($asset_id),
            'start' => $start,
            'type' => 'player',
            'count' => $count
        ];
        $response = $this->request('GET', 'defid', [], $params);
        return $response;
    }

    public function searchAuctions($ctype = 'player', $level = null, $category = null, $assetId = null, $defId = null, $min_price = null, $max_price = null, $min_buy = null, $max_buy = null, $league = null, $club = null, $position = null, $zone = null, $nationality = null, $rare = null, $playStyle = null, $start = 0, $page_size = 36, $fast = false) {
        if($start == 0) {
            $events = $this->pin->event('page_view', 'Transfer Market Search');
            $this->pin->send($events);
        }
        $params = [
            'start' => $start,
            'num' => $page_size,
            'type' => $ctype
        ];
        if(!is_null($level)) { $params['lev'] = $level; }
        if(!is_null($category)) { $params['cat'] = $category; }
        if(!is_null($assetId)) { $params['maskedDefId'] = $assetId; }
        if(!is_null($defId)) { $params['definitionId'] = $defId; }
        if(!is_null($min_price)) { $params['micr'] = $min_price; }
        if(!is_null($max_price)) { $params['macr'] = $max_price; }
        if(!is_null($min_buy)) { $params['minb'] = $min_buy; }
        if(!is_null($max_buy)) { $params['maxb'] = $max_buy; }
        if(!is_null($league)) { $params['leag'] = $league; }
        if(!is_null($club)) { $params['team'] = $club; }
        if(!is_null($position)) { $params['pos'] = $position; }
        if(!is_null($zone)) { $params['zone'] = $zone; }
        if(!is_null($nationality)) { $params['nat'] = $nationality; }
        if(!is_null($rare)) { $params['rare'] = 'SP'; }
        if(!is_null($playStyle)) { $params['playStyle'] = $playStyle; }
        $response = $this->request('GET', 'transfermarket', [], $params);
        if($start == 0) {
            $event = $this->pin->event('page_view', 'Transfer Market Results - List View');
            $this->pin->send($event);
        }
        return $response;
    }

    public function bid($trade_id, $bid, $fast = false) {
        if(!$fast) {
            $response = $this->tradeStatus($trade_id);
            if($response['currentBid'] >= $bid || $this->credits < $bid) {
                return false;
            }
        }
        try {
            $response = $this->request('POST', 'trade/'.$trade_id.'/bid', [
                'bid' => $bid
            ], [
                'sku_b' => $this->sku_b
            ]);
        } catch(FutError $e) {
            return false;
        }
        if($response['auctionInfo'][0]['bidState'] == 'highest' || ($response['auctionInfo'][0]['tradeState'] == 'closed' && $response['auctionInfo'][0]['bidState'] == 'buyNow')) {
            return true;
        }
        return false;
    }

    public function club($sort = 'desc', $ctype = 'player', $defId = '', $start = '0', $count = 91, $level = false) {
        $params = [
            'sort' => $sort,
            'type' => $ctype,
            'defId' => $defId,
            'start' => $start,
            'count' => $count
        ];
        if($level) {
            $params['level'] = $level;
        }
        $response = $this->request('GET', 'club', [], $params);
        if($start == 0) {
            switch($ctype) {
                case "player":
                    $events = $this->pin->event('page_view', 'Club - Players - List View');
                    break;
                case "item":
                    $events = $this->pin->event('page_view', 'Club - Club Items - List View');
                    break;
                default:
                    $events = $this->pin->event('page_view', 'Club - Club Items - List View');
                    break;
            }
            $this->pin->send($events);
        }
        return $response;
    }

    public function clubStaff() {
        $response = $this->request('GET', 'club/stats/staff');
        return $response;
    }

    public function squad($squad_id = 0, $persona_id = null) {
        $events = $this->pin->event('page_view', 'Hub - Squads');
        $this->pin->send($events);
        $response = $this->request('GET', 'squad/'.$squad_id.'/user/'.(is_null($persona_id) ? $this->persona_id : $persona_id));
        $events = $this->pin->event('page_view', 'Squads - Squad Overview');
        $this->pin->send($events);
        return $response;
    }

    public function tradeStatus($trade_id) {
        $response = $this->request('GET', 'trade/status', [], [
            'tradeIds' => $trade_id
        ]);
        return $response;
    }

    public function tradepile() {
        $response = $this->request('GET', 'tradepile');
        $events = $this->pin->event('page_view', 'Transfer List - List View');
        $this->pin->send($events);
        return $response;
    }

    public function watchlist() {
        $response = $this->request('GET', 'watchlist');
        $events = $this->pin->event('page_view', 'Transfer Targets - List View');
        $this->pin->send($events);
        return $response;
    }

    public function unassigned() {
        $response = $this->request('GET', 'purchased/items');
        $events = $this->pin->event('page_view', 'Unassigned Items - List View');
        $this->pin->send($events);
        return $response;
    }

    public function sell($id, $bid, $buy_now, $duration = 3600, $fast = false) {
        $response = $this->request('JSON', 'auctionhouse', [
            'itemData' => [
                'id' => $id
            ],
            'buyNowPrice' => $buy_now,
            'startingBid' => $bid,
            'duration' => $duration
        ], [
            'sku_b' => $this->sku_b
        ]);
        if(!$fast) {
            $this->tradeStatus($response['id']);
        }
        return $response;
    }

    public function quickSell($item_id) {
        $response = $this->request('DELETE', 'item', [], [
            'itemIds' => $item_id
        ]);
        return $response;
    }

    public function removeSold($item_id) {
        $response = $this->request('DELETE', 'trade/'.$item_id);
        return $response;
    }

    public function watchlistDelete($trade_id) {
        $response = $this->request('DELETE', 'watchlist', [], [
            'tradeId' => $trade_id
        ]);
        return $response;
    }

    public function sendToTradepile($item_id, $safe = true) {
        if($safe) {
            if(count($this->tradepile()) >= $this->tradepile_size) {
                return false;
            }
        }
        return $this->__sendToPile__('trade', $item_id);
    }

    public function sendToClub($item_id) {
        return $this->__sendToPile__('club', $item_id);
    }

    public function sendToWatchList($trade_id) {
        $response = $this->request('PUT', 'watchlist', [
            'auctionInfo' => [
                [
                    'id' => $trade_id
                ]
            ]
        ]);
        return $response;
    }

    public function priceRange($definitionId = null) {
        $response = $this->request('POST', 'marketdata/pricelimits?defId='.(is_array($definitionId) ? implode(",", $definitionId) : $definitionId));
        return $response;
    }

    public function relist() {
        $response = $this->request('PUT', 'auctionhouse/relist');
        return $response;
    }

    public function applyConsumable($item_id, $resource_id) {
        $response = $this->request('POST', 'item/resource/'.$resource_id, [
            'apply' => [
                [
                    'id' => $item_id
                ]
            ]
        ]);
        return $response;
    }

    public function keepalive() {
        $response = $this->request('GET', 'user/credits');
        return $response['credits'];
    }

    public function pileSize() {
        $data = $this->__usermassinfo['pileSizeClientData']['entries'];
        return [
            'tradepile' => $data[0]['value'],
            'watchlist' => $data[2]['value']
        ];
    }

    public function buyPack($pack_id, $currency = 'COINS') {
        $this->pin->send($this->pin->event('page_view', 'Hub - Store'));
        $response = $this->request('POST', 'purchased/items', [
            'packId' => $pack_id,
            'currency' => $currency
        ]);
        return $response;
    }

    public function openPack($pack_id) {
        $response = $this->request('POST', 'purchased/items', [
            'packId' => $pack_id,
            'currency' => 0,
            'usePreOrder' => true
        ]);
        return $response;
    }

    public function sbsSets() {
        $response = $this->request('GET', 'sbs/sets');
        $this->pin->send($this->pin->event('page_view', 'Hub - SBC'));
        return $response;
    }

    public function clubConsumables() {
        $response = $this->request('GET', 'club/consumables/development');
        $this->pin->send($this->pin->event('page_view', 'Hub - Club'));
        $this->pin->send($this->pin->event('page_view', 'Club - Consumables'));
        $this->pin->send($this->pin->event('page_view', 'Club - Consumables - List View'));
        return $response;
    }

    public function objectives($scope = 'all') {
        $response = $this->request('GET', 'user/dynamicobjectives', [], ['scope' => $scope]);
        return $response;
    }

    public function __sendToPile__($pile, $item_id = null) {
        $response = $this->request('PUT', 'item', [
            'itemData' => [
                [
                    'pile' => $pile,
                    'id' => $item_id
                ]
            ]
        ]);
        return $response;
    }

    private function baseId($assetId) {
        $version = 0;
        $assetId = $assetId + 0xC4000000;
        while($assetId > 0x01000000){
            $version++;
            if ($version == 1){
                //the constant applied to all items
                $assetId -= 1342177280;
            }elseif ($version == 2){
                //the value added to the first updated version
                $assetId -= 50331648;
            }else{
                //the value added on all subsequent versions
                $assetId -= 16777216;
            }
        }
        return $assetId;
    }

    public function setHeaders(array $array) {
    	$this->clientHeaders = array_merge($this->clientHeaders, $array);
    }

    public function request($method, $url, $data = [], $params = [], $delay = false) {
        $url = 'https://'.$this->fut_host.'/ut/game/fifa18/'.$url;
        if($method == 'GET') {
            $params['_'] = $this->time;
        }
        if($delay) {
            sleep(1);
        }
        switch(strtoupper($method)) {
            case "GET":
                $response = $this->client->request('GET', $url, [
                    'query' => $params,
                    'body' => json_encode($data),
                    'headers' => $this->clientHeaders,
                    'debug' => $this->debug
                ]);
                break;
            case "POST":
                $this->clientHeaders['X-HTTP-Method-Override'] = 'GET';
                $response = $response = $this->client->request('POST', $url, [
                    'query' => $params,
                    'body' => json_encode($data),
                    'headers' => $this->clientHeaders,
                    'debug' => $this->debug
                ]);
                break;
            case "JSON":
                $response = $response = $this->client->request('POST', $url, [
                    'query' => $params,
                    'body' => json_encode($data),
                    'headers' => $this->clientHeaders,
                    'debug' => $this->debug
                ]);
                break;
            default:
                $this->clientHeaders['X-HTTP-Method-Override'] = $method;
                $response = $response = $this->client->request('POST', $url, [
                    'query' => $params,
                    'body' => json_encode($data),
                    'headers' => $this->clientHeaders,
                    'debug' => $this->debug
                ]);
                break;
        }
        if($response->getStatusCode() !== 200) {
            switch($response->getStatusCode()) {
                case 401:
                    throw new FutError('Account session has expired.', 0, null, [
                        "reason" => 'expired_session'
                    ]);
                    break;
                case 426:
                case 429:
                    throw new FutError('Too many requests.', 0, null, [
                        "reason" => 'rate_limit_exceeded'
                    ]);
                    break;
                case 458:
                    $error = $this->pin->event('error');
                    $this->pin->send($error);
                    $this->logout();
                    throw new FutError('Your account has received a captcha.', 0, null, [
                        "reason" => 'captcha'
                    ]);
                    break;
                case 460:
                case 461:
                    throw new FutError('Permission denied.', 0, null, [
                        "reason" => 'permission_denied'
                    ]);
                    break;
                case 494:
                    throw new FutError('Transfer market is probably disabled on this account.', 0, null, [
                        "reason" => 'market_disabled'
                    ]);
                    break;
                case 512:
                case 521:
                    throw new FutError('Temporary ban or just too many requests.', 0, null, [
                        "reason" => 'temporary_ban'
                    ]);
                    break;
            }
        }
        $response = (string)$response->getBody()->getContents();
        if($response == '') {
            $response = [];
        } else {
            $response = json_decode($response, true);
            if(array_key_exists('credits', $response)) {
                $this->credits = $response['credits'];
            }
            if(array_key_exists('duplicateItemIdList', $response)) {
                foreach($response['duplicateItemIdList'] as $id) {
                    $this->duplicates[] = $id['itemId'];
                }
            }
        }
        return $response;
    }

}

?>
