import fs from 'node:fs/promises';

export async function call(name, args = {}) {
  const response = await fetch('http://127.0.0.1:8932/call', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, arguments: args }),
  });
  const result = await response.json();
  if (!response.ok || result.isError) throw new Error(JSON.stringify(result));
  return result;
}

const inventory = [
  ['G01', 'https://buananet.com/mikrotik-static-routing.html'],
  ['G02', 'https://buananet.com/mikrotik-queue-qos.html'],
  ['G03', 'https://buananet.com/pcc.html'],
  ['G04', 'https://buananet.com/mikrotik-pcc-lb-starlink.html'],
  ['G05', 'https://buananet.com/static-routing-games.html'],
];

// Observe form metadata only. Do not read source scripts, account fields, or datasets.
const collect = `() => ({
  title: document.title,
  heading: document.querySelector('h1')?.textContent?.trim(),
  fields: [...document.querySelectorAll('input:not([type=hidden]),select,textarea')]
    .filter(e => !e.closest('[id*=chat]') && !/password|email|captcha/i.test(e.name+' '+e.id+' '+e.type))
    .map(e => ({id:e.id, name:e.name, tag:e.tagName, type:e.type,
      label: e.labels?.[0]?.textContent?.trim() || e.closest('.form-group')?.querySelector('label')?.textContent?.trim() || null,
      placeholder:e.getAttribute('placeholder'),
      value: e.tagName === 'TEXTAREA' ? '[output omitted]' : e.value,
      checked:e.type==='checkbox' || e.type==='radio' ? e.checked : undefined,
      required:e.required, disabled:e.disabled, min:e.min, max:e.max,
      options:e.options ? [...e.options].map(o=>({label:o.text,value:o.value,selected:o.selected,disabled:o.disabled})) : undefined
    })),
  buttons:[...document.querySelectorAll('button,input[type=button],input[type=submit]')].map(e=>({id:e.id,text:e.textContent.trim()||e.value,disabled:e.disabled})),
  outputs:[...document.querySelectorAll('pre,code,textarea')].map(e=>({tag:e.tagName,id:e.id,length:(e.innerText||e.value||'').length})),
  accessMessage: [...document.querySelectorAll('pre,code')].some(e=>/please login and subscribe/i.test(e.innerText))
})`;

if (process.argv.includes('--inventory')) {
  await fs.mkdir('docs/reference/evidence', { recursive:true });
  for (const [id,url] of inventory) {
    await call('browser_navigate', {url});
    const result = await call('browser_evaluate', {function:collect});
    const block = result.content.find(c=>c.type==='text' && c.text.includes('### Result'));
    if (!block) throw new Error('Missing observation');
    const match = block.text.match(/### Result\s+([\s\S]*?)(?:\n###|$)/);
    const observed = JSON.parse(match[1].replace(/^```json\s*|\s*```$/g,''));
    const record = {id,url,observed_at:'2026-11-03',method:'Playwright MCP 0.0.84; authenticated manual session',status:'FORM_OBSERVED; OUTPUT_UNVERIFIED',...observed};
    await fs.writeFile('docs/reference/evidence/'+id+'-form.json',JSON.stringify(record,null,2)+'\n');
    console.log(JSON.stringify({id,fields:record.fields.length,buttons:record.buttons.length,status:record.status}));
  }
}
