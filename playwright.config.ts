import {defineConfig,devices} from '@playwright/test';
export default defineConfig({
 testDir:'tests/e2e',fullyParallel:false,workers:1,retries:0,reporter:[['list'],['json',{outputFile:'.runtime/e2e-results.json'}]],
 use:{baseURL:'http://127.0.0.1:8001',channel:process.platform==='win32'?'chrome':undefined,colorScheme:'dark',trace:'off',screenshot:'only-on-failure',...devices['Desktop Chrome']},
 webServer:{command:'node scripts/e2e-server.mjs',url:'http://127.0.0.1:8001/up',reuseExistingServer:false,timeout:60000},
});
