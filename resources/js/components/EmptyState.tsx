import {Inbox} from 'lucide-react';
export function EmptyState({title,description}:{title:string;description?:string}) {return <div className="empty-state"><Inbox size={32}/><h3>{title}</h3>{description&&<p>{description}</p>}</div>;}
