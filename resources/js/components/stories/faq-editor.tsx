import { Plus, Trash2 } from 'lucide-react';
import { useId } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { FaqItem } from '@/types';

type Props = {
    value: FaqItem[];
    onChange: (items: FaqItem[]) => void;
    errors: Record<string, string>;
    max?: number;
};

/**
 * Question-and-answer pairs shown under the story and published as FAQ structured data, which is
 * what search engines and AI assistants quote. Submits `faq[n][question]` / `faq[n][answer]`.
 */
export default function FaqEditor({
    value,
    onChange,
    errors,
    max = 10,
}: Props) {
    const id = useId();

    const update = (index: number, patch: Partial<FaqItem>) =>
        onChange(
            value.map((item, i) =>
                i === index ? { ...item, ...patch } : item,
            ),
        );

    return (
        <div className="space-y-3">
            {value.map((item, index) => (
                <fieldset
                    key={index}
                    className="grid gap-2 rounded-md border p-3"
                >
                    <legend className="px-1 text-xs text-muted-foreground">
                        Question {index + 1}
                    </legend>
                    <Label htmlFor={`${id}-q-${index}`} className="sr-only">
                        Question {index + 1}
                    </Label>
                    <Input
                        id={`${id}-q-${index}`}
                        name={`faq[${index}][question]`}
                        value={item.question}
                        maxLength={200}
                        placeholder="e.g. How can I donate to Make-A-Wish through Canco Cares?"
                        onChange={(event) =>
                            update(index, { question: event.target.value })
                        }
                    />
                    <InputError message={errors[`faq.${index}.question`]} />
                    <Label htmlFor={`${id}-a-${index}`} className="sr-only">
                        Answer {index + 1}
                    </Label>
                    <Textarea
                        id={`${id}-a-${index}`}
                        name={`faq[${index}][answer]`}
                        value={item.answer}
                        maxLength={1000}
                        rows={3}
                        placeholder="Answer in one or two plain sentences."
                        onChange={(event) =>
                            update(index, { answer: event.target.value })
                        }
                    />
                    <InputError message={errors[`faq.${index}.answer`]} />
                    <div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                onChange(value.filter((_, i) => i !== index))
                            }
                        >
                            <Trash2 /> Remove question {index + 1}
                        </Button>
                    </div>
                </fieldset>
            ))}

            {value.length < max && (
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    onClick={() =>
                        onChange([...value, { question: '', answer: '' }])
                    }
                >
                    <Plus /> Add a question
                </Button>
            )}
        </div>
    );
}
