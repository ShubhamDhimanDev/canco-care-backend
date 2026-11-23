import { Form, Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import AlertError from '@/components/alert-error';
import FormActionBar, { BarLink } from '@/components/form-action-bar';
import FormField from '@/components/form-field';
import ImagePicker from '@/components/media/image-picker';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import SectionCard from '@/components/section-card';
import { Label } from '@/components/ui/label';
import { useFormDirty } from '@/hooks/use-form-dirty';
import type { AttachedImage, PageSection, PageValues } from '@/types';

type Props = {
    slug: string;
    label: string;
    sections: PageSection[];
    values: PageValues;
    defaults: Record<string, string | null>;
    publicUrl: string | null;
};

export default function PageEdit({
    slug,
    label,
    sections,
    values,
    defaults,
    publicUrl,
}: Props) {
    const { dirty, clear, bindings } = useFormDirty();

    return (
        <>
            <Head title={label} />

            <div className="mx-auto w-full max-w-4xl min-w-0 space-y-6 p-4">
                <Form
                    {...PageController.update.form(slug)}
                    options={{ preserveScroll: true }}
                    onSuccess={clear}
                    className="space-y-6"
                    {...bindings}
                >
                    {({ errors, processing, recentlySuccessful }) => {
                        const messages = Object.values(errors);

                        return (
                            <>
                                <FormActionBar
                                    title={label}
                                    subtitle="Leave a field empty to use the original text."
                                    back={{
                                        href: PageController.index(),
                                        label: 'All pages',
                                    }}
                                    extra={
                                        publicUrl ? (
                                            <BarLink href={publicUrl}>
                                                View live page
                                            </BarLink>
                                        ) : null
                                    }
                                    submitLabel="Save page"
                                    processing={processing}
                                    dirty={dirty}
                                    saved={recentlySuccessful}
                                />

                                {messages.length > 0 && (
                                    <AlertError
                                        errors={messages}
                                        title="Please check the highlighted fields"
                                    />
                                )}

                                {sections.map((section) => (
                                    <SectionCard
                                        key={section.title}
                                        icon={FileText}
                                        title={section.title}
                                        description={
                                            section.description ?? undefined
                                        }
                                    >
                                        <div className="space-y-4">
                                            {Object.entries(section.fields).map(
                                                ([key, field]) => {
                                                    const name = `fields[${key}]`;
                                                    const error =
                                                        errors[`fields.${key}`];

                                                    if (
                                                        field.type === 'image'
                                                    ) {
                                                        return (
                                                            <ImagePicker
                                                                key={key}
                                                                fields={{
                                                                    mediaId: `${name}[media_id]`,
                                                                    alt: `${name}[alt]`,
                                                                    remove: `${name}[remove]`,
                                                                }}
                                                                label={`${field.label} (optional)`}
                                                                current={
                                                                    values[
                                                                        key
                                                                    ] as AttachedImage | null
                                                                }
                                                                altRequired
                                                                error={
                                                                    errors[
                                                                        `fields.${key}.media_id`
                                                                    ]
                                                                }
                                                                altError={
                                                                    errors[
                                                                        `fields.${key}.alt`
                                                                    ]
                                                                }
                                                            />
                                                        );
                                                    }

                                                    if (field.type === 'rich') {
                                                        return (
                                                            <div
                                                                key={key}
                                                                className="grid gap-2"
                                                            >
                                                                <Label
                                                                    htmlFor={`rich-${key}`}
                                                                >
                                                                    {
                                                                        field.label
                                                                    }
                                                                </Label>
                                                                {field.help && (
                                                                    <p className="text-sm text-muted-foreground">
                                                                        {
                                                                            field.help
                                                                        }
                                                                    </p>
                                                                )}
                                                                <RichTextEditor
                                                                    id={`rich-${key}`}
                                                                    name={name}
                                                                    label={
                                                                        field.label
                                                                    }
                                                                    defaultValue={
                                                                        (values[
                                                                            key
                                                                        ] as string) ??
                                                                        ''
                                                                    }
                                                                    invalid={Boolean(
                                                                        error,
                                                                    )}
                                                                    minHeightClass="min-h-48"
                                                                />
                                                                <InputError
                                                                    message={
                                                                        error
                                                                    }
                                                                />
                                                            </div>
                                                        );
                                                    }

                                                    return (
                                                        <FormField
                                                            key={key}
                                                            name={name}
                                                            label={field.label}
                                                            help={
                                                                field.help ??
                                                                undefined
                                                            }
                                                            defaultValue={
                                                                (values[
                                                                    key
                                                                ] as string) ??
                                                                ''
                                                            }
                                                            placeholder={
                                                                defaults[key] ??
                                                                undefined
                                                            }
                                                            rows={
                                                                field.type ===
                                                                'textarea'
                                                                    ? 4
                                                                    : undefined
                                                            }
                                                            maxLength={
                                                                field.max ??
                                                                undefined
                                                            }
                                                            counter
                                                            error={error}
                                                        />
                                                    );
                                                },
                                            )}
                                        </div>
                                    </SectionCard>
                                ))}
                            </>
                        );
                    }}
                </Form>
            </div>
        </>
    );
}
