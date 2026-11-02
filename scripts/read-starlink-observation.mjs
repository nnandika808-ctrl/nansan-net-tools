import {call} from './reference-audit.mjs';
await call('browser_tabs',{action:'select',index:0});
await call('browser_navigate',{url:'https://buananet.com/mikrotik-pcc-lb-starlink.html'});
const result=await call('browser_evaluate',{function:`() => document.getElementById('out').value`});
console.log(JSON.stringify(result));
