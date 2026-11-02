import {clsx,type ClassValue} from 'clsx';
import {twMerge} from 'tailwind-merge';
export function cn(...inputs:ClassValue[]) {return twMerge(clsx(inputs));}
export function date(value:string|number) {return new Intl.DateTimeFormat('id-ID',{dateStyle:'medium'}).format(new Date(typeof value==='number'?value*1000:value));}
