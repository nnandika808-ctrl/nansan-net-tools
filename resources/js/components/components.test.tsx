import {render,screen,fireEvent,waitFor} from '@testing-library/react';
import {describe,it,expect,vi} from 'vitest';
import {ToolCard} from './ToolCard';
import {Search} from './Search';
import {ScriptOutput} from './ScriptOutput';
import {Preferences,usePreferences} from '@/lib/preferences';
import type {Tool} from '@/types';
vi.mock('@inertiajs/react',()=>({Link:({href,children,...props}:any)=><a href={href} {...props}>{children}</a>,router:{patch:vi.fn()}}));
describe('generator presentation',()=>{
 it('shows the unavailable status and links to the correct tool',()=>{
  const tool={id:1,generator_id:'G01',name:'Static Routing',slug:'static-routing',description:'Routing description',icon:'route',status:'BLOCKED',ros_versions:['6','7'],category:{id:1,name:'Routing',slug:'routing'}} as Tool;
  render(<ToolCard tool={tool}/>);expect(screen.getByRole('link')).toHaveAttribute('href','/tools/static-routing');expect(screen.getByText('Menunggu verifikasi')).toBeInTheDocument();
 });
 it('renders script as text without executing embedded markup and copies the original text',async()=>{
  const code='<img src=x onerror="alert(1)">';render(<ScriptOutput steps={[{title:'Test fixture',script:code}]} version="test" rosVersion="7"/>);
  expect(document.querySelector('img')).toBeNull();fireEvent.click(screen.getByRole('button',{name:'Salin script'}));await waitFor(()=>expect(navigator.clipboard.writeText).toHaveBeenCalledWith(code));
 });
 it('switches output steps and copies the selected original script',async()=>{
  render(<ScriptOutput steps={[{title:'First fixture',script:'first fixture text'},{title:'Second fixture',script:'second fixture text'}]} version="test" rosVersion="7"/>);
  fireEvent.click(screen.getByRole('tab',{name:'STEP 2'}));
  expect(screen.getByRole('tabpanel',{name:'STEP 2'})).toBeVisible();
  fireEvent.click(screen.getByRole('button',{name:'Salin script'}));
  await waitFor(()=>expect(navigator.clipboard.writeText).toHaveBeenCalledWith('second fixture text'));
 });
});
describe('search',()=>{
 it('debounces requests and displays a synonym result',async()=>{
  const fetchMock=vi.spyOn(globalThis,'fetch').mockResolvedValue({ok:true,json:async()=>({tools:[{name:'PCC Load Balancing',slug:'pcc'}],articles:[]})} as Response);
  render(<Search/>);fireEvent.change(screen.getByRole('textbox'),{target:{value:'Load Balance'}});expect(fetchMock).not.toHaveBeenCalled();
  expect(await screen.findByRole('link',{name:'PCC Load Balancing'})).toHaveAttribute('href','/tools/pcc');expect(fetchMock).toHaveBeenCalledTimes(1);
 });
 it('shows empty results without fabricated matches',async()=>{
  vi.spyOn(globalThis,'fetch').mockResolvedValue({ok:true,json:async()=>({tools:[],articles:[]})} as Response);render(<Search/>);fireEvent.change(screen.getByRole('textbox'),{target:{value:'missing'}});expect(await screen.findByText('Tidak ada hasil yang cocok. Coba kata kunci lain.')).toBeInTheDocument();
 });
});
it('persists guest theme and language preferences',async()=>{
 function Switch(){const {setTheme,setLocale,locale}=usePreferences();return <button onClick={()=>{setTheme('light');setLocale('en');}}>{locale}</button>;}
 render(<Preferences user={null}><Switch/></Preferences>);fireEvent.click(screen.getByRole('button'));await waitFor(()=>expect(document.documentElement.dataset.theme).toBe('light'));expect(localStorage.getItem('nansan-locale')).toBe('en');
});
it('uses the reference light theme for a new visitor',async()=>{
 localStorage.removeItem('nansan-theme');
 render(<Preferences user={null}><span>Reference theme</span></Preferences>);
 await waitFor(()=>expect(document.documentElement.dataset.theme).toBe('light'));
});
