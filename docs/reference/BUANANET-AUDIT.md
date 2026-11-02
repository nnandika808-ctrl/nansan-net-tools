# Buananet audit

Date: 2026-11-03, Asia/Jakarta. Method: local @playwright/mcp 0.0.84 over loopback, actual Chrome browser, owner manually authenticated. Initial unauthenticated G01 form controls were disabled and output replaced with a login/subscription message. Owner confirmed successful login; subsequent controls were enabled. Authentication state stays in the isolated browser process and is excluded from repository artifacts.

All five source URLs, observed fields, defaults, options and controls are inventoried in [GENERATOR-INVENTORY](GENERATOR-INVENTORY.md) and [FIELD-MAPPING](FIELD-MAPPING.md). Sixteen behavior scenarios are in [BEHAVIOR-MATRIX](BEHAVIOR-MATRIX.md). Desktop viewport: 1440×1000; mobile viewport: 390×844. Mobile structural measurements are in evidence/mobile.json. These measurements are not a visual accessibility certification.

G01/G02 observably generate two steps and depend on remote .rsc datasets. G03 has ratio and recursive controls; a 50:100 pair produces denominator 3 and remainders 0,1,2. G04 uses DHCP clients, and its 2:1:1 manual ratio produces denominator 4. G05 exposes mobile, web and PC game selection; game port freshness and origin have not been independently verified. Every finding is tied to a URL and scenario in the evidence files.

Rights: the owner reports that Buananet said on Facebook that the behavior may be followed. This supports the chosen independent implementation workflow; the scope of redistribution permission for exclusive datasets, complete outputs and third-party source code has not been established. No third-party JavaScript source was extracted. No .rsc datasets were downloaded or bundled. No full reference-output fixtures are committed.

Coverage limits: the option inventory is broader than the exercised scenario set. Browser-native invalid-input handling, all combinations, ROS lab behavior, repeated-import safety, rollback behavior, packet flow/failover and port provenance remain UNVERIFIED. Do not call this a completed exhaustive audit.
