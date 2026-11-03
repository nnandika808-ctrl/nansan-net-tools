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

const scenarios = [
  { id: 'S01', mode: 'Simple Queue', categories: [], down: '100M', up: '50M', qtype: 'default-small' },
  { id: 'S02', mode: 'Simple Queue', categories: ['category-streaming-video'], down: '100M', up: '50M', qtype: 'default-small' },
  { id: 'S03', mode: 'Simple Queue', categories: ['category-social-media'], down: '100M', up: '50M', qtype: 'default-small' },
  { id: 'S04', mode: 'Simple Queue', categories: ['category-e-commerce'], down: '100M', up: '50M', qtype: 'default-small' },
  { id: 'S05', mode: 'Simple Queue', categories: ['category-video-conference'], down: '100M', up: '50M', qtype: 'default-small' },
  { id: 'S06', mode: 'Simple Queue', categories: ['category-streaming-video', 'category-social-media', 'category-e-commerce', 'category-video-conference'], down: '100M', up: '50M', qtype: 'default-small' },
  { id: 'S07', mode: 'Queue Tree', categories: [], down: '100M', up: '50M', qtype: 'default-small' },
  { id: 'S08', mode: 'Queue Tree', categories: ['category-streaming-video'], down: '100M', up: '50M', qtype: 'default-small' },
  { id: 'S09', mode: 'Queue Tree', categories: ['category-streaming-video', 'category-social-media', 'category-e-commerce', 'category-video-conference'], down: '100M', up: '50M', qtype: 'default-small' },
  { id: 'S10', mode: 'Simple Queue', categories: [], down: '30M', up: '10M', qtype: 'default-small' },
  { id: 'S11', mode: 'Simple Queue', categories: [], down: '100M', up: '50M', qtype: 'pcq' },
  { id: 'S12', mode: 'Queue Tree', categories: [], down: '100M', up: '50M', qtype: 'cake' },
];

const results = [];

for (const sc of scenarios) {
  console.log('Testing ' + sc.id + ': ' + sc.mode + ' cats=' + sc.categories.length + ' down=' + sc.down + ' up=' + sc.up + ' qtype=' + sc.qtype);
  
  const fn = `() => {
    // 1. Set mode
    const modeSel = document.querySelector('#globalQueueType');
    if (modeSel && modeSel.value !== ${JSON.stringify(sc.mode)}) {
      modeSel.value = ${JSON.stringify(sc.mode)};
      modeSel.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // 2. Set categories
    const allCats = ['category-streaming-video', 'category-social-media', 'category-e-commerce', 'category-video-conference'];
    for (const c of allCats) {
      const el = document.querySelector('#' + c);
      const shouldCheck = ${JSON.stringify(sc.categories)}.includes(c);
      if (el && el.checked !== shouldCheck) {
        el.click();
      }
    }

    // 3. Set download/upload
    const downInput = document.querySelector('#downloadBandwidthISP');
    if (downInput && downInput.value !== ${JSON.stringify(sc.down)}) {
      downInput.value = ${JSON.stringify(sc.down)};
      downInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    const upInput = document.querySelector('#uploadBandwidthISP');
    if (upInput && upInput.value !== ${JSON.stringify(sc.up)}) {
      upInput.value = ${JSON.stringify(sc.up)};
      upInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // 4. Set global queue type
    const qtypeSel = document.querySelector('#globalQueueTypeInput');
    if (qtypeSel && qtypeSel.value !== ${JSON.stringify(sc.qtype)}) {
      qtypeSel.value = ${JSON.stringify(sc.qtype)};
      qtypeSel.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // 5. Click apply button if present
    const applyBtn = document.querySelector('#applyGlobalSettingsButton');
    if (applyBtn) applyBtn.click();

    // 6. Collect output
    const ta = document.querySelector('#mikrotikScriptOutput');
    const text = ta ? ta.value : '';

    const lines = text.split('\\n');
    const commandLines = lines.filter(l => l.trim().startsWith('add ') || l.trim().startsWith('/'));

    // Collect table rows
    const rows = [...document.querySelectorAll('.table-row')].map(r => {
      return [...r.querySelectorAll('.table-cell, div')].map(c => c.innerText.trim()).filter(Boolean);
    });

    return {
      scriptLength: text.length,
      lineCount: lines.length,
      commandCount: commandLines.length,
      tableRowCount: rows.length,
      rows: rows,
      scriptSample: {
        header: lines.slice(0, 8).join('\\n'),
        sampleCommands: commandLines.slice(0, 15),
        queueSection: text.includes('/queue') ? text.slice(text.indexOf('/queue')) : ''
      }
    };
  }`;

  const data = await evalInBrowser(fn);
  results.push({
    scenario: sc,
    data
  });
}

await fs.writeFile('docs/reference/evidence/G06-cases-semantics.json', JSON.stringify(results, null, 2));
console.log('Saved docs/reference/evidence/G06-cases-semantics.json with ' + results.length + ' scenarios');
