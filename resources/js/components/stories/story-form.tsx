import { Form } from '@inertiajs/react';
import {
    EyeOff,
    FileText,
    Globe,
    MessageCircleQuestion,
    Search,
    Star,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import StoryController from '@/actions/App/Http/Controllers/Admin/StoryController';
import AlertError from '@/components/alert-error';
import CheckField from '@/components/check-field';
import FormActionBar, { BarLink } from '@/components/form-action-bar';
import InputError from '@/components/input-error';
import ImagePicker from '@/components/media/image-picker';
import RichTextEditor from '@/components/rich-text-editor';
import SectionCard from '@/components/section-card';
import SegmentedField from '@/components/segmented-field';
import FaqEditor from '@/components/stories/faq-editor';
import SeoPanel, {
    Counter,
    DESCRIPTION_LIMIT,
    TITLE_LIMIT,
} from '@/components/stories/seo-panel';
import TagInput from '@/components/stories/tag-input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useFormDirty } from '@/hooks/use-form-dirty';
import { isoToLocalInput, localInputToIso, viewerTimeZone } from '@/lib/date';
import type { FaqItem, StoryFormData } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    form: RouteFormDefinition<'post'>;
    story?: StoryFormData;
    /** "New story" / "Edit story", shown in the sticky action bar. */
    title: string;
    subtitle?: ReactNode;
    badge?: ReactNode;
    submitLabel: string;
    /** Every existing tag name, offered as suggestions. */
    allTags: string[];
    /** Public origin of the website, for the search-result preview. */
    siteUrl: string;
};

/** What the website will do with the story once saved, in plain words. */
function outcome(
    status: 'draft' | 'published',
    publishedLocal: string,
    wasPublished: boolean,
): string {
    if (status === 'draft') {
        return wasPublished
            ? 'Saving as a draft takes the story off the website.'
            : 'Not on the website. Only you and other staff can see it.';
    }

    if (publishedLocal === '') {
        return 'Goes live as soon as you save.';
    }

    const when = new Date(publishedLocal);

    if (Number.isNaN(when.getTime())) {
        return 'Goes live as soon as you save.';
    }

    const text = new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(when);

    return when.getTime() > Date.now()
        ? `Scheduled: goes live on ${text}.`
        : `Live on the website, dated ${text}.`;
}

