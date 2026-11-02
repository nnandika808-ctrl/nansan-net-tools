import {Link} from '@inertiajs/react';
import {ArrowUpRight,Route,Layers,Network,Satellite,Gamepad2} from 'lucide-react';
import type {Tool} from '@/types';
import {usePreferences} from '@/lib/preferences';
const icons={route:Route,queue:Layers,pcc:Network,starlink:Satellite,games:Gamepad2};
export function ToolCard({tool}:{tool:Tool}) {
 const {t,locale}=usePreferences();const Icon=icons[tool.icon as keyof typeof icons]??Network;
 return <Link href={'/tools/'+tool.slug} className="tool-card"><div className="tool-card-top"><span className={'tool-icon '+tool.icon}><Icon size={24}/></span><span className="badge badge-muted">{t('blocked')}</span></div><div className="tool-category">{tool.category?.name??'MikroTik'} <span>/{tool.generator_id}</span></div><h3>{tool.name}<ArrowUpRight size={18}/></h3><p>{locale==='en'?(tool.description_en||tool.description):tool.description}</p><div className="tool-card-bottom"><div>{tool.ros_versions.map(v=><span className="version" key={v}>ROS {v}</span>)}</div><span>{t('free')}</span></div></Link>;
}
