import fs from 'node:fs/promises';

async function call(name, args = {}) {
  const response = await fetch('http://127.0.0.1:8932/call', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, arguments: args }),
  });
  const result = await response.json();
  if (!response.ok || result.isError) throw new Error(JSON.stringify(result));
  return result;
}

async function evalInBrowser(fn) {
  const res = await call('browser_evaluate', { function: fn });
  const block = res.content?.find(b => b.type === 'text');
  if (!block) throw new Error('No text block in evaluate result');
  const match = block.text.match(/^### Result\s*\n([\s\S]*)$/);
  if (!match) throw new Error('Failed to parse result block: ' + block.text);
  return JSON.parse(match[1]);
}

const url = 'https://buananet.com/mikrotik-burst-limit-calculator.html';
console.log('Navigating to ' + url);
await call('browser_navigate', { url });

const formData = await evalInBrowser(`() => {
  return {
    id: "G07",
    url: "${url}",
    observed_at: new Date().toISOString().split('T')[0],
    method: "Playwright MCP loopback bridge; manual authenticated session",
    status: "FORM_OBSERVED; AUDITED",
    title: document.title,
    heading: document.querySelector('h1')?.textContent?.trim(),
    fields: [...document.querySelectorAll('input:not([type=hidden]),select,textarea')]
      .filter(e => !e.closest('[id*=chat]') && !/password|email|captcha/i.test(e.name+' '+e.id+' '+e.type))
      .map(e => ({
        id: e.id,
        name: e.name,
        tag: e.tagName,
        type: e.type,
        label: e.labels?.[0]?.textContent?.trim() || e.closest('.form-group, .input-group, div')?.querySelector('label, b, span')?.textContent?.trim() || null,
        placeholder: e.getAttribute('placeholder'),
        value: e.tagName === 'TEXTAREA' ? '[output omitted]' : e.value,
        checked: e.type === 'checkbox' || e.type === 'radio' ? e.checked : undefined,
        required: e.required,
        disabled: e.disabled
      })),
    buttons: [...document.querySelectorAll('button,input[type=button],input[type=submit]')].map(e => ({id: e.id, text: e.textContent.trim() || e.value, disabled: e.disabled}))
  };
}`);

await fs.writeFile('docs/reference/evidence/G07-form.json', JSON.stringify(formData, null, 2));
console.log('Saved docs/reference/evidence/G07-form.json');

// Test several cases
const testCases = [
  { id: 'C01', upMax: '3M', upBurst: '6M', upDur: '15', downMax: '5M', downBurst: '8M', downDur: '15' },
  { id: 'C02', upMax: '10M', upBurst: '20M', upDur: '10', downMax: '20M', downBurst: '40M', downDur: '10' },
  { id: 'C03', upMax: '1M', upBurst: '2M', upDur: '20', downMax: '2M', downBurst: '4M', downDur: '20' },
  { id: 'C04', upMax: '512K', upBurst: '1M', upDur: '15', downMax: '1M', downBurst: '2M', downDur: '15' },
  { id: 'C05', upMax: '50M', upBurst: '100M', upDur: '8', downMax: '100M', downBurst: '200M', downDur: '8' },
];

const results = [];
for (const tc of testCases) {
  const data = await evalInBrowser(`() => {
    document.querySelector('#up-max-limit').value = ${JSON.stringify(tc.upMax)};
    document.querySelector('#up-max-limit').dispatchEvent(new Event('input', { bubbles: true }));
    document.querySelector('#up-speed-bonus').value = ${JSON.stringify(tc.upBurst)};
    document.querySelector('#up-speed-bonus').dispatchEvent(new Event('input', { bubbles: true }));
    document.querySelector('#up-interval-bonus').value = ${JSON.stringify(tc.upDur)};
    document.querySelector('#up-interval-bonus').dispatchEvent(new Event('input', { bubbles: true }));

    document.querySelector('#down-max-limit').value = ${JSON.stringify(tc.downMax)};
    document.querySelector('#down-max-limit').dispatchEvent(new Event('input', { bubbles: true }));
    document.querySelector('#down-speed-bonus').value = ${JSON.stringify(tc.downBurst)};
    document.querySelector('#down-speed-bonus').dispatchEvent(new Event('input', { bubbles: true }));
    document.querySelector('#down-interval-bonus').value = ${JSON.stringify(tc.downDur)};
    document.querySelector('#down-interval-bonus').dispatchEvent(new Event('input', { bubbles: true }));

    const outText = document.querySelector('.list-output-content')?.innerText || '';
    const rateLimitText = document.querySelector('#mytable')?.innerText || '';
    return {
      outputTable: outText,
      rateLimit: rateLimitText.replace(/\\s+/g, ' ').trim()
    };
  }`);
  results.push({ case: tc, result: data });
}

await fs.writeFile('docs/reference/evidence/G07-cases-semantics.json', JSON.stringify(results, null, 2));
console.log('Saved docs/reference/evidence/G07-cases-semantics.json (' + results.length + ' cases)');
