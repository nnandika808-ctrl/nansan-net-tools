# Field mapping

Observed defaults are metadata, not evidence of RouterOS compatibility. Backend validation is intentionally stricter against malformed identifiers, control characters, duplicate WAN interfaces, invalid IPv4/CIDR and excessive PCC rule counts; these input-handling differences are explicit and are not reference parity claims.

## G01

Source: https://buananet.com/mikrotik-static-routing.html · Scenario: initial page after manual Premium sign-in · Status: FORM OBSERVED / OUTPUT UNVERIFIED.

| Reference ID / label | Type | Default | Options / condition |
|---|---|---|---|
| routing-list | select-one | Youtube | [ Streaming ] [] (disabled heading); Youtube [Youtube]; Tiktok [Tiktok]; Vidio [Vidio]; Iflix [Iflix]; Netflix [Netflix]; Spotify [Spotify]; Wetv [Wetv]; Vimeo [Vimeo]; Twitch [Twitch]; [ Social Media ] [] (disabled heading); Facebook [Facebook]; Instagram [Instagram]; WhatsApp [WhatsApp]; Twitter / X [Twitter-X]; Telegram [Telegram]; Threads [Threads]; Imo.im [Imo]; Snapchat [Snapchat]; Linkedin [Linkedin]; Pinterest [Pinterest]; [ E-Commerce ] [] (disabled heading); Amazon [Amazon]; Alibaba [Alibaba]; Etsy [Etsy]; Rakuten [Rakuten]; Shopify [Shopify]; Wish [Wish]; Temu [Temu]; Ralali [Ralali]; Shopee [Shopee]; Tokopedia [Tokopedia]; Bukalapak [Bukalapak]; Lazada [Lazada]; Blibli [Blibli]; Olx [Olx]; [ Video Conperence ] [] (disabled heading); Zoom Meeting [Zoom]; Microsoft Teams [Teams]; Google Meet [Google-Meet]; Discord [Discord]; Webex [Webex]; Skype [Skype]; Jitsi [Jitsi]; Whereby [Whereby]; [ Other List IP ] [] (disabled heading); Meta [Meta]; Google [Google]; Speedtest [Speedtest]; GGC (Google Global Cache) [GGC]; Nice IIX or OIXP (indonesia) [NICE-OIXP]; All Indonesian Bank [Bank-Indonesia] |
| ros-list | select-one | ros6 | RouterOS v6.xx [ros6]; RouterOS v7.xx [ros7] |
| match-condition | select-one | tls-host | TLS-HOST [tls-host]; CONTENT [content] |
| gateway-option | text | 192.168.1.1 | — |
| routing-mark | text | To-ISP-1 | — |

## G02

Source: https://buananet.com/mikrotik-queue-qos.html · Scenario: initial page after manual Premium sign-in · Status: FORM OBSERVED / OUTPUT UNVERIFIED.

| Reference ID / label | Type | Default | Options / condition |
|---|---|---|---|
| Queue-Options | select-one | Queue-Simple | Queue Simple [Queue-Simple]; Queue Tree [Queue-Tree] |
| Queue-List | select-one | Youtube | [ Streaming ] [] (disabled heading); Youtube [Youtube]; Tiktok [Tiktok]; Vidio [Vidio]; Iflix [Iflix]; Netflix [Netflix]; Spotify [Spotify]; Wetv [Wetv]; Vimeo [Vimeo]; Twitch [Twitch]; [ Social Media ] [] (disabled heading); Facebook [Facebook]; Instagram [Instagram]; WhatsApp [WhatsApp]; Twitter / X [Twitter-X]; Telegram [Telegram]; Threads [Threads]; Imo.im [Imo]; Snapchat [Snapchat]; Linkedin [Linkedin]; Pinterest [Pinterest]; [ E-Commerce ] [] (disabled heading); Amazon [Amazon]; Alibaba [Alibaba]; Etsy [Etsy]; Rakuten [Rakuten]; Shopify [Shopify]; Wish [Wish]; Temu [Temu]; Ralali [Ralali]; Shopee [Shopee]; Tokopedia [Tokopedia]; Bukalapak [Bukalapak]; Lazada [Lazada]; Blibli [Blibli]; Olx [Olx]; [ Video Conperence ] [] (disabled heading); Zoom Meeting [Zoom]; Microsoft Teams [Teams]; Google Meet [Google-Meet]; Discord [Discord]; Webex [Webex]; Skype [Skype]; Jitsi [Jitsi]; Whereby [Whereby]; [ Other List IP ] [] (disabled heading); Meta [Meta]; Google [Google]; Speedtest [Speedtest]; GGC (Google Global Cache) [GGC]; Nice IIX or OIXP (indonesia) [NICE-OIXP]; All Indonesian Bank [Bank-Indonesia] |
| Queue-Name | text | -> YOUTUBE | — |
| Priority | text | 8 | — |
| Max-Limit | text | 20M | — |
| match-condition | select-one | tls-host | TLS-HOST [tls-host]; CONTENT [content] |