/** A rough web-address preview of what the server will build from the title. */
function previewSlug(title: string): string {
    return title
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

/**
 * The story form shared by "New story" and "Edit story".
 * Server-side validation is the source of truth; errors come back per field.
 */
export default function StoryForm({
    form,
    story,
    title: heading,
    subtitle,
    badge,
    submitLabel,
    allTags,
    siteUrl,
}: Props) {
    const { dirty, touch, clear, bindings } = useFormDirty();
    const [status, setStatus] = useState<'draft' | 'published'>(
        story?.status ?? 'draft',
    );
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

    const address = `${siteUrl.replace(/^https?:\/\//, '')}/stories/${slug.trim() || previewSlug(title) || '…'}`;

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onSuccess={clear}
            className="space-y-6"
            {...bindings}
        >
            {({ errors, processing, recentlySuccessful }) => {
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
                        <FormActionBar
                            title={heading}
                            subtitle={subtitle}
                            badge={badge}
                            back={{
                                href: StoryController.index(),
                                label: 'Back to stories',
                            }}
                            extra={
                                story?.public_url &&
                                story.state === 'published' ? (
                                    <BarLink href={story.public_url}>
                                        View
                                    </BarLink>
                                ) : null
                            }
                            submitLabel={submitLabel}
                            submitTest="save-story-button"
                            processing={processing}
                            dirty={dirty}
                            saved={recentlySuccessful}
                        />

                        {messages.length > 0 && (
                            <div role="alert">
                                <AlertError
                                    errors={messages}
                                    title="The story was not saved"
                                />
                            </div>
                        )}

                        <div className="grid gap-6 lg:grid-cols-3">
                            <div className="space-y-6 lg:col-span-2">
                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="title">Title</Label>
                                        <Counter
                                            length={title.length}
                                            limit={200}
                                        />
                                    </div>
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
                                        placeholder="A headline that says what happened"
                                        className="h-12 text-lg font-semibold md:text-lg"
                                        aria-invalid={
                                            errors.title ? true : undefined
                                        }
                                        aria-describedby={
                                            errors.title
                                                ? 'title-error'
                                                : 'title-address'
                                        }
                                    />
                                    <p
                                        id="title-address"
                                        className="truncate text-xs text-muted-foreground"
                                    >
                                        Web address:{' '}
                                        <span className="font-mono">
                                            {address}
                                        </span>
                                    </p>
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
                                        minHeightClass="min-h-[28rem]"
                                        invalid={Boolean(errors.body)}
                                        describedBy={
                                            errors.body
                                                ? 'body-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="body-error"
                                        message={errors.body}
                                    />
                                </div>

                                <SectionCard
                                    collapsible
                                    icon={MessageCircleQuestion}
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
                                            One or two plain sentences that say
                                            what this story is about and the key
                                            fact (who, what, how much). Shown in
                                            a box at the top of the story.
                                        </p>
                                        <InputError message={errors.summary} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>
                                            Questions and answers (FAQ)
                                        </Label>
                                        <FaqEditor
                                            value={faq}
                                            onChange={(items) => {
                                                setFaq(items);
                                                touch();
                                            }}
                                            errors={errors}
                                        />
                                        <InputError message={errors.faq} />
                                    </div>
                                </SectionCard>

                                <SectionCard
                                    collapsible
                                    icon={Search}
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
                                                    item.question &&
                                                    item.answer,
                                            ).length
                                        }
                                        hasQuickAnswer={summary.trim() !== ''}
                                    />

                                    <div className="grid gap-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="excerpt">
                                                Card text
                                            </Label>
                                            <Counter
                                                length={excerpt.length}
                                                limit={300}
                                            />
                                        </div>
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
                                            Shown on story cards. Leave empty to
                                            use the start of the story text.
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
                                        <InputError
                                            message={errors.meta_title}
                                        />
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

                                    <CheckField
                                        name="noindex"
                                        icon={EyeOff}
                                        label="Hide this story from search engines"
                                        description="It stays on the website for people who have the link, but is left out of search results and the sitemap."
                                        defaultChecked={story?.noindex ?? false}
                                    />
                                </SectionCard>
                            </div>

                            <aside className="space-y-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Publishing
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-5">
                                        <div className="grid gap-2">
                                            <SegmentedField
                                                name="status"
                                                label="Status"
                                                value={status}
                                                onValueChange={setStatus}
                                                options={[
                                                    {
                                                        value: 'draft',
                                                        label: 'Draft',
                                                        icon: FileText,
                                                    },
                                                    {
                                                        value: 'published',
                                                        label: 'Published',
                                                        icon: Globe,
                                                    },
                                                ]}
                                            />
                                            <p
                                                role="status"
                                                className="rounded-md bg-muted/60 px-3 py-2 text-xs text-muted-foreground"
                                            >
                                                {outcome(
                                                    status,
                                                    publishedLocal,
                                                    story?.state ===
                                                        'published',
                                                )}
                                            </p>
                                            <InputError
                                                message={errors.status}
                                            />
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
                                                Leave empty to publish as soon
                                                as you save. A future date
                                                schedules the story. Times are
                                                in your time zone (
                                                {viewerTimeZone()}).
                                            </p>
                                            <InputError
                                                message={errors.published_at}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="slug">
                                                Web address
                                            </Label>
                                            <div className="flex items-center rounded-md border shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                                                <span className="pl-3 text-sm text-muted-foreground select-none">
                                                    /stories/
                                                </span>
                                                <Input
                                                    id="slug"
                                                    name="slug"
                                                    value={slug}
                                                    onChange={(event) =>
                                                        setSlug(
                                                            event.target.value,
                                                        )
                                                    }
                                                    maxLength={220}
                                                    placeholder="built-from-the-title"
                                                    aria-describedby="slug-help"
                                                    aria-invalid={
                                                        errors.slug
                                                            ? true
                                                            : undefined
                                                    }
                                                    className="border-0 pl-0.5 shadow-none focus-visible:ring-0"
                                                />
                                            </div>
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
                                            icon={Star}
                                            label="Feature on the home page"
                                            description="Featured stories are shown first in “Latest Stories” on the home page."
                                            defaultChecked={
                                                story?.is_featured ?? false
                                            }
                                        />
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
                                            onChange={(next) => {
                                                setTags(next);
                                                touch();
                                            }}
                                            suggestions={allTags}
                                            error={errors.tags}
                                        />
                                    </CardContent>
                                </Card>
                            </aside>
                        </div>
                    </>
                );
            }}
        </Form>
    );
}
