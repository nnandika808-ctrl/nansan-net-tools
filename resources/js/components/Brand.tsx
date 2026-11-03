import {useId} from 'react';

export function Brand({admin=false}:{admin?:boolean}) {
 const id=useId().replaceAll(':','');
 return <span className="brand-lockup"><svg viewBox="0 0 48 48" className="brand-symbol" aria-hidden="true"><defs><linearGradient id={id+'b'} x1="0" y1="0" x2="1" y2="1"><stop stopColor="#0067ff"/><stop offset="1" stopColor="#00d5ef"/></linearGradient><linearGradient id={id+'v'} x1="0" y1="0" x2="1" y2="1"><stop stopColor="#20d9ff"/><stop offset="1" stopColor="#8054ff"/></linearGradient></defs><path d="M8 36 15 10 36 25 40 12" fill="none" stroke={'url(#'+id+'b)'} strokeWidth="8" strokeLinecap="round" strokeLinejoin="round"/><path d="m11 23 22 15 4-14" fill="none" stroke={'url(#'+id+'v)'} strokeWidth="8" strokeLinecap="round" strokeLinejoin="round"/><path d="m13 20 23 15" stroke="white" strokeWidth="3.5" strokeLinecap="round"/></svg><span className="brand-wordmark">NANSAN NET<small>{admin?'Admin':'Tools'}</small></span></span>;
}
