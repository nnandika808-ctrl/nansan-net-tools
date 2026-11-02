import {test} from 'node:test';
import assert from 'node:assert/strict';
import {hasVisibleSecrets} from '../../scripts/browser-privacy.mjs';

function page({visible=true,style={visibility:'visible',display:'block',opacity:'1'}}={}){
 const input={getClientRects:()=>visible?[{}]:[]};Object.defineProperty(input,'value',{get(){throw new Error('Secret value must never be read.');}});
 return {querySelectorAll:()=>[input],defaultView:{getComputedStyle:()=>style}};
}
test('visible authentication inputs block inspection without reading their values',()=>assert.equal(hasVisibleSecrets(page()),true));
test('hidden authentication controls do not block ordinary reference pages',()=>assert.equal(hasVisibleSecrets(page({visible:false})),false));
test('ignores CSS-hidden login controls without inspecting their values',()=>assert.equal(hasVisibleSecrets(page({style:{visibility:'hidden',display:'none',opacity:'0'}})),false));
