import {Link,router} from '@inertiajs/react';
import {SeoHead as Head} from '@/components/SeoHead';
import {useState} from 'react';
import {BookOpen,ArrowUpRight,Search} from 'lucide-react';
import type {Article,Pagination as Pages} from '@/types';
import {PageHeader} from '@/components/PageHeader';
import {Pagination} from '@/components/ui/pagination';
import {EmptyState} from '@/components/EmptyState';
import {usePreferences} from '@/lib/preferences';
export default function Documentation({articles,q}:{articles:Pages<Article>;q:string}){
 const {t,locale}=usePreferences();const [query,setQuery]=useState(q);
 return <div className="container page-space"><Head title={t('docs')}/><PageHeader eyebrow={t('docs')} title={locale==='id'?'Pengetahuan yang menghubungkan.':'Knowledge that connects.'} description={locale==='id'?'Panduan penggunaan, keamanan, dan proses verifikasi konfigurasi.':'Guides to using tools, staying secure, and verifying configurations.'}/><form className="catalog-search" onSubmit={e=>{e.preventDefault();router.get('/docs',{q:query},{preserveState:true});}}><Search size={20}/><input aria-label={t('search')} placeholder={t('search')} value={query} onChange={e=>setQuery(e.target.value)}/><button type="submit">{locale==='id'?'Cari':'Search'}</button></form><div className="docs-grid">{articles.data.map(a=><Link key={a.id} className="panel docs-card" href={'/docs/'+a.slug}><span className="tool-icon"><BookOpen size={23}/></span><h2>{a.title}<ArrowUpRight size={17}/></h2><p>{a.excerpt}</p><span className="text-link">{locale==='id'?'Baca panduan':'Read guide'}<ArrowUpRight size={14}/></span></Link>)}</div>{!articles.data.length&&<EmptyState title={t('noResults')}/>}<Pagination links={articles.links}/></div>;
}
