import { Form } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import CheckField from '@/components/check-field';
import InputError from '@/components/input-error';
import ImagePicker from '@/components/media/image-picker';
import RichTextEditor from '@/components/rich-text-editor';
import FaqEditor from '@/components/stories/faq-editor';
import SeoPanel, {
    Counter,
    DESCRIPTION_LIMIT,
    TITLE_LIMIT,
} from '@/components/stories/seo-panel';
import TagInput from '@/components/stories/tag-input';
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
import type { FaqItem, StoryFormData } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    form: RouteFormDefinition<'post'>;
    story?: StoryFormData;
    submitLabel: string;
    /** Every existing tag name, offered as suggestions. */
    allTags: string[];
    /** Public origin of the website, for the search-result preview. */
    siteUrl: string;
};

/** A collapsible card used for the optional blocks of the form. */
function Section({
    title,
    description,
    defaultOpen,
    children,
}: {
    title: string;
    description: string;
    defaultOpen: boolean;
    children: React.ReactNode;
}) {
    return (
        <Collapsible defaultOpen={defaultOpen}>
            <Card>
                <CardHeader>
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="group flex w-full items-center justify-between text-left"
                        >
                            <span>
                                <CardTitle className="text-base">
                                    {title}
                                </CardTitle>
                                <CardDescription>{description}</CardDescription>
                            </span>
                            <ChevronDown className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                        </button>
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent className="space-y-4">{children}</CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    );
}

/**
 * The story form shared by "New story" and "Edit story".
 * Server-side validation is the source of truth; errors come back per field.
 */
