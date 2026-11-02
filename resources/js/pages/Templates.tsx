import {Head,Link,useForm,router} from '@inertiajs/react';
import {useState} from 'react';
import type {Tool,FormSchema} from '@/types';
import {GeneratorFields} from '@/components/GeneratorFields';
import {PageHeader} from '@/components/PageHeader';
import {Button} from '@/components/ui/button';
import {Field,Input} from '@/components/ui/form';
import {Dialog} from '@/components/ui/dialog';
import {generatorDefaults,changeGeneratorField} from '@/lib/generator-form';
import {usePreferences} from '@/lib/preferences';
type Template={id:number;name:string;defaults:Record<string,string|boolean>;version:string;is_active:boolean};

export default function Templates({tool,schema,version,templates}:{tool:Tool;schema:FormSchema;version:string;templates:Template[]}) {
 const {t,locale}=usePreferences();const [editor,setEditor]=useState<Template|null|undefined>();const [remove,setRemove]=useState<Template|null>(null);
 return <><Head title={'Templates / '+tool.name}/><PageHeader title={tool.name+' / Templates'} eyebrow={'Admin · '+version} action={<Button asChild variant="outline"><Link href="/admin/tools">{t('tools')}</Link></Button>}/><p className="notice">{locale==='id'?'Template berisi nilai parameter tervalidasi. Aktivasi hanya menampilkan pilihan template; tidak mempublikasikan generator.':'Templates contain validated parameters. Activation exposes a template choice and does not publish the generator.'}</p><Button onClick={()=>setEditor(null)}>{t('new')}</Button><section className="panel">{templates.map(template=><div className="record-row" key={template.id}><b>{template.name}</b><p>v{template.version} · {template.is_active?'Active':'Draft'}</p><Button variant="outline" onClick={()=>setEditor(template)}>{t('edit')}</Button><Button variant="ghost" onClick={()=>setRemove(template)}>{t('delete')}</Button></div>)}</section>
 {editor!==undefined&&<TemplateEditor key={editor?.id??'new'} tool={tool} schema={schema} template={editor} close={()=>setEditor(undefined)}/>}
 <Dialog open={!!remove} onOpenChange={()=>setRemove(null)} title={t('delete')}><Button variant="destructive" onClick={()=>{if(remove)router.delete('/admin/tools/'+tool.slug+'/templates/'+remove.id,{onSuccess:()=>setRemove(null)});}}>{t('delete')}</Button></Dialog></>;
}
function TemplateEditor({tool,schema,template,close}:{tool:Tool;schema:FormSchema;template:Template|null;close:()=>void}) {
 const {t}=usePreferences();const form=useForm({name:template?.name??'',is_active:template?.is_active??false,input:{...generatorDefaults(schema),...template?.defaults}});
 return <Dialog open onOpenChange={close} title={t(template?'edit':'new')}><form onSubmit={e=>{e.preventDefault();const path='/admin/tools/'+tool.slug+'/templates';const options={preserveScroll:true,onSuccess:close};if(template)form.put(path+'/'+template.id,options);else form.post(path,options);}}><Field id="template-name" label={t('name')} error={form.errors.name}><Input id="template-name" required maxLength={100} value={form.data.name} onChange={e=>form.setData('name',e.target.value)}/></Field><label className="checkbox-label"><input type="checkbox" checked={form.data.is_active} onChange={e=>form.setData('is_active',e.target.checked)}/>Active</label><GeneratorFields schema={schema} values={form.data.input} onChange={(key,value)=>form.setData('input',changeGeneratorField(schema,form.data.input,key,value))} errors={form.errors}/><Button disabled={form.processing}>{t('save')}</Button></form></Dialog>;
}
