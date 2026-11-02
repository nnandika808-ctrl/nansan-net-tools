import http from 'node:http';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
import {authGuardFunction} from './browser-privacy.mjs';

if(process.env.NANSAN_AUDIT_SNAPSHOT_MODE!=='none')throw new Error('Start the reference browser with scripts/start-reference-browser.ps1 so automatic snapshots are disabled before manual login.');

const client = new Client({ name: 'nansan-reference-audit', version: '0.0.0' });
await client.connect(new StreamableHTTPClientTransport(new URL('http://127.0.0.1:8931/mcp')));
const allowed = new Set(['browser_navigate', 'browser_tabs', 'browser_click', 'browser_evaluate', 'browser_resize', 'browser_select_option', 'browser_take_screenshot','browser_close']);
let busy = false;
const server = http.createServer(async (req, res) => {
  res.setHeader('Content-Type', 'application/json');
  if (req.method !== 'POST' || req.url !== '/call' || req.headers.origin || !req.headers['content-type']?.startsWith('application/json')) {
    res.writeHead(403).end('{}');
    return;
  }
  if (busy) { res.writeHead(409).end('{}'); return; }
  busy = true;
  try {
    let body = '';
    for await (const chunk of req) {
      body += chunk;
      if (body.length > 32768) throw new Error('Request too large');
    }
    const request = JSON.parse(body);
    if (!allowed.has(request.name)) throw new Error('Unsupported browser operation');
    if(['browser_click','browser_evaluate','browser_select_option','browser_take_screenshot'].includes(request.name)){
      const probe=await client.callTool({name:'browser_evaluate',arguments:{function:authGuardFunction}});
      const result=probe.content?.filter(block=>block.type==='text').map(block=>block.text).join('\n')??'';
      const match=result.match(/### Result\s+(true|false)/);
      if(probe.isError||!match||match[1]==='true')throw new Error('Authentication screen is private. Complete login manually; snapshots, screenshots and form inspection are blocked here.');
    }
    const result = await client.callTool({ name: request.name, arguments: request.arguments ?? {} });
    res.end(JSON.stringify(result));
  } catch (error) {
    res.writeHead(500).end(JSON.stringify({ error: error.message }));
  } finally { busy = false; }
});
server.listen(8932, '127.0.0.1', () => console.log('Audit session ready on loopback port 8932. Authentication stays in browser memory.'));
async function stop() { server.close(); await client.close(); process.exit(0); }
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
