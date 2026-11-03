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

const url = 'https://buananet.com/simple-queue-or-queue-tree-generator.html';
console.log('Navigating to ' + url);
await call('browser_navigate', { url });

const formData = await evalInBrowser(`() => {
  return {
    id: "G06",
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
        label: e.labels?.[0]?.textContent?.trim() || e.closest('.isp-bandwidth-input-group, .form-group')?.querySelector('label')?.textContent?.trim() || null,
        placeholder: e.getAttribute('placeholder'),
        value: e.tagName === 'TEXTAREA' ? '[output omitted]' : e.value,
        checked: e.type === 'checkbox' || e.type === 'radio' ? e.checked : undefined,
        required: e.required,
        disabled: e.disabled,
        options: e.options ? [...e.options].map(o => ({label: o.text.trim(), value: o.value, selected: o.selected, disabled: o.disabled})) : undefined
      })),
    buttons: [...document.querySelectorAll('button,input[type=button],input[type=submit]')].map(e => ({id: e.id, text: e.textContent.trim() || e.value, disabled: e.disabled})),
    outputs: [...document.querySelectorAll('pre,code,textarea')].filter(e => e.id !== 'chat-input').map(e => ({tag: e.tagName, id: e.id, length: (e.innerText || e.value || '').length}))
  };
}`);

await fs.writeFile('docs/reference/evidence/G06-form.json', JSON.stringify(formData, null, 2));
console.log('Saved docs/reference/evidence/G06-form.json (' + formData.fields.length + ' fields)');
