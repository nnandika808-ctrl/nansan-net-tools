import {Head,Link,useForm} from '@inertiajs/react';
import {PageHeader} from '@/components/PageHeader';
import {Button} from '@/components/ui/button';
import {Field,Input} from '@/components/ui/form';
import {usePreferences} from '@/lib/preferences';
type Item={locale:string;question:string;answer:string};

export default function Faq({items}:{items:Item[]}) {
 const {t,locale}=usePreferences();const form=useForm({items});
 const update=(index:number,key:keyof Item,value:string)=>form.setData('items',form.data.items.map((item,i)=>i===index?{...item,[key]:value}:item));
 return <><Head title="FAQ"/><PageHeader title="FAQ" eyebrow="Admin" action={<Button asChild variant="outline"><Link href="/admin">Admin</Link></Button>}/><form className="narrow-panel" onSubmit={e=>{e.preventDefault();form.put('/admin/faq',{preserveScroll:true});}}>
 {form.data.items.map((item,index)=><section className="panel" key={index}><Field id={'faq-locale-'+index} label={t('language')}><select className="input" id={'faq-locale-'+index} value={item.locale} onChange={e=>update(index,'locale',e.target.value)}><option value="id">Indonesia</option><option value="en">English</option></select></Field>
 <Field id={'faq-question-'+index} label={locale==='id'?'Pertanyaan':'Question'} error={(form.errors as Record<string,string>)['items.'+index+'.question']}><Input id={'faq-question-'+index} required maxLength={180} value={item.question} onChange={e=>update(index,'question',e.target.value)}/></Field>
 <Field id={'faq-answer-'+index} label={locale==='id'?'Jawaban':'Answer'} error={(form.errors as Record<string,string>)['items.'+index+'.answer']}><textarea className="input" id={'faq-answer-'+index} required maxLength={1200} value={item.answer} onChange={e=>update(index,'answer',e.target.value)}/></Field><Button variant="ghost" type="button" onClick={()=>form.setData('items',form.data.items.filter((_,i)=>i!==index))}>{t('delete')}</Button></section>)}
 <div className="dialog-actions"><Button variant="outline" type="button" disabled={form.data.items.length>=24} onClick={()=>form.setData('items',[...form.data.items,{locale,question:'',answer:''}])}>{t('new')}</Button><Button disabled={form.processing}>{t('save')}</Button></div><p className="field-error">{form.errors.items}</p></form></>;
}
