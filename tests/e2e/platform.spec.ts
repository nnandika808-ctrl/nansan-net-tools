import {test,expect} from '@playwright/test';
test('public catalog, search, docs, and generator access gate',async({page})=>{
 await page.goto('/');await expect(page.getByRole('heading',{name:'Jaringan lebih baik. Mulai dari sini.'})).toBeVisible();
 await expect(page.locator('.tool-card')).toHaveCount(5);
 await page.getByRole('textbox',{name:'Cari tools atau dokumentasi…'}).fill('Load Balance');
 await expect(page.locator('.search-results a').first()).toContainText('PCC Load Balancing');
 await page.goto('/tools/static-routing');await expect(page.getByRole('heading',{name:'Masuk ke ruang kerja Anda'})).toBeVisible();
 await page.goto('/docs/verification');await expect(page.locator('article')).toContainText('Exact match');
 await page.goto('/admin');await expect(page).toHaveURL(/\/login/);
});
test('registration, recovery display, favorites, feedback, and account security',async({page})=>{
 const username='e2e_user_'+Date.now().toString().slice(-10);await page.goto('/register');
 await page.getByLabel('Username',{exact:true}).fill(username);await page.getByLabel('Password',{exact:true}).fill('E2E_Only_Password_12345');await page.getByLabel('Konfirmasi password').fill('E2E_Only_Password_12345');await page.getByRole('button',{name:'Buat akun gratis'}).click();
 await expect(page).toHaveURL(/\/dashboard/);const recovery=page.getByRole('dialog',{name:'Kode pemulihan'});await expect(recovery).toBeVisible();await expect(recovery.locator('code')).toHaveCount(8);await recovery.getByRole('button',{name:'Close',exact:true}).click();
 await page.getByRole('button',{name:'Mulai jelajahi'}).click();
 await page.goto('/tools/pcc');await expect(page.getByRole('button',{name:'Generate script'})).toBeDisabled();await page.getByRole('button',{name:'Favorit',exact:true}).click();
 await page.goto('/dashboard');await expect(page.locator('#favorites')).toContainText('PCC Load Balancing');
 await page.goto('/feedback');await page.getByLabel('Judul',{exact:true}).fill('E2E feedback test');await page.getByLabel('Jelaskan masalah atau saran Anda').fill('This is an isolated end to end test report.');await page.getByRole('button',{name:'Kirim laporan'}).click();await expect(page.locator('.feedback-report')).toContainText('E2E feedback test');
 await page.goto('/account');await expect(page.getByRole('heading',{name:'Sesi perangkat'})).toBeVisible();
 await page.getByLabel('Password saat ini').fill('wrong-password');await page.getByLabel('Password baru',{exact:true}).fill('Updated_E2E_Password123');await page.getByLabel('Konfirmasi password').fill('Updated_E2E_Password123');await page.locator('.account-grid>section').nth(1).getByRole('button',{name:'Simpan perubahan'}).click();await expect(page.locator('.field-error')).toBeVisible();
});
test('admin publishes a CMS article and changes feedback status',async({page})=>{
 await page.goto('/login');await page.getByLabel('Username',{exact:true}).fill('e2e_admin');await page.getByLabel('Password',{exact:true}).fill('E2E_Only_Password_12345');await page.getByRole('button',{name:'Masuk',exact:true}).click();await expect(page).toHaveURL(/\/dashboard/);
 await page.goto('/admin/content/articles');await page.getByRole('button',{name:'Tambah baru'}).click();const dialog=page.getByRole('dialog');
 const slug='e2e-article-'+Date.now();await dialog.getByLabel('Judul').fill('E2E published article');await dialog.getByLabel('Slug').fill(slug);await dialog.getByLabel('Isi · Markdown').fill('## E2E content\n\nThis is content created in the isolated test database.');await dialog.getByLabel('Status').selectOption('published');await dialog.getByRole('button',{name:'Simpan perubahan'}).click();await expect(dialog).not.toBeVisible();await page.goto('/docs/'+slug);await expect(page.locator('article')).toContainText('E2E content');
 await page.goto('/admin/feedback');if(await page.getByRole('button',{name:'Edit'}).count()){await page.getByRole('button',{name:'Edit'}).first().click();await page.getByRole('dialog').getByLabel('Status').selectOption('resolved');await page.getByLabel('Reply').fill('Resolved in isolated E2E test.');await page.getByRole('dialog').getByRole('button',{name:'Simpan perubahan'}).click();await expect(page.locator('table')).toContainText('resolved');}
});
for(const width of [360,390,768,1440])test('responsive layout at '+width+' pixels',async({page})=>{
 await page.setViewportSize({width,height:900});for(const url of ['/','/tools','/login','/docs/verification']){await page.goto(url);await expect(page.locator('h1')).toBeVisible();expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);}
 if(width<680){await page.goto('/');await page.getByRole('button',{name:'Open navigation'}).click();await expect(page.getByRole('dialog')).toBeVisible();await page.keyboard.press('Escape');await expect(page.getByRole('dialog')).not.toBeVisible();}
});
test('CSRF rejects a write without a browser token',async({request})=>{const response=await request.post('/register',{headers:{Accept:'application/json'},data:{username:'csrf_test',password:'E2E_Only_Password_12345',password_confirmation:'E2E_Only_Password_12345'}});expect(response.status()).toBe(419);});
