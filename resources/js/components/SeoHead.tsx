import {Head,usePage} from '@inertiajs/react';
import type {Shared} from '@/types';

export function SeoHead({title}:{title:string}) {
 const {seo}=usePage<Shared>().props;
 const resolved=seo.customized?seo.title:title;
 return <Head title={resolved}>
  <meta head-key="description" name="description" content={seo.description}/>
  <meta head-key="robots" name="robots" content={seo.indexable?'index,follow':'noindex,nofollow'}/>
  <link head-key="canonical" rel="canonical" href={seo.canonical}/>
  <meta head-key="og:title" property="og:title" content={resolved}/>
  <meta head-key="og:description" property="og:description" content={seo.description}/>
  <meta head-key="og:url" property="og:url" content={seo.canonical}/>
  {seo.image&&<meta head-key="og:image" property="og:image" content={seo.image}/>}
 </Head>;
}
