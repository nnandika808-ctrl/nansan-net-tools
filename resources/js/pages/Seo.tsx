import {Head,Link,useForm,router} from '@inertiajs/react';
import {useState} from 'react';
import {PageHeader} from '@/components/PageHeader';
import {Button} from '@/components/ui/button';
import {Field,Input} from '@/components/ui/form';
import {Dialog} from '@/components/ui/dialog';
import {usePreferences} from '@/lib/preferences';

type Metadata={id:number;path:string;title:string;description:string;noindex:boolean;og_image:string|null};
export default function Seo({records,paths,images}:{records:Metadata[];paths:string[];images:{id:number;original_name:string}[]}) {
 const {t}=usePreferences();const [remove,setRemove]=useState<Metadata|null>(null);
 const form=useForm({path:'/',title:'',description:'',noindex:false,image_id:''});
 return <><Head title="SEO"/><PageHeader title="SEO" eyebrow="Admin" action={<Button asChild variant="outline"><Link href="/admin">Admin</Link></Button>}/><div className="tool-details-grid"><section className="panel"><form onSubmit={e=>{e.preventDefault();form.post('/admin/seo',{preserveScroll:true});}}>
 <Field id="seo-path" label="URL" error={form.errors.path}><select className="input" id="seo-path" value={form.data.path} onChange={e=>{const row=records.find(r=>r.path===e.target.value);form.setData({path:e.target.value,title:row?.title??'',description:row?.description??'',noindex:!!row?.noindex,image_id:row?.og_image?.split('/').pop()??''});}}>{paths.map(path=><option key={path}>{path}</option>)}</select></Field>
 <Field id="seo-title" label={t('title')} error={form.errors.title}><Input id="seo-title" required maxLength={180} value={form.data.title} onChange={e=>form.setData('title',e.target.value)}/></Field>
 <Field id="seo-description" label="Description" error={form.errors.description}><textarea className="input" id="seo-description" required maxLength={500} value={form.data.description} onChange={e=>form.setData('description',e.target.value)}/></Field>
 <Field id="seo-image" label="Open Graph image" error={form.errors.image_id}><select className="input" id="seo-image" value={form.data.image_id} onChange={e=>form.setData('image_id',e.target.value)}><option value="">—</option>{images.map(i=><option key={i.id} value={i.id}>{i.original_name}</option>)}</select></Field>
 <label className="checkbox-label"><input type="checkbox" checked={form.data.noindex} onChange={e=>form.setData('noindex',e.target.checked)}/>Noindex</label><Button disabled={form.processing}>{t('save')}</Button></form><p className="muted">Staging selalu noindex. Halaman akun dan Admin tidak dapat diindeks.</p></section>
 <section className="panel">{records.map(row=><div className="record-row" key={row.id}><b>{row.path}</b><p>{row.title}</p><Button variant="ghost" onClick={()=>setRemove(row)}>{t('delete')}</Button></div>)}</section></div>
 <Dialog open={!!remove} onOpenChange={()=>setRemove(null)} title={t('delete')}><Button variant="destructive" onClick={()=>{if(remove)router.delete('/admin/seo/'+remove.id,{onSuccess:()=>setRemove(null)});}}>{t('delete')}</Button></Dialog></>;
}
