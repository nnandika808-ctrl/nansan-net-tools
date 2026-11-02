import {Link,router} from '@inertiajs/react';
import {SeoHead as Head} from '@/components/SeoHead';
import {Search,SlidersHorizontal} from 'lucide-react';
import {useState} from 'react';
import {ToolCard} from '@/components/ToolCard';
import {EmptyState} from '@/components/EmptyState';
import {PageHeader} from '@/components/PageHeader';
import {Pagination} from '@/components/ui/pagination';
import {usePreferences} from '@/lib/preferences';
import type {Tool,Category,Pagination as Pages} from '@/types';
export default function Catalog({tools,categories,filters}:{tools:Pages<Tool>;categories:Category[];filters:{q:string;category:string}}){
 const {t,locale}=usePreferences();const [q,setQ]=useState(filters.q);
 return <div className="container page-space"><Head title={t('tools')}/><PageHeader eyebrow={t('tools')} title={locale==='id'?'Toolbox jaringan Anda.':'Your network toolbox.'} description={locale==='id'?'Lima generator prioritas, satu ruang kerja. Semua gratis.':'Five priority generators, one workspace. All free.'}/><form className="catalog-search" onSubmit={e=>{e.preventDefault();router.get('/tools',{q,category:filters.category},{preserveState:true});}}><Search size={20}/><input aria-label={t('search')} placeholder={t('searchHint')} value={q} onChange={e=>setQ(e.target.value)}/><button type="submit">{locale==='id'?'Cari':'Search'}<SlidersHorizontal size={15}/></button></form><div className="category-pills" id="categories"><Link href="/tools" className={!filters.category?'active':''}>{t('all')}</Link>{categories.map(c=><Link href={'/tools?category='+c.slug+'&q='+encodeURIComponent(filters.q)} key={c.id} className={filters.category===c.slug?'active':''}>{c.name}</Link>)}</div><div className="catalog-summary"><span>{tools.total} tools</span><span>{t('free')} · RouterOS 6 & 7</span></div>{tools.data.length?<div className="tools-grid">{tools.data.map(tool=><ToolCard key={tool.id} tool={tool}/>)}</div>:<EmptyState title={t('noResults')}/>}<Pagination links={tools.links}/></div>;
}
