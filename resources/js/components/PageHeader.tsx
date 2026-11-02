import {Link} from '@inertiajs/react';
import {ChevronRight} from 'lucide-react';
import type {ReactNode} from 'react';
export function PageHeader({eyebrow,title,description,action}:{eyebrow?:string;title:string;description?:string;action?:ReactNode}) {return <div className="page-header"><div><div className="breadcrumbs"><Link href="/">NANSAN NET</Link><ChevronRight size={12}/>{eyebrow??'Workspace'}</div><h1>{title}</h1>{description&&<p>{description}</p>}</div>{action}</div>;}
