import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {chromium} from '@playwright/test';

const origin='http://127.0.0.1:8091';
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const guest=await browser.newContext({viewport:{width:1440,height:1000}});const page=await guest.newPage();await page.goto(origin);await page.screenshot({path:'.runtime/staging-desktop.png'});await page.setViewportSize({width:390,height:844});await page.screenshot({path:'.runtime/staging-mobile.png'});
 const credential=JSON.parse(execFileSync('powershell.exe',['-NoProfile','-Command',"$credential=Import-Clixml -LiteralPath '.runtime/staging-owner.credential.xml'; @{username=$credential.UserName;password=$credential.GetNetworkCredential().Password}|ConvertTo-Json -Compress"],{encoding:'utf8',windowsHide:true}));
 const context=await browser.newContext();const owner=await context.newPage();await owner.goto(origin+'/login');await owner.getByLabel('Username',{exact:true}).fill(credential.username);await owner.getByLabel('Password',{exact:true}).fill(credential.password);await owner.getByRole('button',{name:'Masuk',exact:true}).click();await owner.waitForURL('**/dashboard');credential.password='';await owner.goto(origin+'/account');await owner.locator('a[href="/admin"]').first().waitFor();
 const token=await owner.locator('meta[name=csrf-token]').getAttribute('content');const logout=await context.request.post(origin+'/logout',{headers:{'X-CSRF-TOKEN':token,Accept:'application/json'}});if(!logout.ok())throw new Error('Owner logout failed.');
 const report={observed_at:new Date().toISOString(),staging_owner_login:'passed',staging_owner_logout:'passed',password_storage:'Windows DPAPI; not printed or saved as plaintext',screenshots:'Guest desktop and mobile only; ignored .runtime paths'};
 await fs.writeFile('docs/staging/owner-login.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report));
}catch{console.error('Staging preview or owner-login verification failed; credential details withheld.');process.exitCode=1;}
finally{await browser.close();}
