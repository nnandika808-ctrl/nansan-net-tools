import {Client} from 'ssh2';
const host=process.argv[2];
if(!host||!/^\d{1,3}(\.\d{1,3}){3}$/.test(host))throw new Error('Pass the explicitly authorized server IPv4 address.');
process.stdout.write('SSH password input ready (hidden).\n');
if(process.stdin.isTTY)process.stdin.setRawMode(true);
process.stdin.resume();
let secret='';
process.stdin.on('data',chunk=>{
 const value=chunk.toString();if(value.includes('\u0003'))process.exit(130);
 secret+=value;
 if(!/[\r\n]/.test(secret))return;
 process.stdin.removeAllListeners('data');process.stdin.pause();
 if(process.stdin.isTTY)process.stdin.setRawMode(false);
 const client=new Client();
 client.on('error',error=>{console.error('SSH connection failed: '+error.message);process.exitCode=1;});
 client.on('ready',()=>{
  secret='';
  const command="uname -sr; sed -n '1,3p' /etc/os-release; for phpbin in /www/server/php/*/bin/php; do if test -x \"$phpbin\"; then \"$phpbin\" -r 'echo PHP_VERSION,PHP_EOL;'; fi; done; command -v php composer mysql redis-server nginx cloudflared; mysql --version 2>/dev/null; redis-server --version 2>/dev/null; nginx -v 2>&1; systemctl is-active nginx mysql mysqld redis redis-server cloudflared 2>/dev/null; df -h /; free -m; ls -d /www/wwwroot/* 2>/dev/null";
  const details="test ! -x /www/server/nginx/sbin/nginx || /www/server/nginx/sbin/nginx -v 2>&1; test ! -x /www/server/php/84/bin/php || /www/server/php/84/bin/php -m; ls -l /tmp/php-cgi*.sock 2>/dev/null; find /www/server/panel/vhost/nginx -maxdepth 1 -type f -printf '%f\\n' 2>/dev/null; command -v redis-cli cloudflared supervisorctl; test ! -d /etc/cloudflared || find /etc/cloudflared -maxdepth 1 -type f -printf '%f\\n'; ss -lnt '( sport = :80 or sport = :443 or sport = :6379 or sport = :8091 )'; mysql -N -e 'SELECT VERSION();' 2>/dev/null";
  const services="ss -lntp '( sport = :80 )'; ls -ld /www/server/*; command -v mariadb mysqldump apt-get tar; test ! -f /www/server/panel/data/default.db || echo AAPANEL_DB_PRESENT; test ! -d /www/wwwroot/nansan-tools-staging || echo STAGING_PATH_EXISTS; systemctl list-units --type=service --state=running --no-pager --no-legend | cut -d' ' -f1";
  client.exec(process.argv.includes('--services')?services:process.argv.includes('--details')?details:command,(error,stream)=>{if(error)throw error;stream.on('data',data=>process.stdout.write(data));stream.stderr.on('data',data=>process.stdout.write(data));stream.on('close',()=>client.end());});
 });
 client.connect({host,port:22,username:'root',password:secret.trim(),readyTimeout:15000,hostHash:'sha256',hostVerifier:hash=>{console.log('Observed SSH host key SHA256 (hex): '+hash);return true;}});
});
