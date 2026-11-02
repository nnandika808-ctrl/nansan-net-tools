import {useState} from 'react';
import {Copy,Download,Check,Terminal} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {usePreferences} from '@/lib/preferences';
export function ScriptOutput({steps,version,rosVersion,warnings=[]}:{steps:{title:string;script:string}[];version:string;rosVersion:string;warnings?:string[]}){
 const {t,locale}=usePreferences();const [copied,setCopied]=useState(-1);
 const download=(script:string)=>{const url=URL.createObjectURL(new Blob([script],{type:'text/plain;charset=utf-8'}));const a=document.createElement('a');a.href=url;a.download='nansan-net.rsc';a.click();URL.revokeObjectURL(url);};
 const highlight=(line:string)=>{if(line.trim().startsWith('#'))return <span className="code-comment">{line}</span>;return line.split(/("(?:\\.|[^"\\])*"|\/[a-z][a-z -]+|[a-z-]+=)/g).map((part,i)=><span key={i} className={part.startsWith('"')?'code-string':part.startsWith('/')?'code-path':part.endsWith('=')?'code-key':''}>{part}</span>);};
 return <section className="output"><div className="section-top"><h2><Terminal size={18}/>Output</h2><span className="badge">ROS {rosVersion} · v{version}</span></div><div className="notice"><div>{locale==='id'?'Tinjau konfigurasi dan uji di lab sebelum digunakan.':'Review and lab-test the configuration before use.'}{warnings.map(w=><p key={w}>{w}</p>)}</div></div>{steps.map((step,index)=><div className="output-step" key={index}><div><b>{step.title}</b><span><Button variant="ghost" size="sm" onClick={async()=>{await navigator.clipboard.writeText(step.script);setCopied(index);}}>{copied===index?<Check size={15}/>:<Copy size={15}/>} {t('copy')}</Button><Button variant="ghost" size="sm" onClick={()=>download(step.script)}><Download size={15}/>{t('download')}</Button></span></div><pre><code>{step.script.split('\n').map((line,i)=><span className="code-line" key={i}><span className="line-number">{i+1}</span>{highlight(line)}</span>)}</code></pre></div>)}</section>;
}
