import { useId } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

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
}: Props) {
    const id = useId();
    const describedBy =
        [help ? `${id}-help` : null, error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {rows ? (
                <Textarea
                    id={id}
                    name={name}
                    defaultValue={defaultValue ?? ''}
                    placeholder={placeholder}
                    maxLength={maxLength}
                    required={required}
                    rows={rows}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                />
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
