import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type Props = {
    name: string;
    label: string;
    defaultValue?: string | number | null;
    /** Shown when the field is empty (for the home page: the text the site uses by default). */
    placeholder?: string;
    help?: string;
    error?: string;
    maxLength?: number;
    required?: boolean;
    /** Renders a multi-line field with this many rows. */
    rows?: number;
    type?: 'text' | 'email' | 'url' | 'number' | 'tel';
    min?: number;
    max?: number;
    step?: number | string;
    autoComplete?: string;
    inputMode?: 'text' | 'numeric' | 'decimal' | 'email' | 'tel' | 'url';
    /** Shows "12 / 80" beside the label (needs `maxLength`). */
    counter?: boolean;
    /** Called with the text on every edit (for live previews); the field itself stays uncontrolled. */
    onValueChange?: (value: string) => void;
    /** Short text shown before the input, such as "/events/". */
    prefix?: string;
};

/**
 * A labelled text input or textarea with help text and an error message wired to it
 * (aria-describedby / aria-invalid), so every form field reads the same to a screen reader.
 */
export default function FormField({
    name,
    label,
    defaultValue,
    placeholder,
    help,
    error,
    maxLength,
    required,
    rows,
    type = 'text',
    min,
    max,
    step,
    autoComplete,
    inputMode,
    counter = false,
    onValueChange,
    prefix,
}: Props) {
    const id = useId();
    const [length, setLength] = useState(String(defaultValue ?? '').length);
    const track = (value: string) => {
        setLength(value.length);
        onValueChange?.(value);
    };
    const describedBy =
        [help ? `${id}-help` : null, error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined;

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between gap-3">
                <Label htmlFor={id}>{label}</Label>
                {counter && maxLength && (
                    <span
                        className={cn(
                            'text-xs tabular-nums',
                            length >= maxLength
                                ? 'text-amber-700 dark:text-amber-400'
                                : 'text-muted-foreground',
                        )}
                    >
                        {length} / {maxLength}
                    </span>
                )}
            </div>
            {rows ? (
                <Textarea
                    id={id}
                    name={name}
                    defaultValue={defaultValue ?? ''}
                    placeholder={placeholder}
                    maxLength={maxLength}
                    required={required}
                    rows={rows}
                    onChange={(event) => track(event.target.value)}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                />
            ) : prefix ? (
                <div className="flex items-center rounded-md border shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                    <span className="pl-3 text-sm text-muted-foreground select-none">
                        {prefix}
                    </span>
                    <Input
                        id={id}
                        name={name}
                        type={type}
                        defaultValue={defaultValue ?? ''}
                        placeholder={placeholder}
                        maxLength={maxLength}
                        required={required}
                        onChange={(event) => track(event.target.value)}
                        aria-invalid={error ? true : undefined}
                        aria-describedby={describedBy}
                        className="border-0 pl-0.5 shadow-none focus-visible:ring-0"
                    />
                </div>
            ) : (
                <Input
                    id={id}
                    name={name}
                    type={type}
                    defaultValue={defaultValue ?? ''}
                    placeholder={placeholder}
                    maxLength={maxLength}
                    required={required}
                    min={min}
                    max={max}
                    step={step}
                    autoComplete={autoComplete}
                    inputMode={inputMode}
                    onChange={(event) => track(event.target.value)}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                />
            )}
            {help && (
                <p id={`${id}-help`} className="text-xs text-muted-foreground">
                    {help}
                </p>
            )}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}
