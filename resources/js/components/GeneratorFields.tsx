import type {FormSchema,GamePorts,Field as GeneratorField} from '@/types';
import {generatorFields} from '@/lib/generator-form';
import {Field,Input} from './ui/form';

export function GeneratorFields({schema,values,onChange,errors={},gamePorts=[],locale='id'}:{schema:FormSchema|null;values:Record<string,string|boolean>;onChange:(key:string,value:string|boolean)=>void;errors?:Record<string,string|string[]>;gamePorts?:GamePorts[];locale?:'id'|'en'}) {
 const fields=generatorFields(schema,values);const isGames=schema?.id==='G05';
 const renderField=(field:GeneratorField)=>{
  if(field.conditional&&!values[field.conditional])return null;
  const message=errors[field.key];const error=Array.isArray(message)?message[0]:message;
  if(field.type==='checkbox'){
   const game=gamePorts.find(entry=>entry.id===field.key);
   return <label className={'checkbox-label schema-checkbox'+(isGames?' game-option':'')} key={field.key}><input id={'field-'+field.key} aria-label={field.label} type="checkbox" checked={!!values[field.key]} onChange={e=>onChange(field.key,e.target.checked)}/><span><span>{field.label}</span>{game&&<span className="game-port-values">{game.ports.map((port,index)=><span key={index}><b>{port.protocol.toUpperCase()}</b> <code>{port.value}</code></span>)}</span>}{schema?.field_statuses?.[field.key]&&<span className="field-error" title={schema.field_statuses[field.key].message}>{schema.field_statuses[field.key].status}{game?' · '+(locale==='id'?'Baris port referensi tidak valid':'Invalid reference port line'):''}</span>}{error&&<span className="field-error">{error}</span>}</span></label>;
  }
  return <Field key={field.key} id={'field-'+field.key} label={field.label} error={error}>{field.type==='select'?<select id={'field-'+field.key} className="input" value={String(values[field.key]??'')} onChange={e=>onChange(field.key,e.target.value)}>{field.options?.map((option,i)=><option key={option.value+i} value={option.value} disabled={option.disabled}>{option.label}</option>)}</select>:<Input id={'field-'+field.key} type={field.type==='number'?'number':'text'} min={field.min} max={schema?.id==='G03'&&field.key==='num-wan-lines'?2:field.max} placeholder={field.placeholder} value={String(values[field.key]??'')} onChange={e=>onChange(field.key,e.target.value)}/>}</Field>;
 };
 return <div className={'dynamic-fields'+(isGames?' dynamic-fields-games':'')}>{fields.filter(field=>!isGames||field.type!=='checkbox').map(renderField)}{isGames&&<fieldset className="game-selection"><legend>{locale==='id'?'Daftar game dan port':'Games and ports'}</legend><p className="muted">{fields.filter(field=>field.type==='checkbox').length} / {schema.fields.filter(field=>field.type==='checkbox').length} {locale==='id'?'game ditampilkan. Centang game untuk memasukkan portnya ke script.':'games shown. Check a game to include its ports in the script.'}</p><div className="game-options">{fields.filter(field=>field.type==='checkbox').map(renderField)}</div>{!fields.some(field=>field.type==='checkbox')&&<p className="muted">{locale==='id'?'Tidak ada game yang cocok dengan pencarian.':'No games match the search.'}</p>}</fieldset>}</div>;
}
