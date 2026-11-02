import type {FormSchema} from '@/types';
import {generatorFields} from '@/lib/generator-form';
import {Field,Input} from './ui/form';

export function GeneratorFields({schema,values,onChange,errors={}}:{schema:FormSchema|null;values:Record<string,string|boolean>;onChange:(key:string,value:string|boolean)=>void;errors?:Record<string,string|string[]>}) {
 return <div className="dynamic-fields">{generatorFields(schema,values).map(field=>{
  if(field.conditional&&!values[field.conditional])return null;
  const message=errors[field.key];const error=Array.isArray(message)?message[0]:message;
  if(field.type==='checkbox')return <label className="checkbox-label schema-checkbox" key={field.key}><input type="checkbox" checked={!!values[field.key]} onChange={e=>onChange(field.key,e.target.checked)}/>{field.label}{error&&<span className="field-error">{error}</span>}</label>;
  return <Field key={field.key} id={'field-'+field.key} label={field.label} error={error}>{field.type==='select'?<select id={'field-'+field.key} className="input" value={String(values[field.key]??'')} onChange={e=>onChange(field.key,e.target.value)}>{field.options?.map((option,i)=><option key={option.value+i} value={option.value} disabled={option.disabled}>{option.label}</option>)}</select>:<Input id={'field-'+field.key} type={field.type==='number'?'number':'text'} min={field.min} max={schema?.id==='G03'&&field.key==='num-wan-lines'?2:field.max} placeholder={field.placeholder} value={String(values[field.key]??'')} onChange={e=>onChange(field.key,e.target.value)}/>}</Field>;
 })}</div>;
}
