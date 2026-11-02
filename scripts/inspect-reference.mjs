import {call} from './reference-audit.mjs';
const result = await call('browser_evaluate', {function:`() => ({containers:[...document.querySelectorAll('pre,code,[id*=result],[id*=script],[id*=output],.code-container')].map(e=>({tag:e.tagName,id:e.id,class:e.className,length:e.innerText?.length})),forms:[...document.querySelectorAll('form')].map(e=>({id:e.id})),labels:[...document.querySelectorAll('label')].slice(0,6).map(e=>e.innerText)})`});
console.log(JSON.stringify(result));