## G03

Source: https://buananet.com/pcc.html · Scenario: initial page after manual Premium sign-in · Status: FORM OBSERVED / OUTPUT UNVERIFIED.

| Reference ID / label | Type | Default | Options / condition |
|---|---|---|---|
| num-wan-lines | number | 2 | — |
| routeros-version | select-one | v6.xx | RouterOS v6.xx [v6.xx]; RouterOS v7.xx [v7.xx] |
| local-target-type | select-one | IP Address List | IP Address List [IP Address List]; In. Interface [In. Interface]; In. Interface List [In. Interface List] |
| interface-target | text | 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8 | — |
| bandwidth-ratio-checkbox | checkbox | false | — |
| recursive-checkbox | checkbox | false | — |
| pccType | select-one | both-addresses-and-ports | both-addresses-and-ports (*Recommended) [both-addresses-and-ports]; both-addresses [both-addresses]; both-ports [both-ports]; dst-address [dst-address]; dst-address-and-port [dst-address-and-port]; dst-port [dst-port]; src-address [src-address]; src-address-and-port [src-address-and-port]; src-port [src-port] |
| wan-isp-1 | text | ether1 | — |
| speed-isp-1 | number |  | Initially disabled |
| gateway-isp-1 | text | 192.168.1.1 | — |
| ip-public-check-isp-1 | text |  | Initially disabled |
| wan-isp-2 | text | ether2 | — |
| speed-isp-2 | number |  | Initially disabled |
| gateway-isp-2 | text | 192.168.2.1 | — |
| ip-public-check-isp-2 | text |  | Initially disabled |

## G04

Source: https://buananet.com/mikrotik-pcc-lb-starlink.html · Scenario: initial page after manual Premium sign-in · Status: FORM OBSERVED / OUTPUT UNVERIFIED.

| Reference ID / label | Type | Default | Options / condition |
|---|---|---|---|
| ros | select-one | v6 | RouterOS v6 [v6]; RouterOS v7 [v7] |
| count | select-one | 2 | 2 [2]; 3 [3]; 4 [4]; 5 [5]; 6 [6]; 7 [7]; 8 [8]; 9 [9]; 10 [10] |
| mode | select-one | equal | Equal (Balanced) [equal]; Manual Ratio [manual] |
| localIpTarget | text | 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8 | — |
| pccType | select-one | both-addresses-and-ports | both-addresses-and-ports (*Recommended) [both-addresses-and-ports]; both-addresses [both-addresses]; both-ports [both-ports]; dst-address [dst-address]; dst-address-and-port [dst-address-and-port]; dst-port [dst-port]; src-address [src-address]; src-address-and-port [src-address-and-port]; src-port [src-port] |
| netwatchEnable | checkbox | false | — |
| netwatchTargets | text | 1.1.1.1,1.1.1.2 | — |
| (unnamed Starlink connection field) | text | ether1 | — |
| (unnamed Starlink connection field) | number | 1 | — |
| (unnamed Starlink connection field) | text | ether2 | — |
| (unnamed Starlink connection field) | number | 1 | — |

## G05

Source: https://buananet.com/static-routing-games.html · Scenario: initial page after manual Premium sign-in · Status: FORM OBSERVED / OUTPUT UNVERIFIED.

