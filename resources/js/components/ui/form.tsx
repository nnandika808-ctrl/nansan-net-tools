import type {InputHTMLAttributes,ReactNode} from 'react';
export function Field({label,error,id,children,hint}:{label:string;error?:string;id:string;children:ReactNode;hint?:string}) {
 return <div className="field"><label htmlFor={id}>{label}</label>{children}{hint&&<small id={id+'-hint'}>{hint}</small>}{error&&<span className="field-error" id={id+'-error'} role="alert">{error}</span>}</div>;
}
export function Input({className='',...props}:InputHTMLAttributes<HTMLInputElement>) {return <input className={'input '+className} {...props}/>;}
