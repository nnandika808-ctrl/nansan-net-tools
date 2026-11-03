import {fireEvent,render,screen} from '@testing-library/react';
import {expect,it,vi} from 'vitest';
import {GeneratorFields} from './GeneratorFields';
import type {FormSchema,GamePorts} from '@/types';
import observed from '../../generators/G05.json';
import {generatorDefaults} from '@/lib/generator-form';

const schema=observed as FormSchema;
const gamePorts:GamePorts[]=[{id:'ArenaOfValor-Mobile',label:'Arena Of Valor',status:'PARAMETERS_OBSERVED',ports:[{protocol:'tcp',value:'10001-10094'},{protocol:'udp',value:'10101-10201,10080-10110,17000-18000'}]}];

it('shows TCP and UDP ports next to the game choice and preserves its checkbox action',()=>{
 const onChange=vi.fn();
 render(<GeneratorFields schema={schema} values={generatorDefaults(schema)} onChange={onChange} gamePorts={gamePorts}/>);
 expect(screen.getByText('10001-10094')).toBeVisible();
 expect(screen.getByText('10101-10201,10080-10110,17000-18000')).toBeVisible();
 expect(screen.getAllByRole('checkbox')).toHaveLength(106);
 fireEvent.click(screen.getByRole('checkbox',{name:'Arena Of Valor (AOV) - Mobile'}));
 expect(onChange).toHaveBeenCalledWith('ArenaOfValor-Mobile',true);
});

it('shows the source failure with the known TCP ports when searching for a malformed game',()=>{
 const failed:FormSchema={...schema,field_statuses:{'Hearthstone-PC':{status:'FAILED',message:'Unbalanced quote'}}};
 render(<GeneratorFields schema={failed} values={{...generatorDefaults(schema),myInput:'Hearthstone'}} onChange={()=>{}} gamePorts={[{id:'Hearthstone-PC',label:'Hearthstone',status:'FAILED_REFERENCE_SYNTAX',ports:[{protocol:'tcp',value:'1119,3724'}]}]}/>);
 expect(screen.getAllByRole('checkbox')).toHaveLength(1);
 expect(screen.getByText('1119,3724')).toBeVisible();
 expect(screen.getByText(/FAILED/)).toBeVisible();
 expect(screen.queryByText('1119,3724 protocol=udp')).not.toBeInTheDocument();
});
