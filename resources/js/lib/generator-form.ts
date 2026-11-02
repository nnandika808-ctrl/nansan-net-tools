import type {Field,FormSchema} from '@/types';

export function generatorFields(schema:FormSchema|null,values:Record<string,string|boolean>):Field[] {
  if(!schema)return [];
  if(schema.id!=='G04')return schema.fields;
  const count=Math.max(2,Math.min(10,Number(values.count)||2));
  const repeated:Field[]=[];
  for(let i=1;i<=count;i++) {
    repeated.push({key:'connection-'+(2*i-1),label:'Starlink '+i+' interface',type:'text',default:'ether'+i});
    if(values.mode==='manual')repeated.push({key:'connection-'+(2*i),label:'Starlink '+i+' ratio',type:'number',default:'1',min:1,max:256});
  }
  return [...schema.fields.filter(f=>!f.key.startsWith('connection-')),...repeated];
}

export function generatorDefaults(schema:FormSchema|null):Record<string,string|boolean> {
  const defaults=Object.fromEntries(schema?.fields.map(f=>[f.key,f.default])??[]);
  if(schema?.id==='G04')for(let i=1;i<=10;i++) {
    defaults['connection-'+(2*i-1)]='ether'+i;
    defaults['connection-'+(2*i)]='1';
  }
  return defaults;
}

export function changeGeneratorField(schema:FormSchema|null,values:Record<string,string|boolean>,key:string,value:string|boolean) {
  const next={...values,[key]:value};
  if(schema?.id==='G04'&&key==='count') {
    const previousAuto=Array.from({length:Number(values.count)||2},(_,i)=>'1.1.1.'+(i+1)).join(',');
    if(String(values.netwatchTargets).replaceAll(' ','')===previousAuto) {
      next.netwatchTargets=Array.from({length:Math.min(10,Number(value)||2)},(_,i)=>'1.1.1.'+(i+1)).join(',');
    }
  }
  return next;
}
