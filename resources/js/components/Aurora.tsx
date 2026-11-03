import {useId} from 'react';

export function Aurora({className=''}:{className?:string}) {
 const id=useId().replaceAll(':','');
 return <svg className={'aurora '+className} viewBox="0 0 1440 800" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id={id} x1="0" y1="0" x2="1" y2=".6"><stop stopColor="#8ff0ff" stopOpacity=".6"/><stop offset=".42" stopColor="#009dff" stopOpacity=".5"/><stop offset="1" stopColor="#a885ff" stopOpacity=".55"/></linearGradient><linearGradient id={id+'s'} x1="0" x2="1"><stop stopColor="#b8f7ff" stopOpacity="0"/><stop offset="1" stopColor="#8fa3ff" stopOpacity=".22"/></linearGradient></defs><path d="M520 800C680 520 760 590 990 240S1340 20 1540-80V800Z" fill={'url(#'+id+'s)'}/><path d="M-180 790C210 530 430 930 770 580S1150 90 1520 40V800Z" fill={'url(#'+id+')'} opacity=".4"/><path d="M440 840C820 380 790 530 1110 310S1380 170 1530 80V800Z" fill={'url(#'+id+')'} opacity=".35"/><path d="M600 850C890 550 1090 690 1500 370" fill="none" stroke="white" strokeWidth="2" opacity=".7"/></svg>;
}