| Reference ID / label | Type | Default | Options / condition |
|---|---|---|---|
| ros-list | select-one | ros6 | RouterOS v6.xx [ros6]; RouterOS v7.xx [ros7] |
| ip-gateway-input | text |  | — |
| myInput | text |  | — |
| ArenaOfValor-Mobile | checkbox | false | — |
| AmongUs-Mobile | checkbox | false | — |
| BooyaCapsaSusun-Mobile | checkbox | false | — |
| BooyaDominoQiuqiu-Mobile | checkbox | false | — |
| ClashOfClans-Mobile | checkbox | false | — |
| ClashRoyale-Mobile | checkbox | false | — |
| CODCallOfDuty-Mobile | checkbox | false | — |
| DOTA2-Mobile | checkbox | false | — |
| DominoQq-Mobile | checkbox | false | — |
| DragonNest-Mobile | checkbox | false | — |
| DreamLeagueSoccer-Mobile | checkbox | false | — |
| FIFAONLINE-Mobile | checkbox | false | — |
| FreeFire-Mobile | checkbox | false | — |
| GenshinImpact-Mobile | checkbox | false | — |
| HiggsDomino-Mobile | checkbox | false | — |
| LeagueofLegends-Mobile | checkbox | false | — |
| LastEmpireWarZ-Mobile | checkbox | false | — |
| LineLetsGetRich-Mobile | checkbox | false | — |
| Minecraft-Mobile | checkbox | false | — |
| MobileLegends-Mobile | checkbox | false | — |
| Mostly-Mobile | checkbox | false | — |
| NarutoLittleNinja-Mobile | checkbox | false | — |
| Netmarble-Mobile | checkbox | false | — |
| PointBlank-Mobile | checkbox | false | — |
| PUBG-Mobile | checkbox | false | — |
| Roblox-Mobile | checkbox | false | — |
| RpgToramOnline-Mobile | checkbox | false | — |
| RulesOfSurvival-Mobile | checkbox | false | — |
| ShinobiHeroes-Mobile | checkbox | false | — |
| BallPool-WebGames | checkbox | false | — |
| CastleVille-WebGames | checkbox | false | — |
| Headshot-WebGames | checkbox | false | — |
| EmpireAllies-WebGames | checkbox | false | — |
| LeagueofAngels-WebGames | checkbox | false | — |
| MegarealmRiseOfChaos-WebGames | checkbox | false | — |
| PerjuanganSemut-WebGames | checkbox | false | — |
| Roblox-WebGames | checkbox | false | — |
| SwordofAngels-WebGames | checkbox | false | — |
| TexasHoldEmPoker-WebGames | checkbox | false | — |
| Warflare-WebGames | checkbox | false | — |
| WildOnes-WebGames | checkbox | false | — |
| Atlantica-PC | checkbox | false | — |
| AuraKingdom-PC | checkbox | false | — |
| Ayodance-PC | checkbox | false | — |
| OriginAPEXLegends-PC | checkbox | false | — |
| BlackDesertOnline-PC | checkbox | false | — |
| BlackDesertOnlinePS-PC | checkbox | false | — |
| BlackDesertOnlineSteam-PC | checkbox | false | — |
| BlackDesertOnlineXbox-PC | checkbox | false | — |
| Blackretribution-PC | checkbox | false | — |
| BlizzardBattleNet-PC | checkbox | false | — |
| BountyHound-PC | checkbox | false | — |
| CabalExtremePrivate-PC | checkbox | false | — |
| CabalIndonesia-PC | checkbox | false | — |
| CallofDutyBO-PC | checkbox | false | — |
| CallofDutyBOPS-PC | checkbox | false | — |
| CallofDutyBOXBox-PC | checkbox | false | — |
| CallofDutyMWPC-PC | checkbox | false | — |
| CallofDutyMWPS-PC | checkbox | false | — |
| CallofDutyMWXBox-PC | checkbox | false | — |
| ClashofGod-PC | checkbox | false | — |
| CSGOPlaystation-PC | checkbox | false | — |
| CSGOSteam-PC | checkbox | false | — |
| CSGOXbox-PC | checkbox | false | — |
| CrossFireindonesia-PC | checkbox | false | — |
| DiabloIII-PC | checkbox | false | — |
| DiabloII-PC | checkbox | false | — |
| Diablo-PC | checkbox | false | — |
| Dota2Steam-PC | checkbox | false | — |
| DragonNest-PC | checkbox | false | — |
| Dragona-PC | checkbox | false | — |
| FifaOnline3-PC | checkbox | false | — |
| FORTNITEEPICGAMES-PC | checkbox | false | — |
| LeagueofLegend-PC | checkbox | false | — |
| GrandChase-PC | checkbox | false | — |
| Hearthstone-PC | checkbox | false | — |
| HeroesoftheStorm-PC | checkbox | false | — |
| HONHeroesofNewerth-PC | checkbox | false | — |
| HeroesofAtarsia-PC | checkbox | false | — |
| IdolStreet-PC | checkbox | false | — |
| Left4Dead2Steam-PC | checkbox | false | — |
| Lineage2-PC | checkbox | false | — |
| LostSaga-PC | checkbox | false | — |
| Luneofeden-PC | checkbox | false | — |
| Mercenaryops-PC | checkbox | false | — |
| Mircovolt-PC | checkbox | false | — |
| ModoMarble-PC | checkbox | false | — |
| Overwatch-PC | checkbox | false | — |
| PaladinsHiRez-PC | checkbox | false | — |
| PointBlank-PC | checkbox | false | — |
| PUBG-PC | checkbox | false | — |
| Ragnarok2-PC | checkbox | false | — |
| RFRisingForce-PC | checkbox | false | — |
| ROERingofElysium-PC | checkbox | false | — |
| ROSRulesOfSurvival-PC | checkbox | false | — |
| SealOnline-PC | checkbox | false | — |
| SpecialForce-PC | checkbox | false | — |
| StarCraftII-PC | checkbox | false | — |
| StarCraft-PC | checkbox | false | — |
| Valorant-PC | checkbox | false | — |
| WarcraftIIBattleNet-PC | checkbox | false | — |
| WarcraftIII-PC | checkbox | false | — |
| Warframe-PC | checkbox | false | — |
| WorldofTanks-PC | checkbox | false | — |
| WorldofWarcraft-PC | checkbox | false | — |
| XshotIndonesia-PC | checkbox | false | — |

Application schema is in resources/generators/. Unnamed G04 interface and ratio fields receive internal connection-N keys; this mapping is an application identifier, not a reference field ID. G03 speed/check fields depend on the ratio/recursive toggles; G04 Netwatch targets are conditional. More-WAN field preservation/reset behavior remains unverified.
