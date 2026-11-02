import {Slot} from '@radix-ui/react-slot';
import {cva,type VariantProps} from 'class-variance-authority';
import type {ButtonHTMLAttributes} from 'react';
import {cn} from '@/lib/utils';
const variants=cva('btn',{variants:{variant:{default:'btn-primary',outline:'btn-outline',ghost:'btn-ghost',destructive:'btn-danger'},size:{default:'',sm:'btn-sm',icon:'btn-icon'}},defaultVariants:{variant:'default',size:'default'}});
export function Button({className,variant,size,asChild=false,...props}:ButtonHTMLAttributes<HTMLButtonElement>&VariantProps<typeof variants>&{asChild?:boolean}) {
 const Comp=asChild?Slot:'button';return <Comp className={cn(variants({variant,size}),className)} {...props}/>;
}
