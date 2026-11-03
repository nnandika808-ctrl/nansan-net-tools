import {describe,it,expect} from 'vitest';
import {generatorDefaults,generatorFields,changeGeneratorField} from './generator-form';
import schema from '../../generators/G04.json';
import games from '../../generators/G05.json';
import type {FormSchema} from '@/types';

describe('Starlink dynamic form',()=>{
 it('adds WAN fields and monitoring defaults while preserving custom targets',()=>{
  const form=schema as FormSchema;
  let values=generatorDefaults(form);
  values=changeGeneratorField(form,values,'count','3');
  expect(generatorFields(form,values).filter(f=>f.key.startsWith('connection-'))).toHaveLength(3);
  expect(values.netwatchTargets).toBe('1.1.1.1,1.1.1.2,1.1.1.3');
  values=changeGeneratorField(form,values,'mode','manual');
  expect(generatorFields(form,values).filter(f=>f.key.startsWith('connection-'))).toHaveLength(6);
  values.netwatchTargets='9.9.9.9,8.8.8.8,1.1.1.1';
  expect(changeGeneratorField(form,values,'count','4').netwatchTargets).toBe(values.netwatchTargets);
 });
});

it('filters game choices without dropping checked games hidden by search',()=>{
 const form=games as FormSchema;
 let values=generatorDefaults(form);
 values=changeGeneratorField(form,values,'ArenaOfValor-Mobile',true);
 values=changeGeneratorField(form,values,'myInput','NO_GAME_MATCH');
 expect(generatorFields(form,values).filter(field=>field.type==='checkbox')).toHaveLength(0);
 expect(values['ArenaOfValor-Mobile']).toBe(true);
 values=changeGeneratorField(form,values,'myInput','arena of valor');
 expect(generatorFields(form,values).some(field=>field.key==='ArenaOfValor-Mobile')).toBe(true);
 expect(values['ArenaOfValor-Mobile']).toBe(true);
});
