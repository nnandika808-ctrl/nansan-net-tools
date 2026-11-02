import {Head,Link,router} from '@inertiajs/react';
import {PageHeader} from '@/components/PageHeader';
import {Button} from '@/components/ui/button';
import {usePreferences} from '@/lib/preferences';
import {date} from '@/lib/utils';
type Member={id:number;username:string;name:string|null;role:string;status:string;locale:string;theme:string;created_at:string;last_login_at:string|null};
type Event={id:number;action:string;created_at:string};

export default function AdminUser({member,counts,activity,audit}:{member:Member;counts:Record<string,number>;activity:Event[];audit:Event[]}) {
 const {t,locale}=usePreferences();return <><Head title={member.username}/><PageHeader title={member.username} eyebrow="Admin / Users" action={<Button asChild variant="outline"><Link href="/admin/users">{t('users')}</Link></Button>}/><div className="tool-details-grid"><section className="panel"><h2>{member.name??member.username}</h2><p>{member.role} · {member.status}</p><p>{member.locale} / {member.theme}</p><p>{locale==='id'?'Dibuat':'Created'}: {date(member.created_at)}</p><p>Login: {member.last_login_at?date(member.last_login_at):'—'}</p>{member.role!=='admin'&&member.status!=='deletion_pending'&&<Button variant="outline" onClick={()=>router.patch('/admin/users/'+member.id,{status:member.status==='active'?'suspended':'active'})}>{member.status==='active'?'Suspend':'Activate'}</Button>}</section><section className="panel">{Object.entries(counts).map(([key,count])=><p key={key}>{t(key)}: <b>{count}</b></p>)}<p className="muted">{locale==='id'?'Isi riwayat, preset, password, dan kode pemulihan tetap pribadi.':'History, preset contents, passwords and recovery codes stay private.'}</p></section></div><div className="tool-details-grid">{[[t('activity'),activity],['Audit',audit]].map(([label,events])=><section className="panel" key={label as string}><h2>{label as string}</h2>{(events as Event[]).map(event=><p key={event.id}><b>{event.action}</b> · {date(event.created_at)}</p>)}</section>)}</div></>;
}
