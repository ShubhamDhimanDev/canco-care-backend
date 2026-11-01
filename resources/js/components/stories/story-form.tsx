import { Form } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import ImageUploader from '@/components/image-uploader';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { isoToLocalInput, localInputToIso, viewerTimeZone } from '@/lib/date';
import type { StoryFormData } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    form: RouteFormDefinition<'post'>;
    story?: StoryFormData;
    submitLabel: string;
};

/**
 * The story form shared by "New story" and "Edit story".
 * Server-side validation is the source of truth; errors come back per field.
 */
export default function StoryForm({ form, story, submitLabel }: Props) {
    const [publishedLocal, setPublishedLocal] = useState(
        isoToLocalInput(story?.published_at ?? null),
    );
    const [slug, setSlug] = useState(story?.slug ?? '');
    const slugChanged = story !== undefined && slug !== story.slug;

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            className="grid gap-6 lg:grid-cols-3"
        >
            {({ errors, processing }) => {
                const messages = Object.values(errors);
                const hasSeo =
                    Boolean(story?.excerpt) ||
                    Boolean(story?.meta_title) ||
                    Boolean(story?.meta_description) ||
                    Boolean(story?.author_name) ||
                    [
                        'excerpt',
                        'meta_title',
                        'meta_description',
                        'author_name',
                    ].some((field) => field in errors);

                return (
                    <>
                        <div className="space-y-6 lg:col-span-2">
                            {messages.length > 0 && (
                                <div role="alert">
                                    <AlertError
                                        errors={messages}
                                        title="The story was not saved"
                                    />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={story?.title ?? ''}
                                    required
                                    maxLength={200}
                                    autoFocus={story === undefined}
                                    aria-invalid={
                                        errors.title ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.title ? 'title-error' : undefined
                                    }
                                />
                                <InputError
                                    id="title-error"
                                    message={errors.title}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="body">Story text</Label>
                                <RichTextEditor
                                    id="body"
                                    name="body"
                                    label="Story text"
                                    defaultValue={story?.body ?? ''}
                                    invalid={Boolean(errors.body)}
                                    describedBy={
                                        errors.body ? 'body-error' : undefined
                                    }
                                />
                                <InputError
                                    id="body-error"
                                    message={errors.body}
                                />
                            </div>

                            <Collapsible defaultOpen={hasSeo}>
                                <Card>
                                    <CardHeader>
                                        <CollapsibleTrigger asChild>
                                            <button
                                                type="button"
                                                className="group flex w-full items-center justify-between text-left"
                                            >
                                                <span>
                                                    <CardTitle className="text-base">
                                                        Summary and search
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Optional. Controls the
                                                        card text and how the
                                                        story appears in search
                                                        results.
                                                    </CardDescription>
                                                </span>
                                                <ChevronDown className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                                            </button>
                                        </CollapsibleTrigger>
                                    </CardHeader>
                                    <CollapsibleContent>
                                        <CardContent className="space-y-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="excerpt">
                                                    Summary
                                                </Label>
                                                <Textarea
                                                    id="excerpt"
                                                    name="excerpt"
                                                    defaultValue={
                                                        story?.excerpt ?? ''
                                                    }
                                                    maxLength={300}
                                                    rows={3}
                                                    aria-describedby="excerpt-help"
                                                />
                                                <p
                                                    id="excerpt-help"
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Shown on story cards. Leave
                                                    empty to use the start of
                                                    the story text.
                                                </p>
                                                <InputError
                                                    message={errors.excerpt}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="meta_title">
                                                    Search title
                                                </Label>
                                                <Input
                                                    id="meta_title"
                                                    name="meta_title"
                                                    defaultValue={
                                                        story?.meta_title ?? ''
                                                    }
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={errors.meta_title}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="meta_description">
                                                    Search description
                                                </Label>
                                                <Textarea
                                                    id="meta_description"
                                                    name="meta_description"
                                                    defaultValue={
                                                        story?.meta_description ??
                                                        ''
                                                    }
                                                    maxLength={255}
                                                    rows={2}
                                                />
                                                <InputError
                                                    message={
                                                        errors.meta_description
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="author_name">
                                                    Author
                                                </Label>
                                                <Input
                                                    id="author_name"
                                                    name="author_name"
                                                    defaultValue={
                                                        story?.author_name ?? ''
                                                    }
                                                    maxLength={120}
                                                />
                                                <InputError
                                                    message={errors.author_name}
                                                />
                                            </div>
                                        </CardContent>
                                    </CollapsibleContent>
                                </Card>
                            </Collapsible>
                        </div>

                        <aside className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Publishing
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select
                                            name="status"
                                            defaultValue={
                                                story?.status ?? 'draft'
                                            }
                                        >
                                            <SelectTrigger
                                                id="status"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="draft">
                                                    Draft — not on the website
                                                </SelectItem>
                                                <SelectItem value="published">
                                                    Published
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="published_at_local">
                                            Publish date
                                        </Label>
                                        <Input
                                            id="published_at_local"
                                            type="datetime-local"
                                            value={publishedLocal}
                                            onChange={(event) =>
                                                setPublishedLocal(
                                                    event.target.value,
                                                )
                                            }
                                            aria-describedby="published-help"
                                        />
                                        <input
                                            type="hidden"
                                            name="published_at"
                                            value={localInputToIso(
                                                publishedLocal,
                                            )}
                                        />
                                        <p
                                            id="published-help"
                                            className="text-xs text-muted-foreground"
                                        >
                                            Leave empty to publish as soon as
                                            you save. A future date schedules
                                            the story. Times are in your time
                                            zone ({viewerTimeZone()}).
                                        </p>
                                        <InputError
                                            message={errors.published_at}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="slug">
                                            Web address
                                        </Label>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            value={slug}
                                            onChange={(event) =>
                                                setSlug(event.target.value)
                                            }
                                            maxLength={220}
                                            placeholder="Built from the title"
                                            aria-describedby="slug-help"
                                            aria-invalid={
                                                errors.slug ? true : undefined
                                            }
                                        />
                                        <p
                                            id="slug-help"
                                            className="text-xs text-muted-foreground"
                                        >
                                            {slugChanged
                                                ? 'The old address will keep working and send visitors here.'
                                                : 'Lowercase letters, numbers and hyphens. Leave empty to build it from the title.'}
                                        </p>
                                        <InputError message={errors.slug} />
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3 pt-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="save-story-button"
                                        >
                                            {processing && <Spinner />}
                                            {submitLabel}
                                        </Button>
                                        {story?.public_url &&
                                            story.state === 'published' && (
                                                <a
                                                    href={story.public_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-sm underline underline-offset-4"
                                                >
                                                    View on website
                                                </a>
                                            )}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Featured image
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ImageUploader
                                        current={story?.image ?? null}
                                        error={errors.featured_image}
                                        altError={errors.featured_image_alt}
                                    />
                                </CardContent>
                            </Card>
                        </aside>
                    </>
                );
            }}
        </Form>
    );
}
