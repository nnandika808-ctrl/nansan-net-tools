import {Client} from 'ssh2';
const host=process.argv[2],fingerprint=process.argv[3];
if(host!=='192.168.1.136'||!fingerprint)throw new Error('Authorized staging endpoint and fingerprint required.');
console.log('SSH password input ready (hidden).');if(process.stdin.isTTY)process.stdin.setRawMode(true);process.stdin.resume();let secret='';
process.stdin.on('data',chunk=>{secret+=chunk;if(!/[\r\n]/.test(secret))return;process.stdin.removeAllListeners('data');process.stdin.pause();if(process.stdin.isTTY)process.stdin.setRawMode(false);
 const client=new Client();client.on('error',e=>{console.error(e.message);process.exitCode=1;});client.on('ready',()=>{secret='';
 const python=`import subprocess,pathlib
base=pathlib.Path('/www/wwwroot/nansan-tools-staging')
assert (base/'.owner').read_text().strip()=='NANSAN NET Tools: isolated staging v1'
values=dict(line.split('=',1) for line in (base/'shared/.env').read_text().splitlines() if '=' in line)
commands=[['systemctl','show','nansan-staging-worker','nansan-staging-scheduler.service','-p','MainPID','-p','ExecMainStatus','-p','ActiveState','-p','SubState'],['journalctl','-u','nansan-staging-worker','-n','12','--no-pager','-o','cat'],['journalctl','-u','nansan-staging-scheduler.service','-n','25','--no-pager','-o','cat']]
for command in commands:
 result=subprocess.run(command,stdout=subprocess.PIPE,stderr=subprocess.STDOUT).stdout.decode(errors='replace')
 for key,value in values.items():
  if value and any(token in key for token in ['KEY','PASSWORD','SECRET']):result=result.replace(value,'<redacted>')
 print(result[-9000:])
`;
 client.exec('python3 -',(error,stream)=>{if(error)throw error;stream.on('data',d=>process.stdout.write(d));stream.stderr.on('data',d=>process.stdout.write(d));stream.end(python);stream.on('close',code=>{process.exitCode=code;client.end();});});});
 client.connect({host,username:'root',password:secret.trim(),hostHash:'sha256',hostVerifier:hash=>hash===fingerprint});
});
