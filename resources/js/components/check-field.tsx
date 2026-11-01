import { useId } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type Props = {
    name: string;
    label: string;
    description?: string;
    defaultChecked?: boolean;
    onCheckedChange?: (checked: boolean) => void;
};

/**
 * A labelled on/off option that posts `name=1` when ticked and nothing when not (the server reads
 * a missing key as "off"). The description is announced with the label.
 */
export default function CheckField({
    name,
    label,
    description,
    defaultChecked = false,
    onCheckedChange,
}: Props) {
    const id = useId();

    return (
        <div className="flex items-start gap-3">
            <Checkbox
                id={id}
                name={name}
                value="1"
                defaultChecked={defaultChecked}
                aria-describedby={description ? `${id}-help` : undefined}
                onCheckedChange={(value) => onCheckedChange?.(value === true)}
                className="mt-0.5"
            />
            <div className="grid gap-0.5">
                <Label htmlFor={id} className="leading-snug">
                    {label}
                </Label>
                {description && (
                    <p
                        id={`${id}-help`}
                        className="text-xs text-muted-foreground"
                    >
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}
