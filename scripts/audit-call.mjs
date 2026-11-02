let input = '';
for await (const chunk of process.stdin) input += chunk;
const response = await fetch('http://127.0.0.1:8932/call', {
  method: 'POST', headers: { 'Content-Type': 'application/json' }, body: input,
});
console.log(await response.text());
if (!response.ok) process.exitCode = 1;
