import type { LucideIcon } from 'lucide-react';
import { useId, useState } from 'react';
import { cn } from '@/lib/utils';

type Option<T extends string> = {
    value: T;
    label: string;
    icon?: LucideIcon;
};

type Props<T extends string> = {
    /** Form field name; omit for a purely visual control (the value is not posted). */
    name?: string;
    label: string;
    options: Option<T>[];
    defaultValue?: T;
    value?: T;
    onValueChange?: (value: T) => void;
    className?: string;
};

/**
 * A "pick one" control drawn as a row of buttons (draft / published). Native radio inputs underneath,
 * so it posts like any field and works with arrow keys and screen readers.
 */
export default function SegmentedField<T extends string>({
    name,
    label,
    options,
    defaultValue,
    value,
    onValueChange,
    className,
}: Props<T>) {
    const group = useId();
    const [inner, setInner] = useState<T>(defaultValue ?? options[0].value);
    const current = value ?? inner;

    return (
        <fieldset className={cn('grid gap-2', className)}>
            <legend className="mb-2 text-sm leading-none font-medium">
                {label}
            </legend>
            <div
                className="grid gap-1 rounded-lg bg-muted p-1"
                style={{
                    gridTemplateColumns: `repeat(${options.length}, minmax(0, 1fr))`,
                }}
            >
                {options.map((option) => {
                    const Icon = option.icon;

                    return (
                        <label key={option.value} className="relative">
                            <input
                                type="radio"
                                name={name ?? group}
                                value={option.value}
                                checked={current === option.value}
                                onChange={() => {
                                    setInner(option.value);
                                    onValueChange?.(option.value);
                                }}
                                className="peer sr-only"
                                // A visual-only control must not post a field the server never reads.
                                {...(name ? {} : { form: 'no-form' })}
                            />
                            <span className="flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-medium text-muted-foreground transition-all peer-checked:bg-background peer-checked:text-foreground peer-checked:shadow-sm peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 hover:text-foreground dark:peer-checked:bg-neutral-600">
                                {Icon && <Icon className="size-3.5" />}
                                {option.label}
                            </span>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
