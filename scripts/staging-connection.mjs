import net from 'node:net';
import {Client} from 'ssh2';
const host=process.argv[2],fingerprint=process.argv[3];
if(!host||!fingerprint)throw new Error('Authorized host and verified fingerprint required.');
process.stdout.write('SSH password input ready (hidden).\n');
if(process.stdin.isTTY)process.stdin.setRawMode(true);process.stdin.resume();let secret='';
process.stdin.on('data',chunk=>{secret+=chunk.toString();if(secret.includes('\u0003'))process.exit(130);if(!/[\r\n]/.test(secret))return;process.stdin.removeAllListeners('data');process.stdin.pause();if(process.stdin.isTTY)process.stdin.setRawMode(false);
 const client=new Client();let server;const sockets=new Set();
 const stopForward=()=>{for(const socket of sockets)socket.destroy();sockets.clear();server?.close();};
 client.on('error',e=>{console.error('Staging connection failed: '+e.message);process.exitCode=1;stopForward();client.destroy();});
 client.on('close',stopForward);
 client.on('ready',()=>{secret='';client.exec("systemctl is-active nansan-staging-web nansan-staging-php nansan-staging-redis nansan-staging-worker nansan-staging-scheduler.timer; ss -lnt '( sport = :8091 or sport = :6381 )'; python3 -c \"import urllib.request; r=urllib.request.urlopen('http://127.0.0.1:8091/up'); print('HTTP health:',r.status)\"",(error,stream)=>{if(error)throw error;stream.on('data',data=>process.stdout.write(data));stream.stderr.on('data',data=>process.stdout.write(data));stream.on('close',()=>{
  server=net.createServer(local=>{sockets.add(local);local.on('close',()=>sockets.delete(local));client.forwardOut('127.0.0.1',0,'127.0.0.1',8091,(error,remote)=>{if(error){local.destroy();return;}local.pipe(remote).pipe(local);local.on('error',()=>remote.destroy());remote.on('error',()=>local.destroy());});});
  server.listen(8091,'127.0.0.1',()=>console.log('Private staging via encrypted SSH: http://127.0.0.1:8091'));
  server.on('error',e=>{console.error('Local forwarding failed: '+e.message);client.end();});
 });});});
 client.connect({host,port:22,username:'root',password:secret.trim(),readyTimeout:15000,keepaliveInterval:30000,hostHash:'sha256',hostVerifier:hash=>hash===fingerprint});
});
