import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

// Browser profile is ephemeral. Never export storage state, cookies or tokens.
const client = new Client({ name: 'nansan-reference-audit', version: '0.0.0' });
await client.connect(new StreamableHTTPClientTransport(new URL('http://127.0.0.1:8931/mcp')));
try {
  const request = JSON.parse(await new Promise(resolve => {
    let input = '';
    process.stdin.setEncoding('utf8');
    process.stdin.on('data', data => { input += data; });
    process.stdin.on('end', () => resolve(input));
  }));
  if (request.list) {
    const { tools } = await client.listTools();
    console.log(JSON.stringify(tools.map(({ name, inputSchema }) => ({ name, inputSchema }))));
  } else {
    const result = await client.callTool({ name: request.name, arguments: request.arguments ?? {} });
    console.log(JSON.stringify(result));
  }
} finally {
  await client.close();
}
