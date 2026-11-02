import '../css/app.css';
import {createRoot} from 'react-dom/client';
import {createInertiaApp} from '@inertiajs/react';
import type {ReactNode,ComponentType} from 'react';
import {Layout} from '@/components/Layout';
const pages=import.meta.glob('./pages/**/*.tsx');
createInertiaApp({
 title:title=>title?`${title} — NANSAN NET Tools`:'NANSAN NET Tools',
 resolve:async name=>{const module=await pages[`./pages/${name}.tsx`]() as {default:ComponentType & {layout?:(page:ReactNode)=>ReactNode}};module.default.layout=page=><Layout>{page}</Layout>;return module.default;},
 setup({el,App,props}){createRoot(el).render(<App {...props}/>);},
 progress:{color:'#25c4e8'},
});
