import {Link} from '@inertiajs/react';
import {ArrowRight,Route,Layers,Network,Satellite,Gamepad2} from 'lucide-react';
import type {Tool} from '@/types';
import {usePreferences} from '@/lib/preferences';
const icons={route:Route,queue:Layers,pcc:Network,starlink:Satellite,games:Gamepad2};
export function ToolCard({tool,compact=false}:{tool:Tool;compact?:boolean}) {
 const {t,locale}=usePreferences();const Icon=icons[tool.icon as keyof typeof icons]??Network;
 const name=compact&&tool.generator_id==='G04'?'PCC Starlink':compact&&tool.generator_id==='G05'?'Routing Games':tool.name;
 return <Link href={'/tools/'+tool.slug} className={'tool-card'+(compact?' tool-card-compact':'')}><div className="tool-card-top"><span className={'tool-icon '+tool.icon}><Icon size={compact?38:30}/></span><span className="badge badge-muted">{t('blocked')}</span></div><div className="tool-category">{tool.category?.name??'MikroTik'} <span>/{tool.generator_id}</span></div><h3>{name}</h3><p>{locale==='en'?(tool.description_en||tool.description):tool.description}</p><div className="tool-card-bottom"><span className="version">RouterOS {tool.ros_versions.join(' / ')}</span><span className="card-arrow"><ArrowRight size={17}/></span></div></Link>;
}
