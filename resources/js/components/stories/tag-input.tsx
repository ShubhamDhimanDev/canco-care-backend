import { X } from 'lucide-react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    value: string[];
    onChange: (tags: string[]) => void;
    suggestions: string[];
    max?: number;
    error?: string;
};

/**
 * Tag chips. Type a tag and press Enter or comma to add it; existing tags are suggested. Submits
 * one `tags[]` field per tag.
 */
export default function TagInput({
    value,
    onChange,
    suggestions,
    max = 10,
    error,
}: Props) {
    const id = useId();
    const [draft, setDraft] = useState('');

    const add = (raw: string) => {
        const name = raw.replace(/\s+/g, ' ').trim().slice(0, 40);

        if (
            name === '' ||
            value.length >= max ||
            value.some((tag) => tag.toLowerCase() === name.toLowerCase())
        ) {
            setDraft('');

            return;
        }

        onChange([...value, name]);
        setDraft('');
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>Tags</Label>

            {value.map((tag) => (
                <input key={tag} type="hidden" name="tags[]" value={tag} />
            ))}

            {value.length > 0 && (
                <ul className="m-0 flex list-none flex-wrap gap-1.5 p-0">
                    {value.map((tag) => (
                        <li
                            key={tag}
                            className="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-1 text-xs text-secondary-foreground"
                        >
                            {tag}
                            <button
                                type="button"
                                aria-label={`Remove tag ${tag}`}
                                onClick={() =>
                                    onChange(value.filter((t) => t !== tag))
                                }
                                className="rounded-sm hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <X className="size-3" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <Input
                id={id}
                value={draft}
                list={`${id}-list`}
                maxLength={40}
                disabled={value.length >= max}
                placeholder={
                    value.length >= max
                        ? `Up to ${max} tags`
                        : 'Add a tag, then press Enter'
                }
                aria-describedby={`${id}-help`}
                onChange={(event) => {
                    const next = event.target.value;

                    // A comma ends the tag, like Enter.
                    if (next.includes(',')) {
                        next.split(',').forEach((part) => add(part));

                        return;
                    }

                    setDraft(next);
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        // Enter must add the tag, not submit the story.
                        event.preventDefault();
                        add(draft);
                    } else if (
                        event.key === 'Backspace' &&
                        draft === '' &&
                        value.length > 0
                    ) {
                        onChange(value.slice(0, -1));
                    }
                }}
                onBlur={() => add(draft)}
            />
            <datalist id={`${id}-list`}>
                {suggestions
                    .filter((tag) => !value.includes(tag))
                    .map((tag) => (
                        <option key={tag} value={tag} />
                    ))}
            </datalist>
            <p id={`${id}-help`} className="text-xs text-muted-foreground">
                Short topics such as “Make-A-Wish” or “Community”. Visitors can
                browse stories by tag.
            </p>
            <InputError message={error} />
        </div>
    );
}
