import {Head,Link} from '@inertiajs/react';
import {Wrench} from 'lucide-react';
import {usePreferences} from '@/lib/preferences';
import {date} from '@/lib/utils';
export default function Maintenance({message,until}:{message:string;until:string|null}){const {locale,t}=usePreferences();return <div className="container maintenance"><Head title="Maintenance"/><Wrench size={40}/><h1>{locale==='id'?'Sedang dalam pemeliharaan.':'Under maintenance.'}</h1><p>{message}</p>{until&&<small>{locale==='id'?'Estimasi: ':'Estimated: '}{date(until)}</small>}<Link href="/login">{t('login')}</Link></div>;}
