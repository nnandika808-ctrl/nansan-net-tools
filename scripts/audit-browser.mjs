import http from 'node:http';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

const client = new Client({ name: 'nansan-reference-audit', version: '0.0.0' });
await client.connect(new StreamableHTTPClientTransport(new URL('http://127.0.0.1:8931/mcp')));
const allowed = new Set(['browser_navigate', 'browser_tabs', 'browser_snapshot', 'browser_click', 'browser_evaluate', 'browser_resize', 'browser_select_option', 'browser_fill_form', 'browser_take_screenshot']);
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
