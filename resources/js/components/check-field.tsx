import type { LucideIcon } from 'lucide-react';
import { useId } from 'react';
import { Switch } from '@/components/ui/switch';

type Props = {
    name: string;
    label: string;
    description?: string;
    defaultChecked?: boolean;
    /** Makes the switch controlled (for options other parts of the form react to). */
    checked?: boolean;
    onCheckedChange?: (checked: boolean) => void;
    icon?: LucideIcon;
};

/**
 * A labelled on/off option, shown as a switch in a clickable row. It posts `name=1` when on and
 * nothing when off (the server reads a missing key as "off"). The description is announced with
 * the label.
 */
export default function CheckField({
    name,
    label,
    description,
    defaultChecked = false,
    checked,
    onCheckedChange,
    icon: Icon,
}: Props) {
    const id = useId();
    const state = checked === undefined ? { defaultChecked } : { checked };

    return (
        <label
            htmlFor={id}
            className="flex cursor-pointer items-start justify-between gap-4 rounded-lg border p-3 transition-colors hover:bg-muted/40 has-[:checked]:border-primary/40 has-[:checked]:bg-primary/5 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/30"
        >
            <span className="flex min-w-0 items-start gap-3">
                {Icon && (
                    <Icon
                        className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                )}
                <span className="grid gap-0.5">
                    <span className="text-sm leading-snug font-medium">
                        {label}
                    </span>
                    {description && (
                        <span
                            id={`${id}-help`}
                            className="text-xs text-muted-foreground"
                        >
                            {description}
                        </span>
                    )}
                </span>
            </span>
            <Switch
                id={id}
                name={name}
                value="1"
                aria-describedby={description ? `${id}-help` : undefined}
                onChange={(event) => onCheckedChange?.(event.target.checked)}
                {...state}
            />
        </label>
    );
}
