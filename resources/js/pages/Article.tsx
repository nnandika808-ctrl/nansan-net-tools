import {Link} from '@inertiajs/react';
import {SeoHead as Head} from '@/components/SeoHead';
import type {Article as ArticleType} from '@/types';
import {PageHeader} from '@/components/PageHeader';
import {usePreferences} from '@/lib/preferences';
export default function Article({article,html,htmlEn,related}:{article:ArticleType;html:string;htmlEn?:string|null;related:ArticleType[]}){
 const {t,locale}=usePreferences();const title=locale==='en'?(article.title_en||article.title):article.title;return <div className="container page-space article-container"><Head title={title}/><PageHeader eyebrow={t('docs')} title={title} description={locale==='en'?(article.excerpt_en||article.excerpt):article.excerpt}/>{article.owner_review_required&&<div className="notice">{locale==='id'?'Draf untuk ditinjau pemilik proyek. Konten ini belum dinyatakan memenuhi seluruh kewajiban hukum.':'Draft for project owner review. This content has not been confirmed to meet all legal obligations.'}</div>}<article className="prose panel" dangerouslySetInnerHTML={{__html:locale==='en'&&htmlEn?htmlEn:html}}/>{related.length>0&&<section className="related"><h2>{locale==='id'?'Bacaan terkait':'Related reading'}</h2>{related.map(a=><Link key={a.slug} href={'/docs/'+a.slug}>{a.title}</Link>)}</section>}</div>;
}