export default function StoryForm({
    form,
    story,
    submitLabel,
    allTags,
    siteUrl,
}: Props) {
    const [publishedLocal, setPublishedLocal] = useState(
        isoToLocalInput(story?.published_at ?? null),
    );
    const [slug, setSlug] = useState(story?.slug ?? '');
    const slugChanged = story !== undefined && slug !== story.slug;

    // Controlled so the search preview and checklist update as the editor types.
    const [title, setTitle] = useState(story?.title ?? '');
    const [excerpt, setExcerpt] = useState(story?.excerpt ?? '');
    const [summary, setSummary] = useState(story?.summary ?? '');
    const [metaTitle, setMetaTitle] = useState(story?.meta_title ?? '');
    const [metaDescription, setMetaDescription] = useState(
        story?.meta_description ?? '',
    );
    const [tags, setTags] = useState<string[]>(story?.tags ?? []);
    const [faq, setFaq] = useState<FaqItem[]>(story?.faq ?? []);
    const [hasImage, setHasImage] = useState(story?.image != null);

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            className="grid gap-6 lg:grid-cols-3"
        >
            {({ errors, processing }) => {
                const messages = Object.values(errors);
                const hasAnswers =
                    summary !== '' ||
                    faq.length > 0 ||
                    'summary' in errors ||
                    Object.keys(errors).some((key) => key.startsWith('faq'));
                const hasSeo =
                    excerpt !== '' ||
                    metaTitle !== '' ||
                    metaDescription !== '' ||
                    Boolean(story?.author_name) ||
                    Boolean(story?.noindex) ||
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
                                    value={title}
                                    onChange={(event) =>
                                        setTitle(event.target.value)
                                    }
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

                            <Section
                                title="Quick answer and questions"
                                description="Helps search engines and AI assistants quote this story correctly."
                                defaultOpen={hasAnswers}
                            >
                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="summary">
                                            Quick answer
                                        </Label>
                                        <Counter
                                            length={summary.length}
                                            limit={300}
                                        />
                                    </div>
                                    <Textarea
                                        id="summary"
                                        name="summary"
                                        value={summary}
                                        onChange={(event) =>
                                            setSummary(event.target.value)
                                        }
                                        maxLength={300}
                                        rows={3}
                                        aria-describedby="summary-help"
                                    />
                                    <p
                                        id="summary-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        One or two plain sentences that say what
                                        this story is about and the key fact
                                        (who, what, how much). Shown in a box at
                                        the top of the story.
                                    </p>
                                    <InputError message={errors.summary} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Questions and answers (FAQ)</Label>
                                    <FaqEditor
                                        value={faq}
                                        onChange={setFaq}
                                        errors={errors}
                                    />
                                    <InputError message={errors.faq} />
                                </div>
                            </Section>

                            <Section
                                title="Search and cards"
                                description="Optional. Controls the card text and how the story appears in search results."
                                defaultOpen={hasSeo}
                            >
                                <SeoPanel
                                    siteUrl={siteUrl}
                                    title={title}
                                    slug={slug}
                                    metaTitle={metaTitle}
                                    metaDescription={metaDescription}
                                    fallbackDescription={summary || excerpt}
                                    hasImage={hasImage}
                                    tagCount={tags.length}
                                    faqCount={
                                        faq.filter(
                                            (item) =>
                                                item.question && item.answer,
                                        ).length
                                    }
                                    hasQuickAnswer={summary.trim() !== ''}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="excerpt">Card text</Label>
                                    <Textarea
                                        id="excerpt"
                                        name="excerpt"
                                        value={excerpt}
                                        onChange={(event) =>
                                            setExcerpt(event.target.value)
                                        }
                                        maxLength={300}
                                        rows={3}
                                        aria-describedby="excerpt-help"
                                    />
                                    <p
                                        id="excerpt-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Shown on story cards. Leave empty to use
                                        the start of the story text.
                                    </p>
                                    <InputError message={errors.excerpt} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="meta_title">
                                            Search title
                                        </Label>
                                        <Counter
                                            length={metaTitle.length}
                                            limit={TITLE_LIMIT}
                                        />
                                    </div>
                                    <Input
                                        id="meta_title"
                                        name="meta_title"
                                        value={metaTitle}
                                        onChange={(event) =>
                                            setMetaTitle(event.target.value)
                                        }
                                        maxLength={255}
                                        placeholder={title}
                                    />
                                    <InputError message={errors.meta_title} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="meta_description">
                                            Search description
                                        </Label>
                                        <Counter
                                            length={metaDescription.length}
                                            limit={DESCRIPTION_LIMIT}
                                        />
                                    </div>
                                    <Textarea
                                        id="meta_description"
                                        name="meta_description"
                                        value={metaDescription}
                                        onChange={(event) =>
                                            setMetaDescription(
                                                event.target.value,
                                            )
                                        }
                                        maxLength={255}
                                        rows={2}
                                    />
                                    <InputError
                                        message={errors.meta_description}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="author_name">Author</Label>
                                    <Input
                                        id="author_name"
                                        name="author_name"
                                        defaultValue={story?.author_name ?? ''}
                                        maxLength={120}
                                    />
                                    <InputError message={errors.author_name} />
                                </div>

                                <CheckField
                                    name="noindex"
                                    label="Hide this story from search engines"
                                    description="It stays on the website for people who have the link, but is left out of search results and the sitemap."
                                    defaultChecked={story?.noindex ?? false}
                                />
                            </Section>
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

                                    <CheckField
                                        name="is_featured"
                                        label="Feature on the home page"
                                        description="Featured stories are shown first in “Latest Stories” on the home page."
                                        defaultChecked={
                                            story?.is_featured ?? false
                                        }
                                    />

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
                                    <ImagePicker
                                        fields={{
                                            mediaId: 'featured_media_id',
                                            alt: 'featured_image_alt',
                                            remove: 'remove_image',
                                        }}
                                        label="Image"
                                        current={story?.image ?? null}
                                        altRequired
                                        minWidth={800}
                                        onPresenceChange={setHasImage}
                                        error={
                                            errors.featured_image ??
                                            errors.featured_media_id
                                        }
                                        altError={errors.featured_image_alt}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="pt-6">
                                    <TagInput
                                        value={tags}
                                        onChange={setTags}
                                        suggestions={allTags}
                                        error={errors.tags}
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
