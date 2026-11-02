import {useState} from 'react';
import {useForm} from '@inertiajs/react';
import {Button} from './ui/button';
import {Dialog} from './ui/dialog';
import {Field,Input} from './ui/form';
import {usePreferences} from '@/lib/preferences';

export function PresetControls({slug,values,saveHistory,onSaveHistory}:{slug:string;values:Record<string,string|boolean>;saveHistory:boolean;onSaveHistory:(value:boolean)=>void}) {
 const {t,locale}=usePreferences();const [open,setOpen]=useState(false);const form=useForm({name:'',input:values});
 return <><label className="checkbox-label"><input type="checkbox" checked={saveHistory} onChange={e=>onSaveHistory(e.target.checked)}/>{locale==='id'?'Simpan hasil ke riwayat':'Save output to history'}</label><Button variant="outline" type="button" onClick={()=>{form.setData('input',values);setOpen(true);}}>{locale==='id'?'Simpan preset':'Save preset'}</Button>
 <Dialog open={open} onOpenChange={setOpen} title={t('presets')}><form onSubmit={e=>{e.preventDefault();form.post('/tools/'+slug+'/presets',{preserveScroll:true,onSuccess:()=>setOpen(false)});}}><Field id="preset-name" label={t('name')} error={form.errors.name}><Input id="preset-name" required maxLength={100} value={form.data.name} onChange={e=>form.setData('name',e.target.value)}/></Field><p className="field-error">{form.errors.input}</p><Button disabled={form.processing}>{t('save')}</Button></form></Dialog></>;
}
