# Game port check - 2026-11-03

Source: https://buananet.com/static-routing-games.html. All 106 games were selected individually on ROS6 and ROS7 through the authenticated rendered UI, plus combination cases (226 observations). Fresh ordered parameters remain unchanged from the earlier snapshot. Private staging generation compared 220 available ordered configurations; six selections involving the two malformed games stay blocked. These are configuration-parameter checks, not current live-game traffic verification.

TCP/UDP values below are observed facts. The malformed UDP literals are retained in the source snapshot and labelled FAILED; no replacement port is inferred. Staging browser testing separately covers real visible output.

| Game | TCP | UDP | Draft result (ROS6 / ROS7) |
|---|---|---|---|
| Arena Of Valor (AOV) - Mobile | 10001-10094 | 10101-10201,10080-10110,17000-18000 | MATCH / MATCH |
| Among Us - Mobile | 27015-27030,27036-27037 | 4380,27000-27031,27036 | MATCH / MATCH |
| Booya Capsa Susun - Mobile | 7090-7100 | - | MATCH / MATCH |
| Booya Domino Qiuqiu - Mobile | 7020-7030 | - | MATCH / MATCH |
| Clash Of Clans (C0C) - Mobile | 9330-9340 | 9330-9340 | MATCH / MATCH |
| Clash Royale - Mobile | 9330-9340 | 9330-9340 | MATCH / MATCH |
| Call of Duty (COD) - Mobile | 3013,10000-10019,18082,50000,65010,65050 | 7085-7995,8700,9030,10010-10019,17000-20100 | MATCH / MATCH |
| DOTA2 - Mobile | 9100-9200,8230-8250,8110-8120,27000-28998 | 27000-28998,39000 | MATCH / MATCH |
| Domino Qq - Mobile | 9122,11000-11150 | - | MATCH / MATCH |
| DragonNest (DN) - Mobile | 10514 | - | MATCH / MATCH |
| Dream League Soccer - Mobile | - | 60970-60980 | MATCH / MATCH |
| Fifa Online - Mobile | 7770-7790 | 16300-16350 | MATCH / MATCH |
| FreeFire (FF) - Mobile | 6006,6674,7006,7889,8001-8012,9006,9137,10000-10012,11000-11019; 12006,12008,13006,15006,20561,39003,39006,39698,39779,39800 | 6006,6008,7008,8008,8130,8443,9008,9120,10000-10015,10100,11000-11019,12008,13008 | MATCH / MATCH |
| Genshin Impact - Mobile | 42472 | 42472,22101-22102 | MATCH / MATCH |
| Higgs Domino Island - Mobile | 50000-50500 | 40000-40010 | MATCH / MATCH |
| League of Legends (LOL) - Mobile | 2080-2099 | 5100 | MATCH / MATCH |
| Last Empire War Z - Mobile | 9930-9940 | - | MATCH / MATCH |
| Line Lets Get Rich - Mobile | 10500-10515 | - | MATCH / MATCH |
| Minecraft - Mobile | 25565,19132-19133 | 25565,19132-19133 | MATCH / MATCH |
| Mobile Legends (ML) - Mobile | 5000-5221,5224-5227,5229-5241,5243-5508,5551-5559,5601-5700,9000-9010,9443; 5520-5529,10003,30000-30300,8443 | 4001-4009,5000-5221,5224-5241,5243-5509,5551-5559,5601-5700,8130,8443,9120; 2702,3702,5517,5520-5529,8001,9000-9010,9992,10003,30000-30300 | MATCH / MATCH |
| Mostly - Mobile | 9933 | - | MATCH / MATCH |
| Naruto Little Ninja - Mobile | 6170-6180 | - | MATCH / MATCH |
| Netmarble - Mobile | 12000-12010 | - | MATCH / MATCH |
| PointBlank (PB) - Mobile | 44590-44610 | 40000-40010 | MATCH / MATCH |
| PUBG - Mobile | 7889,10012,13004,14000,17000,17500,18081,20000-20002,20371 | 8011,9030,10491,10612,12235,13004,13748,17000,17500,20000-20002; 7086-7995,10039,10096,11455,12070-12460,13894,13972,41182-41192 | MATCH / MATCH |
| Roblox - Mobile | 3074 | 88,500,3074,3544,4500,49152-65535 | MATCH / MATCH |
| Rpg Toram Online - Mobile | 30100-30110 | - | MATCH / MATCH |
| RulesOfSurvival (ROS) - Mobile | - | 24000-24050 | MATCH / MATCH |
| Shinobi Heroes - Mobile | 10005-10020 | - | MATCH / MATCH |
| 8 Ball Pool - WebGames | 4000 | - | MATCH / MATCH |
| Castle Ville - WebGames | 8890 | - | MATCH / MATCH |
| Headshot - WebGames | 1800-1810 | 1845-1860 | MATCH / MATCH |
| Empire Allies - WebGames | 8890 | - | MATCH / MATCH |
| League of Angels 2 - WebGames | 51700-51715 | - | MATCH / MATCH |
| Megarealm RiseOfChaos-WebGames | 26590-26600 | - | MATCH / MATCH |
| Perjuangan Semut - WebGames | 7200-7210,7450-7460 | - | MATCH / MATCH |
| Roblox - WebGames | - | 49152-65535 | MATCH / MATCH |
| Sword of Angels - WebGames | 15490-15510 | - | MATCH / MATCH |
| Texas Hold EmPoker - WebGames | 9339 | - | MATCH / MATCH |
| Warflare - WebGames | 64990-65010 | - | MATCH / MATCH |
| WildOnes - WebGames | 8000 | - | MATCH / MATCH |
| Atlantica - PC | 4300 | - | MATCH / MATCH |
| Aura Kingdom - PC | 5540-5580 | - | MATCH / MATCH |
| AyoDance - PC | 18900-18910 | - | MATCH / MATCH |
| APEX Legends - PC | 9960-9969,1024-1124,3216,18000,18120,18060,27900,28910,29900 | 1024-1124,18000,29900,37000-40000 | MATCH / MATCH |
| BlackDesert (BDO) - PC | 8888,9991-9993 | 8888,9991-9993 | MATCH / MATCH |
| BlackDesert (BDO) - Playstaion | 1935,3478-3480 | 3074,3478-3479 | MATCH / MATCH |
| BlackDesert Steam (BDO) - PC | 8888,9991-9993,27015-27030,27036-27037 | 4380,8888,9991-9993,27000-27031,27036 | MATCH / MATCH |
| Black Desert (BDO) - Xbox | 3074 | 88,500,3074,3544,4500 | MATCH / MATCH |
| Blackretribution - PC | - | 7020-7050,8200-8220,9000-9020 | MATCH / MATCH |
| Blizzard BattleNet - PC | 1119 | 1119 | MATCH / MATCH |
| Bounty Hound - PC | 9810-9860 | - | MATCH / MATCH |
| Cabal Extreme - PC | 60170-60180,63000-64000,38101,38110-38600 | - | MATCH / MATCH |
| Cabal Indonesia - PC | 63000-64000,38101,38110-38130 | - | MATCH / MATCH |
| CallofDuty(COD)BO - PC | 3074,27014-27050 | 3478,4379-4380,27000-27031,27036 | MATCH / MATCH |
| CallofDuty(COD)BO - PlaySt.. | 80,443,1935,3478-3480 | 3478-3479 | MATCH / MATCH |
| CallofDuty(COD)BO - XBox | 53,80,3074 | 88,500,3074,3075,3544,4500 | MATCH / MATCH |
| CallofDuty(COD)MW - PC | 3074,27014-27050 | 3074,3478,4379-4380,27000-27031,27036 | MATCH / MATCH |
| CallofDuty COD)MW - Playst.. | 1935,3478-3480 | 3074,3478-3479 | MATCH / MATCH |
| CallofDuty (COD)MW - XBox | 3074 | 88,500,3074,3075,3544,4500 | MATCH / MATCH |
| Clash of God - PC | 9430-9450,5220-5230 | - | MATCH / MATCH |
| CSGO Playstation | 3478-3480,5223,8080 | 3074,3478-3479,3658 | MATCH / MATCH |
| CSGO Steam - PC | 27015-27030,27036-27037 | 4380,27000-27031,27036 | MATCH / MATCH |
| CSGO Xbox - PC | 3074 | 88,3074 | MATCH / MATCH |
| Cross Fire indonesia - PC | 10009,13008,16666,28012 | 12020-12080,13000-13080 | MATCH / MATCH |
| Diablo III - PC | 1119 | 1119,6120 | MATCH / MATCH |
| Diablo II - PC | 6112,4000 | - | MATCH / MATCH |
| Diablo I - PC | 6112-6119 | 6112-6119 | MATCH / MATCH |
| DOTA 2 Steam - PC | 9100-9200,8230-8250,8110-8120 | 28010-28200,27010-27200,39000 | MATCH / MATCH |
| DragonNest (DN) - PC | 14300-15512 | 15000-15500 | MATCH / MATCH |
| Dragona - PC | 10000-10030 | - | MATCH / MATCH |
| Fifa Online 3 (FO3) - PC | 7770-7790 | 16300-16350 | MATCH / MATCH |
| Fortnite Epic - PC | - | 9000-9100 | MATCH / MATCH |
| League of Legend (LOL) - PC | 2080-2099 | FAILED (unbalanced quote) | BLOCKED / BLOCKED |
| Grand Chase - PC | 9300,9400,9700 | - | MATCH / MATCH |
| Hearthstone - PC | 1119,3724 | FAILED (unbalanced quote) | BLOCKED / BLOCKED |
| Heroes of the Storm - PC | 1119-1120,3724,6113 | 1119-1120,3478-3479,3724,5060,5062,6113,6250,12000-64000 | MATCH / MATCH |
| Heroes of Newerth (HON) - PC | - | 9100-9200,11200-11500 | MATCH / MATCH |
| Heroes of Atarsia (HOA) - PC | 7777,9400 | - | MATCH / MATCH |
| Idol Street - PC | 2001-2010 | - | MATCH / MATCH |
| Left4Dead 2 Steam - PC | - | 4360-4390 | MATCH / MATCH |
| Lineage 2 - PC | 7777,10000,11000,13000 | - | MATCH / MATCH |
| LostSaga (LS) - PC | 14000-14010 | 14000-14010 | MATCH / MATCH |
| Lune of eden - PC | 8400 | - | MATCH / MATCH |
| Mercenary ops - PC | 6000-6125 | - | MATCH / MATCH |
| Mircovolt - PC | 13000 | - | MATCH / MATCH |
| Modo Marble - PC | 28900-28914 | - | MATCH / MATCH |
| Overwatch - PC | 1119,3724,6113 | 3478-3479,5060,5062,6250,12000-64000 | MATCH / MATCH |
| Paladins Hi-Rez - PC | 9000-9999 | 9002-9999 | MATCH / MATCH |
| PointBlank (PB) - PC | 39190-39200 | 40000-40010 | MATCH / MATCH |
| PUBG - PC | - | 7080-8000 | MATCH / MATCH |
| Ragnarok 2 - PC | 7201-7210,7401-7410 | - | MATCH / MATCH |
| RF Rising Force - PC | 27780 | - | MATCH / MATCH |
| Ring of Elysium (ROE) - PC | 9002,10000-10015 | - | MATCH / MATCH |
| Rules Of Survival (ROS) - PC | - | 24000-24100 | MATCH / MATCH |
| Seal Online - PC | 1818 | - | MATCH / MATCH |
| Special Force - PC | 27920-27940 | 30000-30030 | MATCH / MATCH |
| StarCraft II - PC | 1119,6113,1120,80,3724 | 1119,6113,1120,80,3724 | MATCH / MATCH |
| StarCraft - PC | 6112 | 9401,9600,16440-16450 | MATCH / MATCH |
| Valorant (RIOT) - PC | 2099,5222-5223,8088,8393-8400 | 7000-7500,8088 | MATCH / MATCH |
| Warcraft II - PC | 6112-6119 | 6112-6119 | MATCH / MATCH |
| Warcraft III - PC | 6112,6113-6119 | - | MATCH / MATCH |
| Warframe - PC | 4950-4955 | 6695-6699 | MATCH / MATCH |
| World of Tanks (WOT) - PC | 20000-25000,8081,8088,32801,3280 | 12000-29999,32801-32825, | MATCH / MATCH |
| World of Warcraft - PC | 3724,1119,6012 | 3724,1119,6012 | MATCH / MATCH |
| X-shot Indonesia - PC | 7320-7350 | 7800-7850,30000 | MATCH / MATCH |
