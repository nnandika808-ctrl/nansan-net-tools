import fs from 'node:fs/promises';
try{await fs.access('.env');console.log('Existing .env preserved.');}catch{
 let text=await fs.readFile('.env.example','utf8');
 for(const [key,value] of Object.entries({APP_ENV:'local',APP_URL:'http://127.0.0.1:8000',DB_CONNECTION:'sqlite',DB_DATABASE:'database/database.sqlite',SESSION_SECURE_COOKIE:'false',CACHE_STORE:'file',QUEUE_CONNECTION:'database',TURNSTILE_ENABLED:'false'})) text=text.replace(new RegExp('^'+key+'=.*$','m'),key+'='+value);
 await fs.writeFile('.env',text);console.log('Local-only environment created. Production defaults preserved in .env.example.');
}
await fs.open('database/database.sqlite','a').then(f=>f.close());
