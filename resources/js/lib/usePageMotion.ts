import {useEffect,useRef} from 'react';

/** Presentation only: never hide content while waiting for JS or an observer. */
export function usePageMotion(route:string) {
 const ref=useRef<HTMLElement>(null);
 useEffect(()=>{
  const root=ref.current;
  if(!root||typeof root.animate!=='function')return;
  const preference=window.matchMedia('(prefers-reduced-motion: reduce)');
  const animations=new Set<Animation>();
  let observer:IntersectionObserver|undefined;
  const stop=()=>{observer?.disconnect();animations.forEach(animation=>animation.cancel());animations.clear();};
  const play=(element:HTMLElement,delay=0)=>{
   const animation=element.animate([{opacity:0,translate:'0 14px'},{opacity:1,translate:'0 0'}],{duration:520,delay,easing:'cubic-bezier(.22,1,.36,1)',fill:'backwards'});
   animations.add(animation);
   animation.finished.then(()=>animations.delete(animation),()=>animations.delete(animation));
  };
  const start=()=>{
   stop();
   if(preference.matches)return;
   const page=root.animate([{opacity:.35},{opacity:1}],{duration:240,easing:'ease-out'});
   animations.add(page);page.finished.then(()=>animations.delete(page),()=>animations.delete(page));
   if(typeof IntersectionObserver==='undefined')return;
   const seen=new WeakSet<Element>();
   observer=new IntersectionObserver(entries=>{
    let order=0;
    for(const entry of entries){
     if(!entry.isIntersecting||seen.has(entry.target))continue;
     seen.add(entry.target);observer?.unobserve(entry.target);
     // Focused controls stay still and immediately readable.
     if(!entry.target.contains(document.activeElement))play(entry.target as HTMLElement,Math.min(order++,4)*45);
    }
   },{threshold:.08});
   // Observe independent surfaces rather than both a panel and its children.
   const surfaces=root.querySelectorAll<HTMLElement>('.benefit,.tool-card,.stat-card,.panel,.popular-panel,.join-banner,.home-discovery,.faq-section,.welcome-banner,.tool-detail-heading,.generator-panel,.tool-result-panel,.usage-panel,.popular-tools-panel');
   surfaces.forEach(element=>{
    if(!element.parentElement?.closest('.panel,.popular-panel,.join-banner'))observer?.observe(element);
   });
  };
  const onPreference=()=>{if(preference.matches)stop();};
  start();preference.addEventListener('change',onPreference);
  return()=>{stop();preference.removeEventListener('change',onPreference);};
 },[route]);
 return ref;
}
